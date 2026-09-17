<?php

declare(strict_types=1);

namespace Aster\Presentation\Controller\Admin;

use Aster\Infrastructure\Persistence\AuditLogger;
use Aster\Infrastructure\Security\SessionManager;
use Aster\Infrastructure\Storage\FileUploader;
use Aster\Infrastructure\Support\BrandPalette;
use Aster\Infrastructure\Support\BrandResolver;
use Aster\Infrastructure\Support\BrandWriter;
use Aster\Infrastructure\Support\Config;
use Aster\Presentation\Controller\Controller;
use Aster\Presentation\Http\Request;
use Aster\Presentation\Http\Response;
use Aster\Presentation\View\View;
use RuntimeException;

/**
 * "Brand & Theme" admin screen: the single edit form over CompanyBrand.json.
 *
 * Deliberately not a CRUD resource - there is one brand, one file, one form.
 */
final class BrandController extends Controller
{
    public function __construct(
        View $view,
        SessionManager $session,
        Config $config,
        private readonly BrandResolver $resolver,
        private readonly BrandWriter $writer,
        private readonly FileUploader $uploader,
        private readonly AuditLogger $audit,
    ) {
        parent::__construct($view, $session, $config);
    }

    public function index(Request $request): Response
    {
        $path   = $this->resolver->path();
        $rawRaw = is_file($path) ? (string) file_get_contents($path) : '';

        return $this->renderAdmin('admin/brand/index', [
            'brand'   => $this->resolver->resolve(),
            'rawJson' => $rawRaw === '' ? '(no CompanyBrand.json - showing defaults)' : $rawRaw,
            'meta'    => ['title' => 'Brand & Theme', 'noindex' => true],
        ]);
    }

    public function save(Request $request): Response
    {
        $identity = [
            'businessName'     => $request->string('businessName'),
            'businessInitials' => $request->string('businessInitials'),
            'mainCity'         => $request->string('mainCity'),
        ];

        $current = $this->resolver->resolve();
        $identity['logoImage'] = $current->logoImage;
        $identity['heroImage'] = $current->heroImage;

        $logo = $request->file('logoImage');
        $hero = $request->file('heroImage');

        try {
            if ($logo !== null) {
                $identity['logoImage'] = $this->uploader->storeMedia($logo, 'brand', 512)->relativePath;
            }

            if ($hero !== null) {
                $identity['heroImage'] = $this->uploader->storeMedia($hero, 'brand', 1600)->relativePath;
            }
        } catch (\Aster\Domain\Exception\ValidationException $e) {
            return $this->redirectWithError($this->config->adminPath . '/brand', $e->getMessage());
        }

        $hexFields = [
            'primarycolor'     => 'primarycolor',
            'secondarycolor'   => 'secondarycolor',
            'headertext'       => 'headertext',
            'bodytext'         => 'bodytext',
            'footerbackground' => 'footerbackground',
            'footertext'       => 'footertext',
        ];

        $theme = [];

        foreach ($hexFields as $formField => $jsonKey) {
            $value = $request->string($formField);

            if ($value !== '' && !BrandPalette::isValidHex($value)) {
                return $this->redirectWithError(
                    $this->config->adminPath . '/brand',
                    "\"{$value}\" is not a valid colour. Use #RRGGBB.",
                );
            }

            if ($value !== '') {
                $theme[$jsonKey] = $value;
            }
        }

        $backgroundColor = trim($request->string('backgroundcolor'));
        $surface         = trim($request->string('surface'));

        if ($backgroundColor !== '') {
            $theme['backgroundcolor'] = $backgroundColor;
        }

        if ($surface !== '') {
            $theme['surface'] = $surface;
        }

        $transparency = $request->float('transparency', 1.0);
        $theme['transparency'] = max(0.0, min(1.0, $transparency));

        $before  = ['identity' => (array) $current, 'note' => 'previous resolved brand'];
        $payload = ['identity' => array_filter($identity, static fn (mixed $v): bool => $v !== null && $v !== ''), 'theme' => $theme];

        try {
            $this->writer->save($payload);
        } catch (RuntimeException $e) {
            return $this->redirectWithError($this->config->adminPath . '/brand', $e->getMessage());
        }

        // recordDiff() compares values as strings; flatten first so a nested
        // array (the colour ramp, the identity/theme grouping) never reaches
        // that comparison as one of the two sides.
        $this->audit->recordDiff(
            AuditLogger::SETTINGS_SAVED,
            'brand',
            0,
            self::flattenForAudit($before),
            self::flattenForAudit($payload),
            'Updated brand & theme settings',
        );

        return $this->redirectWithSuccess($this->config->adminPath . '/brand', 'Brand & theme saved.');
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private static function flattenForAudit(array $data, string $prefix = ''): array
    {
        $flat = [];

        foreach ($data as $key => $value) {
            $flatKey = $prefix === '' ? (string) $key : $prefix . '.' . $key;

            if (is_array($value)) {
                $flat += self::flattenForAudit($value, $flatKey);
            } else {
                $flat[$flatKey] = $value;
            }
        }

        return $flat;
    }
}
