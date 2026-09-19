<?php

declare(strict_types=1);

namespace MediCareMini\Presentation\Controller\Web;

use MediCareMini\Domain\Enum\Locale;
use MediCareMini\Domain\Exception\HttpException;
use MediCareMini\Infrastructure\Persistence\ArticleRepository;
use MediCareMini\Infrastructure\Persistence\DoctorRepository;
use MediCareMini\Infrastructure\Persistence\ServiceRepository;
use MediCareMini\Infrastructure\Persistence\SettingsRepository;
use MediCareMini\Infrastructure\Support\BrandResolver;
use MediCareMini\Infrastructure\Support\Config;
use MediCareMini\Infrastructure\Storage\FileUploader;
use MediCareMini\Presentation\Http\Request;
use MediCareMini\Presentation\Http\Response;

/**
 * sitemap.xml, robots.txt, the PWA manifest, and media file serving.
 *
 * The sitemap is generated live rather than written to disk: the article and
 * doctor sets change from the CMS, and a stale file would leave new content
 * uncrawled until someone remembered to regenerate it. The manifest is
 * generated live for the same reason applied to branding: clinic_name is a
 * setting, not a constant (see layouts/public.php), so the installed app's
 * name must follow it rather than freeze whatever it was at deploy time.
 *
 * Every URL carries xhtml:link alternates so Google treats the English and
 * Amharic versions as translations rather than duplicate content.
 */
final class SitemapController
{
    public function __construct(
        private readonly Config $config,
        private readonly ArticleRepository $articles,
        private readonly ServiceRepository $services,
        private readonly DoctorRepository $doctors,
        private readonly FileUploader $uploader,
        private readonly SettingsRepository $settings,
        private readonly BrandResolver $brand,
    ) {
    }

    public function sitemap(Request $request): Response
    {
        $urls = [];

        // Static pages, weighted by how much they matter for acquisition.
        $static = [
            ''          => ['priority' => '1.0', 'freq' => 'weekly'],
            'services'  => ['priority' => '0.9', 'freq' => 'weekly'],
            'doctors'   => ['priority' => '0.9', 'freq' => 'weekly'],
            'packages'  => ['priority' => '0.9', 'freq' => 'monthly'],
            'health'    => ['priority' => '0.8', 'freq' => 'daily'],
            'book'      => ['priority' => '0.9', 'freq' => 'monthly'],
            'locations' => ['priority' => '0.7', 'freq' => 'monthly'],
            'contact'   => ['priority' => '0.6', 'freq' => 'monthly'],
            'privacy'   => ['priority' => '0.2', 'freq' => 'yearly'],
            'terms'     => ['priority' => '0.2', 'freq' => 'yearly'],
        ];

        foreach ($static as $path => $meta) {
            $urls[] = [
                'loc'      => $this->config->url($path),
                'priority' => $meta['priority'],
                'freq'     => $meta['freq'],
                'lastmod'  => null,
                'alt'      => true,
            ];
        }

        foreach ($this->articles->sitemapEntries() as $article) {
            $urls[] = [
                'loc'      => $this->config->url('health/' . $article['slug']),
                'priority' => '0.7',
                'freq'     => 'monthly',
                'lastmod'  => substr((string) $article['updated_at'], 0, 10),
                // Only offer an Amharic alternate when one actually exists.
                'alt'      => (bool) $article['has_am'],
            ];
        }

        foreach ($this->services->publicList() as $service) {
            $urls[] = [
                'loc'      => $this->config->url('services/' . $service->slug),
                'priority' => '0.7',
                'freq'     => 'monthly',
                'lastmod'  => null,
                'alt'      => $service->nameAm !== null,
            ];
        }

        foreach ($this->doctors->publicList() as $doctor) {
            $urls[] = [
                'loc'      => $this->config->url('doctors/' . $doctor->slug),
                'priority' => '0.6',
                'freq'     => 'monthly',
                'lastmod'  => null,
                'alt'      => $doctor->fullNameAm !== null,
            ];
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" '
            . 'xmlns:xhtml="http://www.w3.org/1999/xhtml">' . "\n";

        foreach ($urls as $url) {
            $loc = htmlspecialchars($url['loc'], ENT_XML1 | ENT_QUOTES, 'UTF-8');

            $xml .= "  <url>\n";
            $xml .= "    <loc>{$loc}</loc>\n";

            if ($url['lastmod'] !== null) {
                $xml .= "    <lastmod>{$url['lastmod']}</lastmod>\n";
            }

            $xml .= "    <changefreq>{$url['freq']}</changefreq>\n";
            $xml .= "    <priority>{$url['priority']}</priority>\n";

            if ($url['alt']) {
                foreach (Locale::all() as $locale) {
                    $xml .= '    <xhtml:link rel="alternate" hreflang="' . $locale->htmlLang()
                        . '" href="' . $loc . '?lang=' . $locale->value . '"/>' . "\n";
                }

                $xml .= '    <xhtml:link rel="alternate" hreflang="x-default" href="' . $loc . '"/>' . "\n";
            }

            $xml .= "  </url>\n";
        }

        $xml .= '</urlset>';

        return Response::xml($xml)->withCache(3600);
    }

    public function robots(Request $request): Response
    {
        $adminPath = $this->config->adminPath;

        $lines = [
            'User-agent: *',
            'Allow: /',
            // Patient-specific pages must never be crawled: they contain PHI
            // and the reference is the only thing protecting them.
            "Disallow: /{$adminPath}",
            'Disallow: /booking/',
            'Disallow: /my-booking',
            'Disallow: /api/',
            '',
            'Sitemap: ' . $this->config->url('sitemap.xml'),
        ];

        return Response::text(implode("\n", $lines))->withCache(86400);
    }

    /**
     * The PWA manifest (FRS-independent - this is a platform feature, not a
     * Phase II one). name/short_name follow the same clinic_name setting
     * every page's <title> already does, so a clinic that renames itself in
     * Settings gets a correctly-named installed app without a redeploy.
     */
    public function manifest(Request $request): Response
    {
        $clinicName = $this->brand->resolve()->businessName;
        $tagline    = $this->settings->string('tagline', '');
        $primary    = array_map('intval', explode(' ', $this->brand->resolve()->primary('700')));
        $themeColor = sprintf('#%02x%02x%02x', ...$primary);

        // Home-screen labels are cramped - truncate on a word boundary
        // rather than mid-word, and only when the full name would not fit.
        $shortName = mb_strlen($clinicName) > 15
            ? rtrim(mb_substr($clinicName, 0, 15)) . '…'
            : $clinicName;

        $manifest = [
            'id'               => '/',
            'name'             => $clinicName,
            'short_name'       => $shortName,
            'description'      => $tagline !== '' ? $tagline : $clinicName . ' - online booking and patient services',
            'start_url'        => '/?source=pwa',
            'scope'            => '/',
            'display'          => 'standalone',
            'orientation'      => 'portrait-primary',
            'background_color' => '#ffffff',
            'theme_color'      => $themeColor,
            'categories'       => ['health', 'medical'],
            'icons'            => [
                ['src' => '/assets/img/icons/icon-192.png', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => '/assets/img/icons/icon-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => '/assets/img/icons/icon-maskable-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
            ],
        ];

        return Response::json($manifest)
            ->withHeader('Content-Type', 'application/manifest+json; charset=UTF-8')
            ->withCache(3600);
    }

    /**
     * Serve an uploaded CMS image.
     *
     * Media lives outside the webroot alongside the payment proofs, so it is
     * streamed here rather than served directly. That keeps one upload
     * directory with one set of permissions, and lets every response carry
     * the nosniff header regardless of web-server configuration.
     *
     * In production a well-configured nginx serves this directly (see the
     * location block in deploy/nginx.conf); this route is the fallback.
     */
    public function media(Request $request): Response
    {
        $path = $request->routeString('path');

        // Reject traversal before touching the filesystem.
        if ($path === '' || str_contains($path, '..') || str_contains($path, "\0")) {
            throw HttpException::notFound();
        }

        $absolute = realpath(
            $this->config->mediaDir() . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path)
        );

        $root = realpath($this->config->mediaDir());

        if ($absolute === false || $root === false || !str_starts_with($absolute, $root) || !is_file($absolute)) {
            throw HttpException::notFound();
        }

        $mime = match (strtolower(pathinfo($absolute, PATHINFO_EXTENSION))) {
            'webp'        => 'image/webp',
            'jpg', 'jpeg' => 'image/jpeg',
            'png'         => 'image/png',
            'svg'         => 'image/svg+xml',
            default       => 'application/octet-stream',
        };

        // Unknown types are never served: an unexpected extension in the
        // media directory is a sign something went wrong, not a file to hand
        // to a browser.
        if ($mime === 'application/octet-stream') {
            throw HttpException::notFound();
        }

        return Response::file($absolute, $mime, basename($absolute))
            ->withHeader('Cache-Control', 'public, max-age=2592000, immutable');
    }
}
