<?php

declare(strict_types=1);

namespace Aster\Application\Service;

use Aster\Domain\Entity\Article;
use Aster\Domain\Entity\Doctor;
use Aster\Domain\Entity\MedicalService;
use Aster\Domain\Enum\Locale;
use Aster\Infrastructure\Persistence\SettingsRepository;
use Aster\Infrastructure\Support\Config;
use Aster\Infrastructure\Support\Translator;
use Aster\Presentation\View\HtmlSanitiser;

/**
 * Structured data and page metadata.
 *
 * This is the lead-acquisition engine's technical half. Google renders
 * MedicalClinic, Physician and MedicalWebPage entities with richer results
 * than a generic page - opening hours and location in the local pack, an
 * author and review date on health content. For queries like "cardiologist
 * Bole Addis Ababa" that difference is the click.
 *
 * JSON-LD rather than microdata: it lives in one block, needs no changes to
 * the markup, and is what Google recommends.
 */
final readonly class SeoService
{
    public function __construct(
        private Config $config,
        private SettingsRepository $settings,
        private Translator $translator,
    ) {
    }

    private function clinicName(Locale $locale): string
    {
        return $locale === Locale::AM
            ? $this->settings->string('clinic_name_am', $this->settings->string('clinic_name', $this->config->appName))
            : $this->settings->string('clinic_name', $this->config->appName);
    }

    /**
     * The organisation entity, emitted on every page.
     *
     * MedicalClinic is a more specific type than LocalBusiness and unlocks
     * the health-specific result treatments.
     *
     * @return array<string, mixed>
     */
    public function organisationSchema(Locale $locale = Locale::EN): array
    {
        $phone     = $this->settings->string('phone_primary');
        $emergency = $this->settings->string('phone_emergency');

        $schema = [
            '@context'    => 'https://schema.org',
            '@type'       => 'MedicalClinic',
            '@id'         => $this->config->url('#clinic'),
            'name'        => $this->clinicName($locale),
            'url'         => $this->config->appUrl,
            'description' => $locale === Locale::AM
                ? $this->settings->string('tagline_am')
                : $this->settings->string('tagline'),
            'address'     => [
                '@type'           => 'PostalAddress',
                'streetAddress'   => $locale === Locale::AM
                    ? $this->settings->string('address_am', $this->settings->string('address'))
                    : $this->settings->string('address'),
                'addressLocality' => 'Addis Ababa',
                'addressRegion'   => 'Addis Ababa',
                'addressCountry'  => 'ET',
            ],
            'geo' => [
                '@type'     => 'GeoCoordinates',
                'latitude'  => $this->settings->string('map_latitude', '8.9969'),
                'longitude' => $this->settings->string('map_longitude', '38.7869'),
            ],
            // Mon-Sat 08:00-18:00, matching the operating_hours setting.
            'openingHoursSpecification' => [[
                '@type'     => 'OpeningHoursSpecification',
                'dayOfWeek' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'],
                'opens'     => '08:00',
                'closes'    => '18:00',
            ]],
            'availableLanguage' => [
                ['@type' => 'Language', 'name' => 'English', 'alternateName' => 'en'],
                ['@type' => 'Language', 'name' => 'Amharic', 'alternateName' => 'am'],
            ],
            'currenciesAccepted' => 'ETB',
            'paymentAccepted'    => 'Cash, Bank Transfer, Telebirr, CBE Birr',
            'isAcceptingNewPatients' => true,
        ];

        if ($phone !== '') {
            $schema['telephone'] = $phone;

            $schema['contactPoint'] = [[
                '@type'       => 'ContactPoint',
                'telephone'   => $phone,
                'contactType' => 'reservations',
                'areaServed'  => 'ET',
                'availableLanguage' => ['en', 'am'],
            ]];

            if ($emergency !== '') {
                $schema['contactPoint'][] = [
                    '@type'             => 'ContactPoint',
                    'telephone'         => $emergency,
                    'contactType'       => 'emergency',
                    'areaServed'        => 'ET',
                    'availableLanguage' => ['en', 'am'],
                ];
            }
        }

        $email = $this->settings->string('email_public');

        if ($email !== '') {
            $schema['email'] = $email;
        }

        $social = array_values(array_filter([
            $this->settings->get('facebook_url'),
            $this->settings->get('telegram_url'),
            $this->settings->get('instagram_url'),
            $this->settings->get('linkedin_url'),
        ]));

        if ($social !== []) {
            $schema['sameAs'] = $social;
        }

        return $schema;
    }

    /**
     * Article schema, typed per the article's own schema_type.
     *
     * `reviewedBy` and `lastReviewed` are what signal clinical
     * trustworthiness for health content; they are emitted only when a
     * reviewer is actually recorded, because claiming a review that did not
     * happen is both a policy violation and dishonest.
     *
     * @return array<string, mixed>
     */
    public function articleSchema(Article $article, Locale $locale = Locale::EN): array
    {
        $url = $this->config->url('health/' . $article->slug);

        $schema = [
            '@context'         => 'https://schema.org',
            '@type'            => $article->schemaType->value,
            '@id'              => $url . '#article',
            'url'              => $url,
            'name'             => $article->heading($locale),
            'headline'         => $article->heading($locale),
            'description'      => $article->seoDescription($locale),
            'inLanguage'       => $locale->htmlLang(),
            'datePublished'    => $article->publishedAt?->format(DATE_ATOM),
            'dateModified'     => $article->updatedAt->format(DATE_ATOM),
            'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => $url],
            'publisher'        => ['@id' => $this->config->url('#clinic')],
            'isPartOf'         => ['@id' => $this->config->url('#clinic')],
        ];

        if ($article->authorName !== null) {
            $schema['author'] = [
                '@type'     => 'Person',
                'name'      => $article->authorName,
                'jobTitle'  => $article->authorSpecialty ?? 'Physician',
                'worksFor'  => ['@id' => $this->config->url('#clinic')],
            ];
        }

        if ($article->schemaType->supportsClinicalReview() && $article->reviewerName !== null) {
            $schema['reviewedBy'] = [
                '@type' => 'Person',
                'name'  => $article->reviewerName,
            ];

            $schema['lastReviewed'] = $article->updatedAt->format('Y-m-d');
        }

        if ($article->coverImage !== null) {
            $schema['image'] = $this->config->url('media/' . ltrim($article->coverImage, '/'));
        }

        $wordCount = str_word_count(HtmlSanitiser::toText($article->body($locale) ?? ''));

        if ($wordCount > 0) {
            $schema['wordCount'] = $wordCount;
        }

        return $schema;
    }

    /**
     * Physician entity for a doctor profile.
     *
     * @return array<string, mixed>
     */
    public function doctorSchema(Doctor $doctor, Locale $locale = Locale::EN): array
    {
        $url = $this->config->url('doctors/' . $doctor->slug);

        return array_filter([
            '@context'          => 'https://schema.org',
            '@type'             => 'Physician',
            '@id'               => $url . '#physician',
            'url'               => $url,
            'name'              => $doctor->name($locale),
            'medicalSpecialty'  => $doctor->specialtyLabel($locale),
            'description'       => $doctor->biography($locale),
            'worksFor'          => ['@id' => $this->config->url('#clinic')],
            'image'             => $doctor->photoPath !== null
                ? $this->config->url('media/' . ltrim($doctor->photoPath, '/'))
                : null,
        ], static fn (mixed $v): bool => $v !== null);
    }

    /**
     * MedicalTest / service offering with its price.
     *
     * @return array<string, mixed>
     */
    public function serviceSchema(MedicalService $service, Locale $locale = Locale::EN): array
    {
        $schema = [
            '@context'    => 'https://schema.org',
            '@type'       => 'MedicalProcedure',
            'name'        => $service->title($locale),
            'description' => $service->summary($locale),
            'provider'    => ['@id' => $this->config->url('#clinic')],
        ];

        if ($service->hasPrice()) {
            $schema['offers'] = [
                '@type'         => 'Offer',
                'price'         => $service->price->toDatabase(),
                'priceCurrency' => 'ETB',
                'availability'  => 'https://schema.org/InStock',
            ];
        }

        return $schema;
    }

    /**
     * FAQPage schema from the site's FAQ block.
     *
     * Earns the expandable FAQ treatment directly in search results, which
     * takes up more vertical space than a plain blue link.
     *
     * @param list<array{question:string, answer:string}> $faqs
     * @return array<string, mixed>
     */
    public function faqSchema(array $faqs): array
    {
        return [
            '@context'   => 'https://schema.org',
            '@type'      => 'FAQPage',
            'mainEntity' => array_map(static fn (array $faq): array => [
                '@type'          => 'Question',
                'name'           => $faq['question'],
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => $faq['answer']],
            ], $faqs),
        ];
    }

    /**
     * Breadcrumb trail.
     *
     * @param list<array{name:string, url:string}> $crumbs
     * @return array<string, mixed>
     */
    public function breadcrumbSchema(array $crumbs): array
    {
        return [
            '@context'        => 'https://schema.org',
            '@type'           => 'BreadcrumbList',
            'itemListElement' => array_map(
                static fn (int $i, array $crumb): array => [
                    '@type'    => 'ListItem',
                    'position' => $i + 1,
                    'name'     => $crumb['name'],
                    'item'     => $crumb['url'],
                ],
                array_keys($crumbs),
                $crumbs,
            ),
        ];
    }

    /** Encode one or more schemas as a script tag body. */
    public function encode(array ...$schemas): string
    {
        $payload = count($schemas) === 1 ? $schemas[0] : $schemas;

        $json = json_encode(
            $payload,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT,
        );

        // </script> inside a JSON string would terminate the block early;
        // escaping the slash keeps it inert.
        return $json === false ? '{}' : str_replace('</', '<\/', $json);
    }

    /**
     * Canonical and hreflang links.
     *
     * hreflang tells Google the English and Amharic pages are translations
     * rather than duplicates, so neither is filtered as thin content.
     *
     * @return array{canonical:string, alternates: array<string, string>}
     */
    public function alternates(string $path, Locale $current): array
    {
        $clean = '/' . ltrim($path, '/');

        return [
            'canonical'  => $this->config->url(ltrim($clean, '/')),
            'alternates' => [
                'en'        => $this->config->url(ltrim($clean, '/')) . '?lang=en',
                'am-ET'     => $this->config->url(ltrim($clean, '/')) . '?lang=am',
                'x-default' => $this->config->url(ltrim($clean, '/')),
            ],
        ];
    }

    /** Page title with the clinic name appended. */
    public function title(string $pageTitle, Locale $locale = Locale::EN): string
    {
        $clinic = $this->clinicName($locale);

        return $pageTitle === '' ? $clinic : $pageTitle . ' | ' . $clinic;
    }
}
