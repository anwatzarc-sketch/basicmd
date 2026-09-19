<?php
/**
 * Booking funnel progress: details -> payment -> done.
 *
 * Shared by all three funnel screens so a patient always knows how far along
 * they are; the payment screen used to be the only step that showed it.
 *
 * @var \Aster\Presentation\View\View $view
 * @var int                           $current 1, 2 or 3
 */

declare(strict_types=1);

$current = $current ?? 1;

$steps = [
    1 => 'booking.step_details',
    2 => 'booking.step_payment',
    3 => 'booking.step_done',
];
?>
<ol class="mb-10 flex items-center gap-2 text-xs font-bold" aria-label="Booking progress">
    <?php foreach ($steps as $number => $key): ?>
        <?php if ($number > 1): ?>
            <li class="h-px w-6 <?= $number <= $current ? 'bg-medical-300' : 'bg-slate-200 dark:bg-slate-700' ?>" aria-hidden="true"></li>
        <?php endif; ?>

        <li class="flex items-center gap-2 <?= $number <= $current ? 'text-medical-700 dark:text-medical-300' : 'text-slate-500 dark:text-slate-400' ?>"
            <?= $number === $current ? 'aria-current="step"' : '' ?>>
            <span class="grid h-6 w-6 shrink-0 place-items-center rounded-full <?= $number <= $current
                ? 'bg-medical-600 text-white'
                : 'bg-slate-200 text-slate-500 dark:bg-slate-700 dark:text-slate-300' ?>">
                <?= $number < $current ? '&#10003;' : $number ?>
            </span>
            <?= $view->t($key) ?>
        </li>
    <?php endforeach; ?>
</ol>
