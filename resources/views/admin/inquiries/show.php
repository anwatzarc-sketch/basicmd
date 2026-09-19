<?php
/**
 * Enquiry detail and response tracking.
 *
 * @var \MediCareMini\Presentation\View\View $view
 * @var \MediCareMini\Domain\Entity\ContactInquiry $inquiry
 * @var list<\MediCareMini\Domain\Enum\InquiryStatus> $statuses
 * @var list<array<string,mixed>> $history
 */

declare(strict_types=1);

$t = $view->translator;

/** Pre-filled mailto so staff can reply without retyping the context. */
$mailto = $inquiry->email !== null
    ? 'mailto:' . rawurlencode($inquiry->email)
        . '?subject=' . rawurlencode('Re: ' . $inquiry->subjectLine())
    : null;
?>
<div class="grid gap-6 xl:grid-cols-[1.4fr_0.6fr]">

    <div class="card-pad">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h2 class="text-xl font-extrabold text-medical-900"><?= $view->e($inquiry->name) ?></h2>
                <p class="mt-1 text-sm text-slate-500">
                    <?= $view->e($t->dateTime($inquiry->createdAt)) ?>
                    &middot; <?= $view->e($inquiry->locale->nativeLabel()) ?>
                </p>
            </div>
            <span class="badge <?= $view->e($inquiry->status->badgeClass()) ?>">
                <?= $view->e($inquiry->status->label()) ?>
            </span>
        </div>

        <dl class="mt-6 grid gap-4 sm:grid-cols-3">
            <div>
                <dt class="stat-label">Phone</dt>
                <dd class="mt-1">
                    <a class="font-semibold text-medical-700 hover:underline" href="<?= $view->e($inquiry->phone->toTelLink()) ?>">
                        <?= $view->e($inquiry->phone->formatNational()) ?>
                    </a>
                </dd>
            </div>
            <div>
                <dt class="stat-label">Email</dt>
                <dd class="mt-1">
                    <?php if ($mailto !== null): ?>
                        <a class="font-semibold text-medical-700 hover:underline" href="<?= $view->e($mailto) ?>">
                            <?= $view->e($inquiry->email) ?>
                        </a>
                    <?php else: ?>
                        <span class="text-slate-400">Not provided</span>
                    <?php endif; ?>
                </dd>
            </div>
            <div>
                <dt class="stat-label">Subject</dt>
                <dd class="mt-1 font-semibold text-slate-800"><?= $view->e($inquiry->subjectLine()) ?></dd>
            </div>
        </dl>

        <div class="mt-6 rounded-2xl bg-slate-50 p-5">
            <p class="whitespace-pre-line text-sm leading-relaxed text-slate-700"><?= $view->e($inquiry->message) ?></p>
        </div>

        <?php if ($mailto !== null): ?>
            <a href="<?= $view->e($mailto) ?>" class="btn-primary btn-sm mt-5">Reply by email</a>
        <?php endif; ?>
    </div>

    <aside class="grid h-max gap-6">
        <form method="post" action="<?= $view->adminUrl('inquiries/' . $inquiry->id) ?>" class="card-pad grid gap-4">
            <?= $view->csrfField() ?>
            <h2 class="text-base font-extrabold text-medical-900">Update</h2>

            <div class="field">
                <label class="label" for="status">Status</label>
                <select class="select" id="status" name="status">
                    <?php foreach ($statuses as $status): ?>
                        <option value="<?= $view->e($status->value) ?>" <?= $view->attr($inquiry->status === $status, 'selected') ?>>
                            <?= $view->e($status->label()) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="field">
                <label class="label" for="notes">Internal notes</label>
                <textarea class="textarea" id="notes" name="notes" rows="5"
                          placeholder="What was done about this"><?= $view->e($inquiry->notes ?? '') ?></textarea>
            </div>

            <button type="submit" class="btn-primary">Save</button>
        </form>

        <form method="post" action="<?= $view->adminUrl('inquiries/' . $inquiry->id . '/delete') ?>"
              data-confirm="Delete this enquiry permanently?">
            <?= $view->csrfField() ?>
            <button type="submit" class="btn-secondary btn-sm w-full text-rose-600">Delete enquiry</button>
        </form>

        <a href="<?= $view->adminUrl('inquiries') ?>" class="btn-ghost btn-sm">&larr; Back to inbox</a>
    </aside>
</div>
