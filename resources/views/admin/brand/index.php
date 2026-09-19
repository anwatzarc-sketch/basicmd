<?php
/**
 * Brand & Theme editor: the single form over storage/CompanyBrand.json.
 *
 * @var \Aster\Presentation\View\View $view
 * @var \Aster\Domain\ValueObject\CompanyBrand $brand
 * @var string $rawJson
 */

declare(strict_types=1);

/** shade => current hex, derived from the resolved "R G B" triple for the colour pickers' value= attributes. */
$tripleToHex = static function (string $triple): string {
    $parts = array_map('intval', explode(' ', trim($triple)));
    $parts = array_pad($parts, 3, 0);

    return sprintf('#%02x%02x%02x', $parts[0], $parts[1], $parts[2]);
};

$primaryHex   = $tripleToHex($brand->primary('500'));
$secondaryHex = $tripleToHex($brand->secondaryColor);
$headerHex    = $tripleToHex($brand->headerText);
$bodyHex      = $tripleToHex($brand->bodyText);
$footerBgHex  = $tripleToHex($brand->footerBackground);
$footerFgHex  = $tripleToHex($brand->footerText);
?>
<form method="post" action="<?= $view->adminUrl('brand') ?>" enctype="multipart/form-data"
      class="card-pad mx-auto grid max-w-3xl gap-5" data-brand-form>
    <?= $view->csrfField() ?>

    <div>
        <h2 class="text-lg font-extrabold text-medical-900">Identity</h2>
        <p class="hint">Overrides the clinic name, city and logo shown across the site. Leave a field blank to keep today's default.</p>
    </div>

    <div class="grid gap-5 sm:grid-cols-2">
        <div class="field">
            <label class="label" for="businessName">Business name</label>
            <input class="input" type="text" id="businessName" name="businessName"
                   value="<?= $view->e($brand->businessName) ?>">
        </div>

        <div class="field">
            <label class="label" for="businessInitials">Monogram initials</label>
            <input class="input" type="text" id="businessInitials" name="businessInitials" maxlength="3"
                   value="<?= $view->e($brand->businessInitials) ?>">
            <span class="hint">Shown in the logo mark when no logo image is uploaded.</span>
        </div>

        <div class="field sm:col-span-2">
            <label class="label" for="mainCity">Main city</label>
            <input class="input" type="text" id="mainCity" name="mainCity"
                   value="<?= $view->e($brand->mainCity) ?>">
            <span class="hint">Used in the site's structured data (address, local SEO).</span>
        </div>

        <div class="field">
            <label class="label" for="logoImage">Logo</label>
            <input class="input" type="file" id="logoImage" name="logoImage" accept="image/jpeg,image/png,image/webp">
            <?php if ($brand->logoImage !== null): ?>
                <img src="<?= $view->media($brand->logoImage) ?>" alt=""
                     class="mt-3 h-16 w-16 rounded-2xl object-cover" width="64" height="64">
            <?php endif; ?>
        </div>

        <div class="field">
            <label class="label" for="heroImage">Hero image</label>
            <input class="input" type="file" id="heroImage" name="heroImage" accept="image/jpeg,image/png,image/webp">
            <?php if ($brand->heroImage !== null): ?>
                <img src="<?= $view->media($brand->heroImage) ?>" alt=""
                     class="mt-3 h-16 w-28 rounded-2xl object-cover" width="112" height="64">
            <?php endif; ?>
            <span class="hint">Replaces the featured-services card in the homepage hero when set.</span>
        </div>
    </div>

    <div class="border-t border-slate-200 pt-5">
        <h2 class="text-lg font-extrabold text-medical-900">Colour theme</h2>
        <p class="hint">The primary colour drives the whole shade ramp (50-950) automatically.</p>
    </div>

    <div class="grid gap-5 sm:grid-cols-2">
        <?php
        $colourFields = [
            ['primarycolor',     'Primary colour',    $primaryHex],
            ['secondarycolor',   'Secondary colour',  $secondaryHex],
            ['headertext',       'Header text',       $headerHex],
            ['bodytext',         'Body text',          $bodyHex],
            ['footerbackground', 'Footer background', $footerBgHex],
            ['footertext',       'Footer text',        $footerFgHex],
        ];
        ?>
        <?php foreach ($colourFields as [$name, $label, $hex]): ?>
            <div class="field">
                <label class="label" for="<?= $name ?>"><?= $view->e($label) ?></label>
                <div class="flex items-center gap-2" data-colour-pair>
                    <input type="color" class="h-10 w-12 shrink-0 rounded-lg border border-slate-200"
                           data-colour-picker value="<?= $view->e($hex) ?>" aria-hidden="true" tabindex="-1">
                    <input class="input" type="text" id="<?= $name ?>" name="<?= $name ?>"
                           data-colour-text pattern="^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$"
                           value="<?= $view->e($hex) ?>">
                </div>
            </div>
        <?php endforeach; ?>

        <div class="field sm:col-span-2">
            <label class="label" for="backgroundcolor">Page background</label>
            <input class="input" type="text" id="backgroundcolor" name="backgroundcolor"
                   value="<?= $view->e($brand->backgroundColor) ?>">
            <span class="hint">Any CSS background value: a gradient, an rgba() colour, or a hex.</span>
        </div>

        <div class="field sm:col-span-2">
            <label class="label" for="surface">Glass surface colour</label>
            <input class="input" type="text" id="surface" name="surface"
                   value="<?= $view->e($brand->surface) ?>">
        </div>

        <div class="field sm:col-span-2">
            <label class="label" for="transparency">Surface transparency (<span data-transparency-value><?= $view->e((string) $brand->transparency) ?></span>)</label>
            <input type="range" id="transparency" name="transparency" min="0" max="1" step="0.05"
                   value="<?= $view->e((string) $brand->transparency) ?>" data-transparency-range>
        </div>
    </div>

    <div class="border-t border-slate-200 pt-5">
        <h2 class="text-lg font-extrabold text-medical-900">Live preview</h2>
        <div class="brand-preview-surface mt-3 rounded-2xl border border-slate-200 p-6" data-brand-preview>
            <div class="brand-preview-primary inline-flex items-center gap-2 rounded-xl px-4 py-2 text-sm font-bold text-white">
                Primary button
            </div>
            <p class="brand-preview-body mt-3 text-sm">
                Body text sample, using the header/body colour above.
            </p>
        </div>
    </div>

    <div class="flex flex-wrap gap-3">
        <button type="submit" class="btn-primary">Save brand &amp; theme</button>
        <a href="<?= $view->adminUrl('') ?>" class="btn-secondary">Cancel</a>
    </div>

    <details class="rounded-2xl border border-slate-200 p-4">
        <summary class="cursor-pointer text-sm font-bold text-slate-700">What's actually saved (storage/CompanyBrand.json)</summary>
        <pre class="mt-3 overflow-x-auto rounded-xl bg-slate-900 p-4 text-xs text-slate-100"><?= $view->e($rawJson) ?></pre>
    </details>
</form>

<?php /* nonce required: the CSP blocks any inline script without one. */ ?>
<script nonce="<?= $view->e($cspNonce ?? '') ?>">
(function () {
    var form = document.querySelector('[data-brand-form]');
    if (!form) { return; }

    // Keep each <input type="color"> in sync with its paired hex text input.
    form.querySelectorAll('[data-colour-pair]').forEach(function (pair) {
        var picker = pair.querySelector('[data-colour-picker]');
        var text   = pair.querySelector('[data-colour-text]');

        picker.addEventListener('input', function () {
            text.value = picker.value;
            updatePreview();
        });

        text.addEventListener('input', function () {
            if (/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/.test(text.value)) {
                picker.value = text.value;
                updatePreview();
            }
        });
    });

    var range = form.querySelector('[data-transparency-range]');
    var rangeLabel = form.querySelector('[data-transparency-value]');
    if (range) {
        range.addEventListener('input', function () {
            if (rangeLabel) { rangeLabel.textContent = range.value; }
        });
    }

    function updatePreview() {
        var preview = form.querySelector('[data-brand-preview]');
        if (!preview) { return; }

        var primary = form.querySelector('#primarycolor');
        var header  = form.querySelector('#headertext');

        if (primary && primary.value) {
            preview.style.setProperty('--preview-primary', primary.value);
        }
        if (header && header.value) {
            preview.style.setProperty('--preview-body', header.value);
        }
    }

    updatePreview();
})();
</script>
