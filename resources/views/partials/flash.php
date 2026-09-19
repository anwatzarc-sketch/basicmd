<?php
/**
 * Flash message banner.
 *
 * role="alert" so assistive technology announces the result of an action
 * (booking confirmed, payment rejected) without the user hunting for it.
 *
 * @var \MediCareMini\Presentation\View\View $view
 * @var list<array{type:string, message:string}> $flash
 */

declare(strict_types=1);

if (empty($flash)) {
    return;
}
?>
<div class="mx-auto max-w-7xl px-4 pt-5 sm:px-6 lg:px-8" data-flash-region>
    <?php foreach ($flash as $message): ?>
        <?php
        $class = match ($message['type']) {
            'success' => 'alert-success',
            'error'   => 'alert-error',
            'warning' => 'alert-warning',
            default   => 'alert-info',
        };

        $icon = match ($message['type']) {
            'success' => 'M5 13l4 4L19 7',
            'error'   => 'M6 18L18 6M6 6l12 12',
            'warning' => 'M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z',
            default   => 'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
        };
        ?>
        <div class="<?= $class ?> mb-3 animate-fade-up" role="alert">
            <svg class="mt-0.5 h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="<?= $icon ?>"/>
            </svg>
            <span class="flex-1"><?= $view->e($message['message']) ?></span>
            <button type="button" class="shrink-0 opacity-50 transition hover:opacity-100" data-dismiss aria-label="Dismiss">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
    <?php endforeach; ?>
</div>
