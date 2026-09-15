<?php
/**
 * Transfer-method management.
 *
 * These rows ARE the payment integration: whatever is entered here is what
 * the patient sees at checkout and transfers money to. A typo in an account
 * number sends patients' money to the wrong place, so the warning is loud.
 *
 * Note: no inline onclick handlers anywhere - the CSP blocks inline script,
 * so the edit button carries its data in a data-* attribute and app.js binds
 * the behaviour.
 *
 * @var \Aster\Presentation\View\View $view
 * @var list<\Aster\Domain\Entity\PaymentMethod> $methods
 * @var list<\Aster\Domain\Enum\PaymentChannel> $channels
 */

declare(strict_types=1);
?>
<div class="alert-warning" role="note">
    <svg class="mt-0.5 h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
    </svg>
    <span>Patients transfer money to exactly what is entered here. Double-check every account number before saving.</span>
</div>

<div class="mt-6 grid gap-6 xl:grid-cols-[1fr_420px]">
    <div class="grid gap-4">
        <?php foreach ($methods as $method): ?>
            <article class="card-pad">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0">
                        <b class="block text-lg text-medical-900"><?= $view->e($method->provider) ?></b>
                        <span class="text-xs text-slate-500"><?= $view->e($method->channel->label()) ?></span>
                    </div>
                    <div class="flex shrink-0 flex-wrap gap-2">
                        <span class="badge <?= $method->isActive
                            ? 'border-emerald-200 bg-emerald-50 text-emerald-700'
                            : 'border-slate-200 bg-slate-100 text-slate-600' ?>">
                            <?= $method->isActive ? 'Active' : 'Inactive' ?>
                        </span>
                        <?php if (!$method->expectsProof()): ?>
                            <span class="badge border-sky-200 bg-sky-50 text-sky-700">No receipt</span>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($method->hasAccountDetails()): ?>
                    <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2">
                        <div>
                            <dt class="stat-label">Account name</dt>
                            <dd class="mt-0.5 font-semibold text-slate-800"><?= $view->e($method->accountName ?? '-') ?></dd>
                        </div>
                        <div>
                            <dt class="stat-label">Account number</dt>
                            <dd class="mt-0.5 font-mono font-bold text-medical-800"><?= $view->e($method->accountNumber ?? '-') ?></dd>
                        </div>
                        <?php if ($method->branch !== null): ?>
                            <div class="sm:col-span-2">
                                <dt class="stat-label">Branch</dt>
                                <dd class="mt-0.5 text-slate-700"><?= $view->e($method->branch) ?></dd>
                            </div>
                        <?php endif; ?>
                    </dl>
                <?php endif; ?>

                <div class="mt-4 flex gap-2 border-t border-slate-100 pt-4">
                    <button type="button" class="btn-secondary btn-sm flex-1"
                            data-edit-method="<?= $view->e($view->json([
                                'id'              => $method->id,
                                'channel'         => $method->channel->value,
                                'provider'        => $method->provider,
                                'provider_am'     => $method->providerAm ?? '',
                                'account_name'    => $method->accountName ?? '',
                                'account_number'  => $method->accountNumber ?? '',
                                'branch'          => $method->branch ?? '',
                                'instructions'    => $method->instructions ?? '',
                                'instructions_am' => $method->instructionsAm ?? '',
                                'sort_order'      => $method->sortOrder,
                                'requires_proof'  => $method->requiresProof,
                                'active'          => $method->isActive,
                            ])) ?>">
                        Edit
                    </button>
                    <form method="post" action="<?= $view->adminUrl('payments/methods/' . $method->id . '/delete') ?>"
                          data-confirm="Remove this payment method? Patients will no longer see it at checkout.">
                        <?= $view->csrfField() ?>
                        <button type="submit" class="btn-ghost btn-sm text-rose-600">Remove</button>
                    </form>
                </div>
            </article>
        <?php endforeach; ?>

        <?php if ($methods === []): ?>
            <p class="rounded-2xl border border-dashed border-slate-300 p-10 text-center text-sm text-slate-500">
                No payment methods configured. Patients cannot pay until at least one is added.
            </p>
        <?php endif; ?>
    </div>

    <form id="method-form" method="post" action="<?= $view->adminUrl('payments/methods') ?>"
          class="card-pad grid h-max gap-4" data-method-form>
        <?= $view->csrfField() ?>
        <input type="hidden" name="id" value="">

        <div class="flex items-center justify-between">
            <h2 class="text-base font-extrabold text-medical-900" data-method-form-title>Add a method</h2>
            <button type="button" class="btn-ghost btn-sm" data-method-reset hidden>Clear</button>
        </div>

        <div class="field">
            <label class="label" for="channel">Channel</label>
            <select class="select" id="channel" name="channel">
                <?php foreach ($channels as $channel): ?>
                    <option value="<?= $view->e($channel->value) ?>"><?= $view->e($channel->label()) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="field">
            <label class="label" for="provider">Provider <span class="text-rose-500" aria-hidden="true">*</span></label>
            <input class="input" type="text" id="provider" name="provider" required
                   placeholder="Commercial Bank of Ethiopia">
        </div>

        <div class="field">
            <label class="label" for="provider_am">Provider (Amharic)</label>
            <input class="input font-ethiopic" type="text" id="provider_am" name="provider_am" lang="am-ET">
        </div>

        <div class="field">
            <label class="label" for="account_name">Account name</label>
            <input class="input" type="text" id="account_name" name="account_name">
        </div>

        <div class="field">
            <label class="label" for="account_number">Account number</label>
            <input class="input font-mono" type="text" id="account_number" name="account_number">
        </div>

        <div class="field">
            <label class="label" for="branch">Branch</label>
            <input class="input" type="text" id="branch" name="branch">
        </div>

        <div class="field">
            <label class="label" for="instructions">Instructions (English)</label>
            <textarea class="textarea" id="instructions" name="instructions" rows="3"></textarea>
        </div>

        <div class="field">
            <label class="label" for="instructions_am">Instructions (Amharic)</label>
            <textarea class="textarea font-ethiopic" id="instructions_am" name="instructions_am" rows="3" lang="am-ET"></textarea>
        </div>

        <div class="field">
            <label class="label" for="sort_order">Display order</label>
            <input class="input" type="number" id="sort_order" name="sort_order" value="0">
        </div>

        <label class="flex items-center gap-2.5 text-sm font-semibold text-slate-700">
            <input class="checkbox" type="checkbox" name="requires_proof" value="1" checked>
            Patient must upload a receipt
        </label>

        <label class="flex items-center gap-2.5 text-sm font-semibold text-slate-700">
            <input class="checkbox" type="checkbox" name="active" value="1" checked>
            Active (offered at checkout)
        </label>

        <button type="submit" class="btn-primary">Save method</button>
    </form>
</div>
