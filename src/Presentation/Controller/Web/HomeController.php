<?php

declare(strict_types=1);

namespace Aster\Presentation\Controller\Web;

use Aster\Application\Service\SeoService;
use Aster\Infrastructure\Persistence\ArticleRepository;
use Aster\Infrastructure\Persistence\DoctorRepository;
use Aster\Infrastructure\Persistence\FacilityRepository;
use Aster\Infrastructure\Persistence\PackageRepository;
use Aster\Infrastructure\Persistence\ServiceRepository;
use Aster\Infrastructure\Persistence\SettingsRepository;
use Aster\Infrastructure\Security\SessionManager;
use Aster\Infrastructure\Support\Config;
use Aster\Presentation\Controller\Controller;
use Aster\Presentation\Http\Request;
use Aster\Presentation\Http\Response;
use Aster\Presentation\View\View;

/**
 * The public homepage.
 *
 * Composes every section of the marketing site from live CMS data - the
 * static arrays in the prototype are gone, so the clinic can change services,
 * doctors, packages and articles without touching code.
 */
final class HomeController extends Controller
{
    public function __construct(
        View $view,
        SessionManager $session,
        Config $config,
        private readonly ServiceRepository $services,
        private readonly DoctorRepository $doctors,
        private readonly PackageRepository $packages,
        private readonly FacilityRepository $facilities,
        private readonly ArticleRepository $articles,
        private readonly SettingsRepository $settings,
        private readonly SeoService $seo,
    ) {
        parent::__construct($view, $session, $config);
    }

    public function index(Request $request): Response
    {
        $locale = $this->currentLocale();

        $faqs = $this->faqEntries();

        // Both the organisation and the FAQ entity go in one JSON-LD block;
        // the FAQ is what earns the expandable treatment in search results.
        $structuredData = $this->seo->encode(
            $this->seo->organisationSchema($locale),
            $this->seo->faqSchema($faqs),
        );

        return $this->render('public/home', [
            'services'       => $this->services->publicList(),
            'doctors'        => $this->doctors->publicList(),
            'packages'       => $this->packages->publicList(),
            'facilities'     => $this->facilities->publicList(),
            'articles'       => $this->articles->publishedList(limit: 3),
            'settings'       => $this->settings,
            'faqs'           => $faqs,
            'structuredData' => $structuredData,
            'meta'           => [
                'title'       => $this->seo->title(
                    $locale->value === 'am'
                        ? $this->settings->string('tagline_am')
                        : $this->settings->string('tagline'),
                    $locale,
                ),
                'description' => $this->view->translator->get('hero.lead'),
                'canonical'   => $this->config->url(''),
            ],
            'alternates' => $this->seo->alternates('/', $locale)['alternates'],
        ]);
    }

    /**
     * FAQ content, pulled from the translation dictionary so both languages
     * stay in step and the schema matches what is rendered.
     *
     * @return list<array{question:string, answer:string}>
     */
    private function faqEntries(): array
    {
        $t     = $this->view->translator;
        $items = [];

        for ($i = 1; $i <= 5; $i++) {
            $question = $t->get("faq.q{$i}");
            $answer   = $t->get("faq.a{$i}");

            // get() returns the key itself when missing, so this skips any
            // entry that has not been written rather than rendering "faq.q5".
            if ($question !== "faq.q{$i}" && $answer !== "faq.a{$i}") {
                $items[] = ['question' => $question, 'answer' => $answer];
            }
        }

        return $items;
    }

    /** Privacy policy - required wherever patient data is collected. */
    public function privacy(Request $request): Response
    {
        return $this->render('public/privacy', [
            'settings' => $this->settings,
            'meta'     => [
                'title'       => $this->seo->title('Privacy Policy', $this->currentLocale()),
                'description' => 'How ' . $this->view->brand->businessName . ' collects, uses and protects patient information.',
                'canonical'   => $this->config->url('privacy'),
                'noindex'     => false,
            ],
        ]);
    }

    public function terms(Request $request): Response
    {
        return $this->render('public/terms', [
            'settings' => $this->settings,
            'meta'     => [
                'title'       => $this->seo->title('Terms of Service', $this->currentLocale()),
                'description' => 'Terms governing the use of the ' . $this->view->brand->businessName . ' website and booking service.',
                'canonical'   => $this->config->url('terms'),
            ],
        ]);
    }
}
