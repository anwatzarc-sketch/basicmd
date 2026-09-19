<?php

declare(strict_types=1);

namespace MediCareMini\Application\Service;

use MediCareMini\Domain\Enum\Locale;
use MediCareMini\Infrastructure\Persistence\ArticleRepository;
use MediCareMini\Infrastructure\Persistence\DoctorRepository;
use MediCareMini\Infrastructure\Persistence\FacilityRepository;
use MediCareMini\Infrastructure\Persistence\PackageRepository;
use MediCareMini\Infrastructure\Persistence\ServiceRepository;
use MediCareMini\Infrastructure\Persistence\SettingsRepository;
use MediCareMini\Infrastructure\Support\BrandResolver;
use MediCareMini\Infrastructure\Support\Config;
use MediCareMini\Infrastructure\Support\Translator;

/**
 * A complete, plain-text description of this site, built from live data.
 *
 * This is the assistant's entire knowledge of the clinic. It is generated from
 * the same repositories the public pages render from, which is the whole point:
 * a hand-written knowledge base goes stale the first time staff add a service
 * or a doctor leaves, and a stale answer from a clinic's own website is worse
 * than no answer. Change the catalogue in the admin and the assistant knows.
 *
 * Deliberately NOT included: anything patient-specific. No appointments, no
 * payments, no patient records. The assistant is a guide to the public site,
 * so a booking lookup is a link to /my-booking, never a database read - that
 * would put one visitor's details within reach of another's question.
 */
final class SiteGuide
{
    /** @var array<string, string> locale value => rendered guide */
    private array $cache = [];

    public function __construct(
        private readonly ServiceRepository $services,
        private readonly DoctorRepository $doctors,
        private readonly PackageRepository $packages,
        private readonly FacilityRepository $facilities,
        private readonly ArticleRepository $articles,
        private readonly SettingsRepository $settings,
        private readonly BrandResolver $brand,
        private readonly Translator $translator,
        private readonly Config $config,
    ) {
    }

    public function forLocale(Locale $locale): string
    {
        return $this->cache[$locale->value] ??= $this->build($locale);
    }

    private function build(Locale $locale): string
    {
        $brand = $this->brand->resolve();
        $name  = $this->settings->localized('clinic_name', $locale, $brand->businessName) ?: $brand->businessName;

        $sections = [
            $this->clinicSection($locale, $name, $brand->mainCity),
            $this->servicesSection($locale),
            $this->packagesSection($locale),
            $this->doctorsSection($locale),
            $this->facilitiesSection($locale),
            $this->articlesSection($locale),
            $this->howToSection(),
            $this->pageMapSection(),
        ];

        return implode("\n\n", array_filter($sections));
    }

    private function clinicSection(Locale $locale, string $name, string $city): string
    {
        $lines = ['## The clinic', 'Name: ' . $name, 'City: ' . $city];

        foreach ([
            'Address'        => $this->settings->localized('address', $locale),
            'Opening hours'  => $this->settings->localized('operating_hours', $locale),
            'Main phone'     => $this->settings->string('phone_primary'),
            'Emergency line' => $this->settings->string('phone_emergency'),
            'Email'          => $this->settings->string('email_public'),
        ] as $label => $value) {
            if ($value !== '') {
                $lines[] = $label . ': ' . $value;
            }
        }

        return implode("\n", $lines);
    }

    private function servicesSection(Locale $locale): string
    {
        $rows = [];

        foreach ($this->services->publicList() as $service) {
            $row = '- ' . $service->title($locale);

            if ($service->hasPrice()) {
                $row .= ' (' . $this->translator->money($service->price) . ')';
            }

            $summary = $service->summary($locale);

            if ($summary !== null && $summary !== '') {
                $row .= ' - ' . $this->trim($summary, 160);
            }

            $row .= ' [/services/' . $service->slug . ']';
            $rows[] = $row;
        }

        return $rows === [] ? '' : "## Services offered\n" . implode("\n", $rows);
    }

    private function packagesSection(Locale $locale): string
    {
        $rows = [];

        foreach ($this->packages->publicList() as $package) {
            $row = '- ' . $package->name($locale) . ' (' . $this->translator->money($package->price) . ')';

            $summary = $package->summary($locale);

            if ($summary !== null && $summary !== '') {
                $row .= ' - ' . $this->trim($summary, 160);
            }

            $items = $package->itemList($locale);

            if ($items !== []) {
                $row .= ' Includes: ' . implode('; ', array_slice($items, 0, 8)) . '.';
            }

            if ($package->requiresDeposit()) {
                $row .= ' Reservable with a ' . $package->depositPercentLabel() . ' deposit of '
                    . $this->translator->money($package->depositAmount()) . '.';
            }

            $rows[] = $row;
        }

        return $rows === [] ? '' : "## Health packages\n" . implode("\n", $rows);
    }

    private function doctorsSection(Locale $locale): string
    {
        $rows = [];

        foreach ($this->doctors->publicList() as $doctor) {
            $row = '- ' . $doctor->name($locale) . ' - ' . $doctor->specialtyLabel($locale);

            if ($doctor->credentials !== null && $doctor->credentials !== '') {
                $row .= ' (' . $doctor->credentials . ')';
            }

            if ($doctor->experienceYears > 0) {
                $row .= ', ' . $doctor->experienceYears . ' years experience';
            }

            $row .= $doctor->isBookable() ? ', accepting appointments' : ', not currently accepting appointments';
            $row .= ' [/doctors/' . $doctor->slug . ']';
            $rows[] = $row;
        }

        return $rows === [] ? '' : "## Doctors\n" . implode("\n", $rows);
    }

    private function facilitiesSection(Locale $locale): string
    {
        $rows = [];

        foreach ($this->facilities->publicList() as $facility) {
            $rows[] = '- ' . $facility->title($locale) . ' - ' . $this->trim((string) $facility->summary($locale), 140);
        }

        return $rows === [] ? '' : "## Facilities\n" . implode("\n", $rows);
    }

    private function articlesSection(Locale $locale): string
    {
        $rows = [];

        foreach ($this->articles->publishedList(limit: 12) as $article) {
            $rows[] = '- ' . $article->heading($locale) . ' [/health/' . $article->slug . ']';
        }

        return $rows === [] ? '' : "## Health articles on the site\n" . implode("\n", $rows);
    }

    /**
     * The procedural knowledge a visitor actually asks for, and the part that
     * cannot be derived from a table.
     */
    private function howToSection(): string
    {
        return <<<'TEXT'
        ## How things work here
        - Booking: choose a service, optionally a doctor, then a date and a time slot at /book. A booking reference is issued immediately and emailed when an address is given.
        - Time slots are fixed windows: 08:00-10:00, 10:00-12:00, 14:00-16:00 and 16:00-18:00.
        - Express queue: an optional surcharge that places the patient earlier within their chosen slot.
        - Walk-ins are accepted for general consultations, but booking ahead shortens the wait.
        - Payment: health packages may require a deposit. After booking, bank and mobile-money details are shown at the payment step. The patient transfers the amount, uploads a photo of the receipt, and finance confirms it within one working day.
        - Checking or cancelling a booking: /my-booking, using the booking reference and the phone number the booking was made with. The assistant cannot look this up - always send the visitor to that page.
        - Confirmation: an email with the booking reference arrives immediately; staff confirm the exact time by phone or email.
        TEXT;
    }

    private function pageMapSection(): string
    {
        $base = rtrim($this->config->appUrl, '/');

        return "## Pages on this site (link to these, relative paths are fine)\n"
            . "- / home\n"
            . "- /services and /services/{slug} individual services\n"
            . "- /doctors and /doctors/{slug} individual doctors\n"
            . "- /packages health packages\n"
            . "- /health and /health/{slug} health articles\n"
            . "- /locations address, map and directions\n"
            . "- /contact contact form and phone numbers\n"
            . "- /book start a booking\n"
            . "- /my-booking check or cancel an existing booking\n"
            . "- /privacy and /terms\n"
            . "Site base URL: " . $base;
    }

    private function trim(string $value, int $length): string
    {
        $clean = trim(preg_replace('/\s+/', ' ', strip_tags($value)) ?? '');

        return mb_strlen($clean) <= $length ? $clean : mb_substr($clean, 0, $length - 1) . '…';
    }
}
