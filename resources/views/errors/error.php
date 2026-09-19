<?php
/**
 * Error page.
 *
 * Shows a friendly message and a way forward. The debug block renders only
 * when APP_DEBUG is on AND APP_ENV is not production - Config forces
 * appDebug to false in production regardless of the .env value.
 *
 * @var \MediCareMini\Presentation\View\View $view
 * @var int $status
 * @var string $title
 * @var string $message
 * @var array<string,mixed>|null $debug
 */

declare(strict_types=1);

// $settings is not injected on the error path: an error page must render even
// when the database is the thing that failed. Fall back to a blank phone
// rather than fataling inside the handler for the original error.
$phone = isset($settings) ? ($settings->string('phone_primary', '') ?? '') : '';
?>
<section class="mx-auto grid min-h-[60vh] max-w-2xl place-items-center px-4 py-16 text-center sm:px-6">
    <div>
        <p class="text-7xl font-extrabold tracking-tight text-medical-700 sm:text-8xl"><?= (int) $status ?></p>
        <h1 class="mt-4 text-2xl font-extrabold text-medical-900 sm:text-3xl"><?= $view->e($title) ?></h1>
        <p class="mx-auto mt-3 max-w-md leading-relaxed text-slate-600"><?= $view->e($message) ?></p>

        <div class="mt-8 flex flex-wrap justify-center gap-3">
            <a href="<?= $view->url('') ?>" class="btn-primary"><?= $view->t('errors.home') ?></a>
            <a href="<?= $view->url('book') ?>" class="btn-secondary"><?= $view->t('nav.book') ?></a>
        </div>

        <?php if ($phone !== ''): ?>
            <p class="mt-6 text-sm text-slate-500">
                <?= $view->t('errors.call_us', ['phone' => '']) ?>
                <a class="font-bold text-medical-700 hover:underline" href="tel:<?= $view->e($phone) ?>"><?= $view->e($phone) ?></a>
            </p>
        <?php endif; ?>

        <?php if ($debug !== null): ?>
            <details class="mt-10 rounded-2xl border border-rose-200 bg-rose-50 p-5 text-left">
                <summary class="cursor-pointer text-sm font-extrabold text-rose-800">
                    Debug: <?= $view->e($debug['exception']) ?>
                </summary>
                <p class="mt-3 font-mono text-xs text-rose-900"><?= $view->e($debug['message']) ?></p>
                <p class="mt-2 font-mono text-xs text-rose-700"><?= $view->e($debug['file']) ?>:<?= (int) $debug['line'] ?></p>
                <pre class="mt-3 max-h-64 overflow-auto rounded-xl bg-white p-3 font-mono text-[11px] leading-relaxed text-slate-700"><?= $view->e(implode("\n", $debug['trace'])) ?></pre>
            </details>
        <?php endif; ?>
    </div>
</section>
