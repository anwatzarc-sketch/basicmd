<?php

declare(strict_types=1);

namespace MediCareMini\Presentation\Controller\Web;

use MediCareMini\Application\DTO\BookingRequest;
use MediCareMini\Application\Service\BookingService;
use MediCareMini\Application\Service\PaymentService;
use MediCareMini\Application\Service\SeoService;
use MediCareMini\Domain\Enum\BookingSource;
use MediCareMini\Domain\Enum\QueueTier;
use MediCareMini\Domain\Exception\BookingException;
use MediCareMini\Domain\Exception\HttpException;
use MediCareMini\Domain\Exception\ValidationException;
use MediCareMini\Domain\ValueObject\BookingReference;
use MediCareMini\Domain\ValueObject\PhoneNumber;
use MediCareMini\Infrastructure\Persistence\AppointmentRepository;
use MediCareMini\Infrastructure\Persistence\DoctorRepository;
use MediCareMini\Infrastructure\Persistence\PackageRepository;
use MediCareMini\Infrastructure\Persistence\PaymentRepository;
use MediCareMini\Infrastructure\Persistence\ServiceRepository;
use MediCareMini\Infrastructure\Persistence\SettingsRepository;
use MediCareMini\Infrastructure\Security\RateLimiter;
use MediCareMini\Infrastructure\Security\SessionManager;
use MediCareMini\Infrastructure\Support\Config;
use MediCareMini\Infrastructure\Support\Env;
use MediCareMini\Presentation\Controller\Controller;
use MediCareMini\Presentation\Http\Request;
use MediCareMini\Presentation\Http\Response;
use MediCareMini\Presentation\View\View;

/**
 * The patient booking journey.
 *
 *   GET  /book                     the form
 *   GET  /api/availability         live slot counts (fetch)
 *   POST /book                     create the appointment
 *   GET  /booking/{ref}            confirmation / status
 *   GET  /booking/{ref}/pay        payment instructions + upload form
 *   POST /booking/{ref}/pay        submit proof of payment
 *   GET|POST /my-booking           reference + phone lookup
 *   POST /booking/{ref}/cancel     patient-initiated cancellation
 *
 * Everything under /booking/{ref} is reachable with the reference alone,
 * which is why references are random and unguessable. Actions that change
 * anything additionally require the phone number.
 */
final class BookingController extends Controller
{
    public function __construct(
        View $view,
        SessionManager $session,
        Config $config,
        private readonly BookingService $booking,
        private readonly PaymentService $paymentService,
        private readonly AppointmentRepository $appointments,
        private readonly PaymentRepository $payments,
        private readonly ServiceRepository $services,
        private readonly PackageRepository $packages,
        private readonly DoctorRepository $doctors,
        private readonly SettingsRepository $settings,
        private readonly RateLimiter $limiter,
        private readonly SeoService $seo,
    ) {
        parent::__construct($view, $session, $config);
    }

    // -----------------------------------------------------------------
    //  Booking form
    // -----------------------------------------------------------------

    public function form(Request $request): Response
    {
        $locale = $this->currentLocale();

        // Deep links from a service card or package tile pre-select the
        // subject, so the patient does not re-choose what they just clicked.
        $preselectedService = $request->nullableInt('service');
        $preselectedPackage = $request->nullableInt('package');
        $preselectedDoctor  = $request->nullableInt('doctor');

        return $this->render('public/booking-form', [
            'services'        => $this->services->publicList(),
            'packages'        => $this->packages->publicList(),
            'doctors'         => $this->doctors->bookableList(),
            'paymentMethods'  => $this->payments->activeMethods(),
            'settings'        => $this->settings,
            'calendar'        => $this->booking->calendar($preselectedDoctor),
            'surchargeLabel'  => $this->booking->quoteFor(null, null, null, QueueTier::STANDARD) !== null
                ? $this->surchargeLabel()
                : '20%',
            'preselected'     => [
                'service' => $preselectedService,
                'package' => $preselectedPackage,
                'doctor'  => $preselectedDoctor,
            ],
            'minDate'         => (new \DateTimeImmutable('now', $this->config->timezone))
                ->modify('+' . $this->config->bookingLeadHours() . ' hours')->format('Y-m-d'),
            'maxDate'         => (new \DateTimeImmutable('now', $this->config->timezone))
                ->modify('+' . $this->config->bookingHorizonDays() . ' days')->format('Y-m-d'),
            'meta'            => [
                'title'       => $this->seo->title($this->view->translator->get('booking.title'), $locale),
                'description' => $this->view->translator->get('booking.lead'),
                'canonical'   => $this->config->url('book'),
            ],
        ]);
    }

    private function surchargeLabel(): string
    {
        $rate = $this->settings->float('express_surcharge_rate', $this->config->expressSurchargeRate());

        return rtrim(rtrim(number_format($rate * 100, 1, '.', ''), '0'), '.') . '%';
    }

    /**
     * Live availability for the chosen date and doctor.
     *
     * Called by the form as the patient picks a date, so full slots are
     * greyed out before they are selected rather than after submission.
     */
    public function availability(Request $request): Response
    {
        $date = $request->input('date');

        if ($date === null || preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1) {
            return Response::json(['ok' => false, 'error' => 'A valid date is required.'], 422);
        }

        $doctorId = $request->nullableInt('doctor_id');

        $availability = $this->booking->availability($date, $doctorId);

        $slots = [];

        foreach ($availability as $slot => $remaining) {
            $slots[] = [
                'value'     => $slot,
                'remaining' => $remaining,
                'available' => $remaining > 0,
            ];
        }

        return Response::json([
            'ok'    => true,
            'date'  => $date,
            'slots' => $slots,
        ])->withHeader('Cache-Control', 'no-store');
    }

    /** Live price quote as the patient changes service, doctor or tier. */
    public function quote(Request $request): Response
    {
        $tier = QueueTier::tryFrom($request->input('queue_tier') ?? 'standard') ?? QueueTier::STANDARD;

        $quote = $this->booking->quoteFor(
            $request->nullableInt('service_id'),
            $request->nullableInt('package_id'),
            $request->nullableInt('doctor_id'),
            $tier,
        );

        $t = $this->view->translator;

        return Response::json([
            'ok'        => true,
            'base'      => $t->money($quote->base),
            'surcharge' => $t->money($quote->surcharge),
            'total'     => $t->money($quote->total),
            'deposit'   => $t->money($quote->depositDue),
            'hasSurcharge'    => $quote->hasSurcharge(),
            'requiresDeposit' => $quote->requiresDeposit,
            'isFree'          => $quote->isFree(),
        ])->withHeader('Cache-Control', 'no-store');
    }

    // -----------------------------------------------------------------
    //  Create
    // -----------------------------------------------------------------

    public function store(Request $request): Response
    {
        $ip = $request->ip($this->config->trustProxy());

        // Keyed on IP: an anonymous form has no better identity, and this is
        // what stops a script filling the calendar with junk bookings.
        $bucket = 'book:' . $ip;

        if (!$this->limiter->attempt($bucket, Env::int('RATE_BOOKING_PER_HOUR', 5), 3600)) {
            $message = $this->view->translator->get('validation.rate_limited');

            if ($request->wantsJson()) {
                return Response::json(['ok' => false, 'message' => $message], 429);
            }

            return $this->redirectWithError('book', $message);
        }

        try {
            $bookingRequest = BookingRequest::fromRequest(
                $request,
                $this->currentLocale(),
                BookingSource::WEB,
                null,
                $this->config->trustProxy(),
            );

            $appointment = $this->booking->book($bookingRequest);
        } catch (ValidationException $e) {
            return $this->validationResponse($e, $request, 'book');
        } catch (BookingException $e) {
            // A business refusal (slot full, too soon) is the patient's to
            // resolve by choosing differently, so the message is shown as-is.
            if ($request->wantsJson()) {
                return Response::json(['ok' => false, 'message' => $e->getMessage()], 409);
            }

            $this->session->flashInput($request->body);

            return $this->redirectWithError('book', $e->getMessage());
        }

        $redirectTo = $appointment->totalAmount->isPositive()
            ? 'booking/' . $appointment->reference->value . '/pay'
            : 'booking/' . $appointment->reference->value;

        if ($request->wantsJson()) {
            return Response::json([
                'ok'        => true,
                'reference' => $appointment->reference->value,
                'redirect'  => $this->config->url($redirectTo),
            ], 201);
        }

        return $this->redirect($redirectTo);
    }

    // -----------------------------------------------------------------
    //  Confirmation / status
    // -----------------------------------------------------------------

    public function show(Request $request): Response
    {
        $appointment = $this->findByReference($request->routeString('reference'));

        $locale = $this->currentLocale();

        return $this->render('public/booking-status', [
            'appointment' => $appointment,
            'payments'    => $this->payments->forAppointment($appointment->id),
            'settings'    => $this->settings,
            'canCancel'   => $appointment->isPatientCancellable($this->config->timezone),
            'meta'        => [
                'title'   => $this->seo->title(
                    $this->view->translator->get('confirmation.title'),
                    $locale,
                ),
                // A booking page must never be indexed; it contains PHI.
                'noindex' => true,
            ],
        ]);
    }

    // -----------------------------------------------------------------
    //  Payment
    // -----------------------------------------------------------------

    public function paymentPage(Request $request): Response
    {
        $appointment = $this->findByReference($request->routeString('reference'));

        $existing = $this->payments->forAppointment($appointment->id);

        return $this->render('public/booking-payment', [
            'appointment'    => $appointment,
            'paymentMethods' => $this->payments->activeMethods(),
            'payments'       => $existing,
            'settings'       => $this->settings,
            'canSubmit'      => $appointment->paymentStatus->acceptsProof(),
            'meta'           => [
                'title'   => $this->seo->title(
                    $this->view->translator->get('payment.title'),
                    $this->currentLocale(),
                ),
                'noindex' => true,
            ],
        ]);
    }

    public function submitProof(Request $request): Response
    {
        $reference   = $request->routeString('reference');
        $appointment = $this->findByReference($reference);

        $bucket = 'proof:' . $request->ip($this->config->trustProxy());

        if (!$this->limiter->attempt($bucket, Env::int('RATE_PROOF_PER_HOUR', 10), 3600)) {
            return $this->redirectWithError(
                'booking/' . $reference . '/pay',
                $this->view->translator->get('validation.rate_limited'),
            );
        }

        try {
            $this->paymentService->submitProof($appointment, $request, $this->config->trustProxy());
        } catch (ValidationException $e) {
            return $this->redirectWithValidation('booking/' . $reference . '/pay', $e, $request);
        } catch (BookingException $e) {
            return $this->redirectWithError('booking/' . $reference . '/pay', $e->getMessage());
        }

        return $this->redirectWithSuccess(
            'booking/' . $reference,
            $this->view->translator->get('payment.submitted_body'),
        );
    }

    // -----------------------------------------------------------------
    //  Lookup and cancellation
    // -----------------------------------------------------------------

    public function lookupForm(Request $request): Response
    {
        return $this->render('public/booking-lookup', [
            'settings' => $this->settings,
            'meta'     => [
                'title'   => $this->seo->title(
                    $this->view->translator->get('lookup.title'),
                    $this->currentLocale(),
                ),
                'noindex' => true,
            ],
        ]);
    }

    /**
     * Find a booking from a reference plus the phone number used to make it.
     *
     * Both are required. The reference alone is unguessable, but a forwarded
     * confirmation email would otherwise expose the booking to whoever
     * received it.
     */
    public function lookup(Request $request): Response
    {
        $reference = BookingReference::tryFrom($request->input('reference') ?? '');
        $phone     = PhoneNumber::tryFrom($request->input('phone') ?? '');

        $notFound = $this->view->translator->get('lookup.not_found');

        if ($reference === null || $phone === null) {
            return $this->redirectWithError('my-booking', $notFound);
        }

        // Throttled: this endpoint is the one place a reference can be
        // guessed at, so it must not be cheap to probe.
        $bucket = 'lookup:' . $request->ip($this->config->trustProxy());

        if (!$this->limiter->attempt($bucket, 15, 3600)) {
            return $this->redirectWithError('my-booking', $this->view->translator->get('validation.rate_limited'));
        }

        $appointment = $this->appointments->findForPatient($reference, $phone->e164);

        if ($appointment === null) {
            return $this->redirectWithError('my-booking', $notFound);
        }

        return $this->redirect('booking/' . $appointment->reference->value);
    }

    public function cancel(Request $request): Response
    {
        $reference = BookingReference::tryFrom($request->routeString('reference'));
        $phone     = PhoneNumber::tryFrom($request->input('phone') ?? '');

        if ($reference === null || $phone === null) {
            return $this->redirectWithError(
                'booking/' . $request->routeString('reference'),
                $this->view->translator->get('lookup.not_found'),
            );
        }

        try {
            $this->booking->cancelAsPatient($reference, $phone->e164, $request->input('reason'));
        } catch (BookingException $e) {
            return $this->redirectWithError('booking/' . $reference->value, $e->getMessage());
        }

        return $this->redirectWithSuccess(
            'booking/' . $reference->value,
            $this->view->translator->get('lookup.cancelled'),
        );
    }

    /**
     * Resolve a reference from the URL, 404ing on anything invalid.
     *
     * A malformed and a non-existent reference produce the identical
     * response, so the endpoint cannot be used to test which references are
     * real.
     */
    private function findByReference(string $raw): \MediCareMini\Domain\Entity\Appointment
    {
        $reference = BookingReference::tryFrom($raw);

        if ($reference === null) {
            throw HttpException::notFound();
        }

        $appointment = $this->appointments->findByReference($reference);

        if ($appointment === null) {
            throw HttpException::notFound();
        }

        return $appointment;
    }
}
