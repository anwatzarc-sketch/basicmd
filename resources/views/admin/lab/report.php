<?php
/**
 * The A4 laboratory report sheet.
 *
 * Branded from CompanyBrand and system_settings, not from hard-coded
 * strings: whoever configures the clinic's name, logo, address and
 * colours in the admin owns what a patient is handed at the counter,
 * and a second set of details baked in here would drift from the first.
 *
 * Flags are derived here, at render, by LabResultFlag::evaluate() through
 * LabResultLine::flag(). Nothing in the stored payload asserts a flag, so
 * a printed sheet cannot carry one that disagrees with its own number.
 *
 * The signature block names real rows: the technologist is whoever saved
 * the result (diagnostic_orders.resulted_by_id) and the clinician is the
 * ordering physician. Neither is decorative - if a name is missing the
 * line says so rather than printing a blank where a signature belongs.
 *
 * @var \Aster\Presentation\View\View $view
 * @var \Aster\Domain\Entity\DiagnosticOrder $order
 * @var \Aster\Domain\DTO\LabReport|null $report
 * @var \Aster\Infrastructure\Persistence\SettingsRepository $settings
 * @var \DateTimeImmutable $generatedAt
 * @var bool $canResult
 */

declare(strict_types=1);

use Aster\Domain\Enum\DiagnosticStatus;

$clinicName = $settings->localized('clinic_name', $view->locale(), $view->brand->businessName);
$address    = $settings->localized('address', $view->locale());
$phone      = $settings->string('phone_primary');
$email      = $settings->string('email_public');

$released  = $order->status === DiagnosticStatus::COMPLETED;
$barcode   = $order->barcodeValue();
$reference = $order->accessionNumber?->value ?? ('ORDER-' . $order->id);
?>

<!-- Action bar - screen only ------------------------------------------ -->
<div class="no-print mx-auto mb-6 flex max-w-[210mm] flex-wrap items-center justify-between gap-4 rounded-2xl bg-white p-4 shadow-sm">
    <div class="flex items-center gap-3">
        <a href="<?= $view->adminUrl('lab') ?>" class="btn-secondary btn-sm">Back to queue</a>
        <?php if ($canResult): ?>
            <a href="<?= $view->adminUrl('lab/orders/' . $order->id . '/results') ?>" class="btn-ghost btn-sm">Edit results</a>
        <?php endif; ?>
    </div>

    <div class="flex items-center gap-3">
        <?php if (!$released): ?>
            <span class="chip--partial">Draft - not released</span>
        <?php endif; ?>
        <button type="button" class="btn-primary btn-sm" data-print>Print A4 sheet</button>
    </div>
</div>

<?php if (!$released): ?>
    <div class="no-print mx-auto mb-6 max-w-[210mm]">
        <div class="alert-warning" role="alert">
            <svg class="mt-0.5 h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
            </svg>
            <span class="flex-1">
                These results have not been verified and released. The sheet below is a working preview and is
                watermarked as provisional when printed.
            </span>
        </div>
    </div>
<?php endif; ?>

<!-- The sheet ---------------------------------------------------------- -->
<article class="a4-page<?= $released ? '' : ' a4-provisional' ?>">

    <!-- Letterhead -->
    <header class="flex items-start justify-between gap-6 border-b-2 border-medical-700 pb-4">
        <div class="flex items-center gap-4">
            <?php if ($view->brand->logoImage !== null): ?>
                <img src="<?= $view->media($view->brand->logoImage) ?>" alt="" class="h-16 w-16 rounded-xl object-cover">
            <?php else: ?>
                <span class="grid h-16 w-16 place-items-center rounded-xl bg-medical-700 text-xl font-extrabold text-white">
                    <?= $view->e($view->brand->businessInitials) ?>
                </span>
            <?php endif; ?>

            <div>
                <h1 class="text-xl font-extrabold tracking-tight text-medical-900"><?= $view->e($clinicName) ?></h1>
                <p class="text-xs font-semibold text-slate-600">Clinical Diagnostic Laboratory</p>
                <p class="text-[11px] text-slate-500">
                    <?= $view->e($address) ?><?php if ($phone !== ''): ?> &middot; <?= $view->e($phone) ?><?php endif; ?>
                    <?php if ($email !== ''): ?> &middot; <?= $view->e($email) ?><?php endif; ?>
                </p>
            </div>
        </div>

        <div class="text-right">
            <span class="inline-block rounded-md bg-medical-900 px-3 py-1 text-[10px] font-extrabold uppercase tracking-widest text-white">
                Laboratory Report
            </span>
            <p class="mt-1 font-mono text-[11px] text-slate-500"><?= $view->e($reference) ?></p>
            <p class="mt-0.5 text-[10px] font-bold <?= $released ? 'text-emerald-700' : 'text-amber-700' ?>">
                <?= $released ? 'Verified &amp; released' : 'Provisional - not released' ?>
            </p>
        </div>
    </header>

    <!-- Patient & specimen -->
    <section class="mt-4 rounded-lg border border-slate-200 bg-slate-50 p-3.5">
        <dl class="grid grid-cols-4 gap-x-4 gap-y-2.5 text-xs">
            <?php
            $facts = [
                'Patient'         => $order->patientName ?? 'Unknown',
                'Patient ID'      => $order->patientPid ?? '-',
                'Age / sex'       => $order->patientAgeGender(),
                'Ordering clinician' => $order->physicianName ?? '-',
                'Visit number'    => $order->visitNumber ?? '-',
                'Specimen'        => $order->specimenType ?? 'Not specified',
                'Collected'       => $order->collectedAt?->format('Y-m-d H:i') ?? 'Not recorded',
                'Reported'        => $order->resultedAt?->format('Y-m-d H:i') ?? $generatedAt->format('Y-m-d H:i'),
            ];
            ?>
            <?php foreach ($facts as $label => $value): ?>
                <div>
                    <dt class="text-[9px] font-extrabold uppercase tracking-wider text-slate-500"><?= $view->e($label) ?></dt>
                    <dd class="text-xs font-semibold text-slate-900"><?= $view->e((string) $value) ?></dd>
                </div>
            <?php endforeach; ?>
        </dl>

        <?php if ($barcode !== null): ?>
            <div class="mt-3 flex items-end justify-between gap-4 border-t border-slate-200 pt-2.5">
                <div>
                    <?= $view->partial('partials/barcode', ['view' => $view, 'value' => $barcode, 'height' => 30]) ?>
                    <p class="mt-1 font-mono text-[10px] tracking-widest text-slate-600"><?= $view->e($barcode) ?></p>
                </div>
                <?php if ($order->clinicalLocation !== null): ?>
                    <p class="text-[10px] text-slate-500">
                        Collected at <b class="text-slate-700"><?= $view->e($order->clinicalLocation) ?></b>
                    </p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </section>

    <!-- Results -->
    <section class="mt-5">
        <div class="flex items-end justify-between border-b-2 border-medical-600 pb-1.5">
            <h2 class="text-sm font-extrabold uppercase tracking-wide text-medical-900">
                <?= $view->e($report?->title ?? $order->testName) ?>
            </h2>
            <span class="text-[10px] font-medium text-slate-500">ICD <?= $view->e($order->icdCode) ?></span>
        </div>

        <?php $lines = $report?->reportedLines() ?? []; ?>

        <?php if ($lines !== []): ?>
            <table class="mt-2 w-full border-collapse text-xs">
                <thead>
                    <tr class="bg-medical-900 text-[10px] uppercase tracking-wider text-white">
                        <th class="w-2/5 px-3 py-2 text-left">Parameter</th>
                        <th class="px-2 py-2 text-center">Result</th>
                        <th class="w-20 px-2 py-2 text-center">Flag</th>
                        <th class="w-24 px-2 py-2 text-left">Units</th>
                        <th class="w-1/4 px-3 py-2 text-left">Reference interval</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($lines as $line): ?>
                        <?php $flag = $line->flag(); ?>
                        <tr class="border-b border-slate-200">
                            <td class="px-3 py-2 align-top">
                                <span class="font-semibold text-slate-900"><?= $view->e($line->parameterName) ?></span>
                                <?php if ($line->methodology !== ''): ?>
                                    <span class="block text-[9px] font-normal text-slate-400"><?= $view->e($line->methodology) ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="px-2 py-2 text-center align-top font-mono <?= $flag->isAbnormal() ? 'font-extrabold text-rose-700' : 'font-bold text-slate-900' ?>">
                                <?= $view->e($line->value) ?>
                            </td>
                            <td class="px-2 py-2 text-center align-top">
                                <?php if ($flag->isAbnormal()): ?>
                                    <span class="<?= $view->e($flag->chipClass()) ?> !px-1.5 !py-0.5 !text-[9px]">
                                        <?= $view->e($flag->shortLabel()) ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="px-2 py-2 align-top text-slate-600"><?= $view->e($line->unit) ?></td>
                            <td class="px-3 py-2 align-top font-mono text-[10px] text-slate-600"><?= $view->e($line->referenceDisplay()) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?php if ($report !== null && $report->abnormalCount() > 0): ?>
                <p class="mt-2 text-[10px] text-slate-500">
                    <b class="text-slate-700"><?= (int) $report->abnormalCount() ?></b>
                    of <?= count($lines) ?> reported parameters fall outside their reference interval.
                    L / H mark a value below or above the interval; LL / HH mark one more than 25% beyond it.
                </p>
            <?php endif; ?>
        <?php elseif ($report === null): ?>
            <p class="mt-4 text-xs text-slate-500">No results have been recorded against this requisition yet.</p>
        <?php else: ?>
            <p class="mt-4 text-xs text-slate-500">
                This result was recorded as a written report rather than as measured parameters.
            </p>
        <?php endif; ?>
    </section>

    <!-- Impression -->
    <?php if ($report !== null && $report->impression !== ''): ?>
        <section class="mt-5 break-inside-avoid rounded-lg border border-slate-200 p-3">
            <h3 class="text-[10px] font-extrabold uppercase tracking-wider text-medical-900">Comments &amp; diagnostic impression</h3>
            <p class="mt-1 whitespace-pre-line text-xs leading-relaxed text-slate-800"><?= $view->e($report->impression) ?></p>
        </section>
    <?php endif; ?>

    <!-- Signatures -->
    <footer class="mt-8 break-inside-avoid border-t-2 border-slate-300 pt-4">
        <div class="grid grid-cols-3 items-end gap-6">
            <div class="text-center">
                <div class="border-t border-slate-400 pt-1">
                    <p class="text-xs font-bold text-slate-800">
                        <?= $view->e($order->resultedByName ?? 'Not yet recorded') ?>
                    </p>
                    <p class="text-[10px] text-slate-500">Recorded by &middot; laboratory</p>
                </div>
            </div>

            <div class="text-center">
                <div class="border-t border-slate-400 pt-1">
                    <p class="text-xs font-bold text-slate-800"><?= $view->e($order->physicianName ?? '-') ?></p>
                    <p class="text-[10px] text-slate-500">Ordering clinician</p>
                </div>
            </div>

            <div class="text-right">
                <p class="text-[9px] leading-tight text-slate-500">
                    Released <?= $view->e($order->resultedAt?->format('Y-m-d H:i') ?? '-') ?><br>
                    Printed <?= $view->e($generatedAt->format('Y-m-d H:i')) ?><br>
                    <span class="font-mono"><?= $view->e($reference) ?></span>
                </p>
            </div>
        </div>

        <div class="mt-5 flex items-center justify-between border-t border-slate-200 pt-2 text-[9px] text-slate-500">
            <p>
                <?= $view->e($clinicName) ?> &middot; Confidential patient record.
                Reference intervals are method-dependent; interpret alongside the clinical picture.
            </p>
            <p>End of report</p>
        </div>
    </footer>
</article>
