<?php

declare(strict_types=1);

namespace MediCareMini\Infrastructure\Mail;

use MediCareMini\Domain\Enum\Locale;
use MediCareMini\Infrastructure\Support\Translator;

/**
 * Builds HTML emails.
 *
 * Email HTML is not web HTML. This renderer deliberately uses a table layout
 * with inline styles only, because Gmail strips <style> blocks, Outlook
 * ignores most of flexbox and float, and no mail client reliably supports
 * external stylesheets. Everything here is the subset that renders the same
 * in Gmail, Outlook, Apple Mail and the Android clients common in Ethiopia.
 *
 * Amharic messages get the Ethiopic font stack and a looser line-height;
 * Ge'ez glyphs are tall and collide at the 1.4 that suits Latin text.
 */
final readonly class MailRenderer
{
    private const string BRAND      = '#056460';
    private const string BRAND_DARK = '#063b3a';
    private const string INK        = '#0f172a';
    private const string MUTED      = '#64748b';
    private const string BORDER     = '#e2e8f0';
    private const string CANVAS     = '#f1f5f9';

    public function __construct(
        private Translator $translator,
        private string $clinicName,
        private string $appUrl,
        private string $supportPhone,
        private string $supportEmail,
    ) {
    }

    private function fontStack(Locale $locale): string
    {
        return $locale === Locale::AM
            ? "'Noto Sans Ethiopic', 'Nyala', 'Abyssinica SIL', Arial, sans-serif"
            : "'Inter', -apple-system, 'Segoe UI', Roboto, Arial, sans-serif";
    }

    private function lineHeight(Locale $locale): string
    {
        return $locale === Locale::AM ? '1.75' : '1.6';
    }

    /**
     * Wrap body content in the branded shell.
     *
     * @param list<array{label:string, value:string}> $details    key/value table
     * @param array{url:string, label:string}|null    $primaryCta
     */
    public function render(
        Locale $locale,
        string $heading,
        string $greeting,
        string $intro,
        array $details = [],
        ?array $primaryCta = null,
        string $extraHtml = '',
        ?string $noticeHtml = null,
    ): string {
        $font = $this->fontStack($locale);
        $lh   = $this->lineHeight($locale);
        $dir  = 'ltr'; // Ge'ez is written left to right, like Latin.

        $e = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $detailRows = '';

        foreach ($details as $detail) {
            $detailRows .= sprintf(
                '<tr>
                   <td style="padding:10px 0;border-bottom:1px solid %s;color:%s;font-size:14px;width:40%%;vertical-align:top;">%s</td>
                   <td style="padding:10px 0;border-bottom:1px solid %s;color:%s;font-size:14px;font-weight:600;text-align:right;vertical-align:top;">%s</td>
                 </tr>',
                self::BORDER,
                self::MUTED,
                $e($detail['label']),
                self::BORDER,
                self::INK,
                $e($detail['value']),
            );
        }

        $detailTable = $detailRows === '' ? '' : sprintf(
            '<table role="presentation" width="100%%" cellpadding="0" cellspacing="0"
                    style="border-collapse:collapse;margin:24px 0;background:#ffffff;border:1px solid %s;border-radius:12px;padding:8px 20px;">
               %s
             </table>',
            self::BORDER,
            $detailRows,
        );

        // Bulletproof-ish button: a padded table cell, which Outlook renders
        // correctly where a styled <a> collapses to plain text.
        $cta = $primaryCta === null ? '' : sprintf(
            '<table role="presentation" cellpadding="0" cellspacing="0" style="margin:28px 0;">
               <tr><td align="center" bgcolor="%s" style="border-radius:12px;">
                 <a href="%s" target="_blank"
                    style="display:inline-block;padding:14px 28px;font-family:%s;font-size:15px;
                           font-weight:700;color:#ffffff;text-decoration:none;border-radius:12px;">%s</a>
               </td></tr>
             </table>',
            self::BRAND,
            $e($primaryCta['url']),
            $font,
            $e($primaryCta['label']),
        );

        $notice = $noticeHtml === null ? '' : sprintf(
            '<div style="margin:20px 0;padding:14px 16px;background:#fffbeb;border-left:4px solid #d99a32;
                         border-radius:8px;color:#78350f;font-size:14px;line-height:%s;">%s</div>',
            $lh,
            $noticeHtml,
        );

        return sprintf(
            '<!doctype html>
<html lang="%s" dir="%s">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="color-scheme" content="light">
<title>%s</title>
</head>
<body style="margin:0;padding:0;background:%s;font-family:%s;">
<!-- Preheader: shown in the inbox preview, hidden in the message body -->
<div style="display:none;max-height:0;overflow:hidden;opacity:0;">%s</div>

<table role="presentation" width="100%%" cellpadding="0" cellspacing="0" style="background:%s;padding:24px 12px;">
  <tr><td align="center">
    <table role="presentation" width="100%%" cellpadding="0" cellspacing="0"
           style="max-width:600px;background:#ffffff;border-radius:18px;overflow:hidden;
                  box-shadow:0 1px 3px rgba(15,23,42,.08);">

      <!-- Header -->
      <tr><td style="background:%s;padding:28px 32px;">
        <table role="presentation" cellpadding="0" cellspacing="0"><tr>
          <td style="padding-right:12px;">
            <!-- Decorative monogram: aria-hidden keeps it out of the
                 plain-text alternative and out of screen readers. -->
            <div aria-hidden="true"
                 style="width:42px;height:42px;background:%s;border-radius:12px;color:#ffffff;
                        font-size:20px;font-weight:800;text-align:center;line-height:42px;">A</div>
          </td>
          <td>
            <div style="color:#ffffff;font-size:17px;font-weight:700;">%s</div>
            <div style="color:#9ce6e1;font-size:11px;letter-spacing:.12em;text-transform:uppercase;">Addis Ababa</div>
          </td>
        </tr></table>
      </td></tr>

      <!-- Body -->
      <tr><td style="padding:32px;">
        <h1 style="margin:0 0 18px;font-size:22px;line-height:1.3;color:%s;font-weight:800;">%s</h1>
        <p style="margin:0 0 14px;font-size:15px;line-height:%s;color:%s;">%s</p>
        <p style="margin:0;font-size:15px;line-height:%s;color:%s;">%s</p>
        %s
        %s
        %s
        %s
      </td></tr>

      <!-- Footer -->
      <tr><td style="padding:22px 32px;background:%s;border-top:1px solid %s;">
        <p style="margin:0 0 8px;font-size:13px;line-height:1.6;color:%s;">%s</p>
        <p style="margin:0;font-size:12px;line-height:1.6;color:%s;">%s</p>
        <p style="margin:12px 0 0;font-size:12px;color:%s;">&copy; %s %s</p>
      </td></tr>

    </table>
  </td></tr>
</table>
</body>
</html>',
            $e($locale->htmlLang()),
            $dir,
            $e($heading),
            self::CANVAS,
            $font,
            $e(mb_substr(strip_tags($intro), 0, 140)),
            self::CANVAS,
            self::BRAND_DARK,
            self::BRAND,
            $e($this->clinicName),
            self::BRAND_DARK,
            $e($heading),
            $lh,
            self::INK,
            $e($greeting),
            $lh,
            self::MUTED,
            $e($intro),
            $notice,
            $detailTable,
            $cta,
            $extraHtml,
            self::CANVAS,
            self::BORDER,
            self::MUTED,
            $e($this->translator->get('email.contact_note', [
                'phone' => $this->supportPhone,
                'email' => $this->supportEmail,
            ])),
            self::MUTED,
            $e($this->translator->get('email.auto_note')),
            self::MUTED,
            date('Y'),
            $e($this->clinicName),
        );
    }

    /**
     * A prominent booking-reference block.
     *
     * The reference is the one string a patient must copy onto a bank
     * transfer, so it gets monospace, letter-spacing and its own panel
     * rather than being buried in a sentence.
     */
    public function referenceBlock(string $reference, string $label, string $hint): string
    {
        $e = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return sprintf(
            '<table role="presentation" width="100%%" cellpadding="0" cellspacing="0"
                    style="margin:8px 0 4px;background:#effcfb;border:1px dashed %s;border-radius:14px;">
               <tr><td style="padding:20px;text-align:center;">
                 <div style="font-size:11px;letter-spacing:.14em;text-transform:uppercase;color:%s;font-weight:700;">%s</div>
                 <div style="margin-top:8px;font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;
                             font-size:26px;font-weight:800;letter-spacing:.08em;color:%s;">%s</div>
                 <div style="margin-top:10px;font-size:12px;color:%s;line-height:1.5;">%s</div>
               </td></tr>
             </table>',
            self::BRAND,
            self::BRAND,
            $e($label),
            self::BRAND_DARK,
            $e($reference),
            self::MUTED,
            $e($hint),
        );
    }

    /** A numbered "what happens next" list. @param list<string> $steps */
    public function stepList(string $title, array $steps): string
    {
        $e = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $items = '';
        $index = 1;

        foreach ($steps as $step) {
            $items .= sprintf(
                '<tr>
                   <td style="padding:6px 12px 6px 0;vertical-align:top;width:26px;">
                     <div style="width:22px;height:22px;background:%s;border-radius:50%%;color:#ffffff;
                                 font-size:12px;font-weight:700;text-align:center;line-height:22px;">%d</div>
                   </td>
                   <td style="padding:6px 0;font-size:14px;line-height:1.6;color:%s;">%s</td>
                 </tr>',
                self::BRAND,
                $index++,
                self::MUTED,
                $e($step),
            );
        }

        return sprintf(
            '<div style="margin:24px 0 0;">
               <div style="font-size:14px;font-weight:700;color:%s;margin-bottom:10px;">%s</div>
               <table role="presentation" cellpadding="0" cellspacing="0" width="100%%">%s</table>
             </div>',
            self::BRAND_DARK,
            $e($title),
            $items,
        );
    }
}
