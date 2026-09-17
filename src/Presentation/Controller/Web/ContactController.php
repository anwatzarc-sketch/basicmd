<?php

declare(strict_types=1);

namespace Aster\Presentation\Controller\Web;

use Aster\Application\Service\NotificationService;
use Aster\Application\Service\SeoService;
use Aster\Domain\Exception\ValidationException;
use Aster\Domain\ValueObject\PhoneNumber;
use Aster\Infrastructure\Persistence\AuditLogger;
use Aster\Infrastructure\Persistence\InquiryRepository;
use Aster\Infrastructure\Persistence\SettingsRepository;
use Aster\Infrastructure\Security\RateLimiter;
use Aster\Infrastructure\Security\SessionManager;
use Aster\Infrastructure\Support\Config;
use Aster\Infrastructure\Support\Env;
use Aster\Infrastructure\Support\Logger;
use Aster\Presentation\Controller\Controller;
use Aster\Presentation\Http\Request;
use Aster\Presentation\Http\Response;
use Aster\Presentation\View\View;

/**
 * The public contact form.
 */
final class ContactController extends Controller
{
    public function __construct(
        View $view,
        SessionManager $session,
        Config $config,
        private readonly InquiryRepository $inquiries,
        private readonly NotificationService $notifications,
        private readonly SettingsRepository $settings,
        private readonly RateLimiter $limiter,
        private readonly AuditLogger $audit,
        private readonly Logger $logger,
        private readonly SeoService $seo,
    ) {
        parent::__construct($view, $session, $config);
    }

    public function form(Request $request): Response
    {
        return $this->render('public/contact', [
            'settings' => $this->settings,
            'map'      => $this->config->mapCoordinates(),
            'mapKey'   => $this->config->googleMapsKey(),
            'meta'     => [
                'title'       => $this->seo->title(
                    $this->view->translator->get('contact.title'),
                    $this->currentLocale(),
                ),
                'description' => 'Contact ' . $this->view->brand->businessName . ' in ' . $this->view->brand->mainCity . '. Phone, email and enquiry form.',
                'canonical'   => $this->config->url('contact'),
            ],
        ]);
    }

    public function store(Request $request): Response
    {
        $ip = $request->ip($this->config->trustProxy());

        if (!$this->limiter->attempt('contact:' . $ip, Env::int('RATE_CONTACT_PER_HOUR', 5), 3600)) {
            return $this->redirectWithError('contact', $this->view->translator->get('validation.rate_limited'));
        }

        // Honeypot: a field hidden with CSS that a human never sees. Most
        // form spam fills every input it finds, so a non-empty value here is
        // a reliable bot signal. Accepted silently so the bot does not learn
        // to skip the field.
        if ($request->input('website') !== null) {
            $this->logger->info('Contact honeypot triggered', ['ip' => $ip]);

            return $this->redirectWithSuccess('contact', $this->view->translator->get('contact.sent'));
        }

        try {
            $data = $this->validate($request);
        } catch (ValidationException $e) {
            return $this->redirectWithValidation('contact', $e, $request);
        }

        $inquiryId = $this->inquiries->create([
            'con_name'   => $data['con_name'],
            'phone'      => $data['phone']->e164,
            'email'      => $data['email'],
            'subject'    => $data['subject'],
            'message'    => $data['message'],
            'locale'     => $this->currentLocale()->value,
            'ip_address' => $request->ipBinary($this->config->trustProxy()),
        ]);

        $inquiry = $this->inquiries->findById($inquiryId);

        $this->audit
            ->asAnonymous($data['con_name'], $request->ipBinary($this->config->trustProxy()), $request->userAgent())
            ->record('inquiry.created', 'inquiry', $inquiryId, 'Contact form submitted');

        if ($inquiry !== null) {
            // Notification failures must not make a successful submission
            // look like a failure to the patient.
            try {
                $this->notifications->inquiryAcknowledgement($inquiry);
                $this->notifications->notifyStaffNewInquiry($inquiry);
            } catch (\Throwable $e) {
                $this->logger->error('Inquiry notification failed', [
                    'inquiry_id' => $inquiryId,
                    'error'      => $e->getMessage(),
                ]);
            }
        }

        return $this->redirectWithSuccess('contact', $this->view->translator->get('contact.sent'));
    }

    /**
     * @return array{con_name:string, phone:PhoneNumber, email:?string, subject:?string, message:string}
     * @throws ValidationException
     */
    private function validate(Request $request): array
    {
        $errors = [];

        $name = $request->input('con_name') ?? '';

        if ($name === '') {
            $errors['con_name'][] = 'Please enter your name.';
        } elseif (mb_strlen($name) > 160) {
            $errors['con_name'][] = 'That name is too long.';
        }

        $phone    = null;
        $rawPhone = $request->input('phone') ?? '';

        if ($rawPhone === '') {
            $errors['phone'][] = 'Please enter a phone number.';
        } else {
            $phone = PhoneNumber::tryFrom($rawPhone);

            if ($phone === null) {
                $errors['phone'][] = $this->view->translator->get('validation.phone');
            }
        }

        $email = $request->input('email');

        if ($email !== null && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $errors['email'][] = $this->view->translator->get('validation.email');
        }

        $message = $request->input('message') ?? '';

        if ($message === '') {
            $errors['message'][] = 'Please enter your message.';
        } elseif (mb_strlen($message) < 10) {
            $errors['message'][] = 'Please give us a little more detail.';
        } elseif (mb_strlen($message) > 4000) {
            $errors['message'][] = 'Please keep your message under 4000 characters.';
        }

        $subject = $request->input('subject');

        if ($subject !== null && mb_strlen($subject) > 190) {
            $errors['subject'][] = 'That subject is too long.';
        }

        if ($errors !== []) {
            throw ValidationException::withErrors($errors);
        }

        assert($phone instanceof PhoneNumber);

        return [
            'con_name' => $name,
            'phone'    => $phone,
            'email'    => $email !== null ? mb_strtolower($email) : null,
            'subject'  => $subject,
            'message'  => $message,
        ];
    }
}
