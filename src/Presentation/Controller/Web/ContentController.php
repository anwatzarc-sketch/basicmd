<?php

declare(strict_types=1);

namespace Aster\Presentation\Controller\Web;

use Aster\Application\Service\SeoService;
use Aster\Domain\Exception\HttpException;
use Aster\Infrastructure\Persistence\ArticleRepository;
use Aster\Infrastructure\Persistence\DoctorRepository;
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
 * Public catalogue pages: services, doctors, packages and the Knowledge Hub.
 *
 * Each listing supports server-side search and filtering. Server-side rather
 * than client-side because these are the pages that need to rank: a filtered
 * view has to be a real URL that Google can crawl, not a JavaScript state.
 */
final class ContentController extends Controller
{
    private const int ARTICLES_PER_PAGE = 9;

    public function __construct(
        View $view,
        SessionManager $session,
        Config $config,
        private readonly ServiceRepository $services,
        private readonly DoctorRepository $doctors,
        private readonly PackageRepository $packages,
        private readonly ArticleRepository $articles,
        private readonly SettingsRepository $settings,
        private readonly SeoService $seo,
    ) {
        parent::__construct($view, $session, $config);
    }

    // -----------------------------------------------------------------
    //  Services
    // -----------------------------------------------------------------

    public function services(Request $request): Response
    {
        $category = $request->input('category');
        $search   = $request->input('q');
        $locale   = $this->currentLocale();

        return $this->render('public/services', [
            'services'   => $this->services->publicList($category, $search),
            'categories' => $this->services->categoryCounts(),
            'filters'    => ['category' => $category, 'q' => $search],
            'settings'   => $this->settings,
            'meta'       => [
                'title'       => $this->seo->title(
                    $this->view->translator->get('services.title'),
                    $locale,
                ),
                'description' => 'Cardiology, pediatrics, internal medicine, laboratory and imaging services at '
                    . $this->view->brand->businessName . ', ' . $this->view->brand->mainCity . '.',
                'canonical'   => $this->config->url('services'),
            ],
        ]);
    }

    public function serviceDetail(Request $request): Response
    {
        $service = $this->services->findBySlug($request->routeString('slug'));

        if ($service === null || !$service->isActive) {
            throw HttpException::notFound();
        }

        $locale = $this->currentLocale();

        return $this->render('public/service-detail', [
            'service'  => $service,
            'doctors'  => $this->doctors->publicList($service->name),
            'related'  => array_slice($this->services->publicList($service->category->value), 0, 4),
            'settings' => $this->settings,
            'structuredData' => $this->seo->encode(
                $this->seo->serviceSchema($service, $locale),
                $this->seo->breadcrumbSchema([
                    ['name' => 'Home',     'url' => $this->config->url('')],
                    ['name' => 'Services', 'url' => $this->config->url('services')],
                    ['name' => $service->title($locale), 'url' => $this->config->url('services/' . $service->slug)],
                ]),
            ),
            'meta' => [
                'title'       => $this->seo->title($service->title($locale), $locale),
                'description' => $service->summary($locale) ?? '',
                'canonical'   => $this->config->url('services/' . $service->slug),
            ],
        ]);
    }

    // -----------------------------------------------------------------
    //  Doctors
    // -----------------------------------------------------------------

    public function doctors(Request $request): Response
    {
        $specialty = $request->input('specialty');
        $search    = $request->input('q');

        return $this->render('public/doctors', [
            'doctors'     => $this->doctors->publicList($specialty, $search),
            'specialties' => $this->doctors->specialties(),
            'filters'     => ['specialty' => $specialty, 'q' => $search],
            'settings'    => $this->settings,
            'meta'        => [
                'title'       => $this->seo->title(
                    $this->view->translator->get('doctors.title'),
                    $this->currentLocale(),
                ),
                'description' => 'Meet the specialists at ' . $this->view->brand->businessName . ' in '
                    . $this->view->brand->mainCity . ' - cardiology, pediatrics, internal medicine, gynecology, orthopedics and dermatology.',
                'canonical'   => $this->config->url('doctors'),
            ],
        ]);
    }

    public function doctorDetail(Request $request): Response
    {
        $doctor = $this->doctors->findBySlug($request->routeString('slug'));

        if ($doctor === null || !$doctor->status->isPubliclyVisible()) {
            throw HttpException::notFound();
        }

        $locale = $this->currentLocale();

        return $this->render('public/doctor-detail', [
            'doctor'   => $doctor,
            'articles' => $this->articles->publishedList(limit: 3),
            'settings' => $this->settings,
            'structuredData' => $this->seo->encode(
                $this->seo->doctorSchema($doctor, $locale),
                $this->seo->breadcrumbSchema([
                    ['name' => 'Home',    'url' => $this->config->url('')],
                    ['name' => 'Doctors', 'url' => $this->config->url('doctors')],
                    ['name' => $doctor->name($locale), 'url' => $this->config->url('doctors/' . $doctor->slug)],
                ]),
            ),
            'meta' => [
                'title'       => $this->seo->title(
                    $doctor->name($locale) . ' - ' . $doctor->specialtyLabel($locale),
                    $locale,
                ),
                'description' => mb_substr(
                    $doctor->biography($locale) ?? ($doctor->specialtyLabel($locale) . ' at '
                        . $this->view->brand->businessName . ', ' . $this->view->brand->mainCity . '.'),
                    0,
                    155,
                ),
                'canonical'   => $this->config->url('doctors/' . $doctor->slug),
            ],
        ]);
    }

    // -----------------------------------------------------------------
    //  Health packages
    // -----------------------------------------------------------------

    public function packages(Request $request): Response
    {
        return $this->render('public/packages', [
            'packages' => $this->packages->publicList(),
            'settings' => $this->settings,
            'meta'     => [
                'title'       => $this->seo->title(
                    $this->view->translator->get('packages.title'),
                    $this->currentLocale(),
                ),
                'description' => 'Preventive health screening packages in ' . $this->view->brand->mainCity
                    . ' - Essential Check from ETB 2,500 and Executive Health from ETB 6,500.',
                'canonical'   => $this->config->url('packages'),
            ],
        ]);
    }

    // -----------------------------------------------------------------
    //  Health Knowledge Hub
    // -----------------------------------------------------------------

    public function articles(Request $request): Response
    {
        $category = $request->input('category');
        $search   = $request->input('q');

        ['page' => $page, 'offset' => $offset] = $this->paginate($request, self::ARTICLES_PER_PAGE);

        $total = $this->articles->countPublished($category, $search);

        return $this->render('public/articles', [
            'articles'   => $this->articles->publishedList($category, $search, self::ARTICLES_PER_PAGE, $offset),
            'categories' => $this->articles->categoryCounts(),
            'mostRead'   => $this->articles->mostRead(4),
            'filters'    => ['category' => $category, 'q' => $search],
            'pagination' => $this->paginationMeta($total, $page, self::ARTICLES_PER_PAGE),
            'settings'   => $this->settings,
            'meta'       => [
                'title'       => $this->seo->title(
                    $this->view->translator->get('articles.title'),
                    $this->currentLocale(),
                ),
                'description' => 'Health guidance from the clinical team at ' . $this->view->brand->businessName
                    . ', ' . $this->view->brand->mainCity . ' - prevention, family health and wellness.',
                'canonical'   => $this->config->url('health'),
            ],
        ]);
    }

    public function articleDetail(Request $request): Response
    {
        $article = $this->articles->findPublishedBySlug($request->routeString('slug'));

        if ($article === null) {
            throw HttpException::notFound();
        }

        // Fire-and-forget: an analytics counter must never delay or fail a
        // reader's page.
        $this->articles->incrementViews($article->id);

        $locale = $this->currentLocale();

        return $this->render('public/article-detail', [
            'article'  => $article,
            'related'  => $this->articles->related($article->id, $article->category, 3),
            'settings' => $this->settings,
            'structuredData' => $this->seo->encode(
                $this->seo->articleSchema($article, $locale),
                $this->seo->breadcrumbSchema([
                    ['name' => 'Home',       'url' => $this->config->url('')],
                    ['name' => 'Health Hub', 'url' => $this->config->url('health')],
                    ['name' => $article->heading($locale), 'url' => $this->config->url('health/' . $article->slug)],
                ]),
            ),
            'meta' => [
                'title'       => $this->seo->title($article->seoTitle($locale), $locale),
                'description' => $article->seoDescription($locale),
                'canonical'   => $this->config->url('health/' . $article->slug),
                'image'       => $article->coverImage !== null
                    ? $this->config->url('media/' . ltrim($article->coverImage, '/'))
                    : null,
                'type'        => 'article',
                'published'   => $article->publishedAt?->format(DATE_ATOM),
                'modified'    => $article->updatedAt->format(DATE_ATOM),
            ],
        ]);
    }

    // -----------------------------------------------------------------
    //  Facilities & location
    // -----------------------------------------------------------------

    public function locations(Request $request): Response
    {
        return $this->render('public/locations', [
            'settings' => $this->settings,
            'mapKey'   => $this->config->googleMapsKey(),
            'map'      => $this->config->mapCoordinates(),
            'meta'     => [
                'title'       => $this->seo->title(
                    $this->view->translator->get('locations.title', ['city' => $this->view->brand->mainCity]),
                    $this->currentLocale(),
                ),
                'description' => $this->view->brand->businessName . ' is located in ' . $this->view->brand->mainCity
                    . '. Opening hours, directions and contact details.',
                'canonical'   => $this->config->url('locations'),
            ],
            'structuredData' => $this->seo->encode($this->seo->organisationSchema($this->currentLocale())),
        ]);
    }
}
