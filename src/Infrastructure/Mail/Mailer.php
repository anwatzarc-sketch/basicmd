<?php

declare(strict_types=1);

namespace Aster\Infrastructure\Mail;

use Aster\Infrastructure\Support\Env;
use Aster\Infrastructure\Support\Logger;
use PHPMailer\PHPMailer\Exception as PHPMailerException;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;

/**
 * PHPMailer transport.
 *
 * Nothing in a web request ever calls this directly - requests enqueue into
 * email_outbox and return. An SMTP handshake can take seconds or hang
 * outright, and a patient must never watch a booking spinner because a mail
 * server is slow. The queue worker is the only caller.
 *
 * Three transport modes:
 *   smtp  - real delivery (or a local sink such as MailHog on :1025)
 *   log   - render to storage/logs/mail.log, send nothing
 *   (catch-all) - rewrite every recipient to MAIL_CATCH_ALL, for staging
 */
final class Mailer
{
    private readonly string $driver;

    private readonly string $fromAddress;

    private readonly string $fromName;

    private readonly ?string $replyTo;

    private readonly ?string $catchAll;

    public function __construct(
        private readonly Logger $logger,
        private readonly string $logDirectory,
    ) {
        $this->driver      = strtolower(Env::get('MAIL_MAILER', 'smtp') ?? 'smtp');
        $this->fromAddress = Env::get('MAIL_FROM_ADDRESS', 'no-reply@pyramid.biz.et') ?? 'no-reply@pyramid.biz.et';
        $this->fromName    = Env::get('MAIL_FROM_NAME', 'Aster Medical Center') ?? 'Aster Medical Center';
        $this->replyTo     = Env::get('MAIL_REPLY_TO') ?: null;
        $this->catchAll    = Env::get('MAIL_CATCH_ALL') ?: null;
    }

    /**
     * Deliver one message.
     *
     * @throws MailException when delivery fails; the queue worker catches it
     *                       and schedules a retry with backoff.
     */
    public function send(
        string $toEmail,
        ?string $toName,
        string $subject,
        string $htmlBody,
        ?string $textBody = null,
        array $attachments = [],
    ): void {
        // Staging safety net: never let a test run email real patients.
        if ($this->catchAll !== null) {
            $subject = '[to: ' . $toEmail . '] ' . $subject;
            $toEmail = $this->catchAll;
            $toName  = null;
        }

        if ($this->driver === 'log') {
            $this->writeToLog($toEmail, $toName, $subject, $htmlBody, $textBody);

            return;
        }

        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host       = Env::get('MAIL_HOST', '127.0.0.1') ?? '127.0.0.1';
            $mail->Port       = Env::int('MAIL_PORT', 1025);
            $mail->CharSet    = PHPMailer::CHARSET_UTF8;
            // Base64 rather than quoted-printable: Ge'ez script is entirely
            // multi-byte, and QP would bloat an Amharic message by ~3x and
            // mangle it in older clients.
            $mail->Encoding   = PHPMailer::ENCODING_BASE64;
            $mail->Timeout    = 15;
            $mail->SMTPDebug  = SMTP::DEBUG_OFF;

            // A local sink accepts plaintext with no credentials; forcing
            // auth or TLS there would make development impossible.
            $mail->SMTPAuth = Env::bool('MAIL_AUTH', false);

            if ($mail->SMTPAuth) {
                $mail->Username = Env::get('MAIL_USERNAME', '') ?? '';
                $mail->Password = Env::get('MAIL_PASSWORD', '') ?? '';
            }

            $encryption = strtolower(Env::get('MAIL_ENCRYPTION', '') ?? '');

            if ($encryption === 'tls') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            } elseif ($encryption === 'ssl') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            } else {
                $mail->SMTPSecure  = '';
                $mail->SMTPAutoTLS = false;
            }

            $mail->setFrom($this->fromAddress, $this->fromName);
            $mail->addAddress($toEmail, $toName ?? '');

            if ($this->replyTo !== null) {
                $mail->addReplyTo($this->replyTo);
            }

            $mail->Subject = $subject;
            $mail->isHTML(true);
            $mail->Body    = $htmlBody;
            $mail->AltBody = $textBody ?? self::htmlToText($htmlBody);

            // Marks the message as automated so mailbox providers and
            // out-of-office responders do not reply to it.
            $mail->addCustomHeader('Auto-Submitted', 'auto-generated');
            $mail->addCustomHeader('X-Auto-Response-Suppress', 'All');

            foreach ($attachments as $attachment) {
                if (isset($attachment['path']) && is_readable($attachment['path'])) {
                    $mail->addAttachment($attachment['path'], $attachment['name'] ?? '');
                }
            }

            $mail->send();
        } catch (PHPMailerException $e) {
            throw new MailException(
                'SMTP delivery failed: ' . $mail->ErrorInfo,
                previous: $e,
            );
        }
    }

    /** Render a message to disk instead of sending it. */
    private function writeToLog(
        string $toEmail,
        ?string $toName,
        string $subject,
        string $htmlBody,
        ?string $textBody,
    ): void {
        $entry = sprintf(
            "%s\n=== MAIL %s ===\nTo: %s <%s>\nFrom: %s <%s>\nSubject: %s\n\n%s\n\n",
            str_repeat('=', 78),
            date('Y-m-d H:i:s'),
            $toName ?? '',
            $toEmail,
            $this->fromName,
            $this->fromAddress,
            $subject,
            $textBody ?? self::htmlToText($htmlBody),
        );

        @file_put_contents($this->logDirectory . '/mail.log', $entry, FILE_APPEND | LOCK_EX);

        $this->logger->info('Mail written to log (MAIL_MAILER=log)', ['subject' => $subject]);
    }

    /**
     * Plain-text alternative, generated from the HTML.
     *
     * Every message ships both parts: some Ethiopian mobile mail clients
     * render HTML poorly, and a text/plain alternative measurably improves
     * spam scoring.
     *
     * The email HTML is table-based out of necessity, which a naive strip_tags
     * turns into a column of blank lines. This walks the structure instead:
     * the hidden preheader is dropped, two-cell detail rows collapse to
     * "Label: Value", and runs of empty lines are squeezed out.
     */
    public static function htmlToText(string $html): string
    {
        $text = $html;

        // Drop elements whose text must not appear in the body: the preheader
        // is inbox-preview only and would otherwise be duplicated verbatim.
        $text = preg_replace('/<div[^>]*display:\s*none[^>]*>.*?<\/div>/is', '', $text) ?? $text;
        $text = preg_replace('/<(script|style|head|title)\b[^>]*>.*?<\/\1>/is', '', $text) ?? $text;

        // Decorative elements (the monogram in the header) carry no meaning
        // in a text-only reading and would otherwise surface as stray letters.
        $text = preg_replace('/<(\w+)[^>]*aria-hidden=["\']true["\'][^>]*>.*?<\/\1>/is', '', $text) ?? $text;

        // Preserve link targets - "click here" is useless without the URL.
        // Parentheses, not angle brackets: <https://...> would be swallowed by
        // the strip_tags() below, silently removing every link from the text
        // part of the confirmation email.
        $text = preg_replace(
            '/<a\b[^>]*href=["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/is',
            '$2 ( $1 )',
            $text,
        ) ?? $text;

        // A two-cell row is a label/value pair; render it on one line.
        $text = preg_replace_callback(
            '/<tr\b[^>]*>\s*<t[dh]\b[^>]*>(.*?)<\/t[dh]>\s*<t[dh]\b[^>]*>(.*?)<\/t[dh]>\s*<\/tr>/is',
            static function (array $m): string {
                $label = trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                $value = trim(html_entity_decode(strip_tags($m[2]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

                if ($label === '' && $value === '') {
                    return "\n";
                }

                return "\n" . ($label === '' ? $value : $label . ': ' . $value);
            },
            $text,
        ) ?? $text;

        $text = preg_replace('/<br\s*\/?>/i', "\n", $text) ?? $text;
        $text = preg_replace('/<\/(p|div|tr|h[1-6]|li|td|th|table)>/i', "\n", $text) ?? $text;
        $text = preg_replace('/<li\b[^>]*>/i', '- ', $text) ?? $text;

        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Normalise line by line: collapse internal runs of spaces, drop
        // trailing whitespace, and remove lines that became empty.
        $lines = preg_split('/\R/u', $text) ?: [];
        $clean = [];
        $blank = 0;

        foreach ($lines as $line) {
            $line = rtrim(preg_replace('/[ \t\x{00A0}]+/u', ' ', $line) ?? $line);
            $line = trim($line);

            if ($line === '') {
                // Allow a single blank line as a paragraph break, never more.
                if (++$blank > 1) {
                    continue;
                }

                $clean[] = '';

                continue;
            }

            $blank   = 0;
            $clean[] = $line;
        }

        return trim(implode("\n", $clean));
    }

    public function isLogDriver(): bool
    {
        return $this->driver === 'log';
    }

    /**
     * Verify SMTP connectivity without sending, for bin/mail-test.php.
     *
     * @return array{ok: bool, message: string}
     */
    public function testConnection(): array
    {
        if ($this->driver === 'log') {
            return ['ok' => true, 'message' => 'MAIL_MAILER=log - messages are written to storage/logs/mail.log.'];
        }

        $smtp = new SMTP();
        $host = Env::get('MAIL_HOST', '127.0.0.1') ?? '127.0.0.1';
        $port = Env::int('MAIL_PORT', 1025);

        try {
            if (!$smtp->connect($host, $port, 10)) {
                return ['ok' => false, 'message' => "Could not connect to {$host}:{$port}."];
            }

            $smtp->hello('localhost');
            $smtp->quit();

            return ['ok' => true, 'message' => "Connected to {$host}:{$port} successfully."];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }
}
