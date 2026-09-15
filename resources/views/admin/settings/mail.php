<?php
/**
 * Email queue health.
 *
 * The queue is what makes reminders reliable: if the cron worker stops, this
 * page is where that becomes visible before patients stop being reminded.
 *
 * @var \Aster\Presentation\View\View $view
 * @var array<string,int> $stats
 * @var list<array<string,mixed>> $failures
 * @var string $driver
 */

declare(strict_types=1);

$t = $view->translator;
?>
<?php if ($driver === 'log'): ?>
    <div class="alert-warning" role="note">
        <svg class="mt-0.5 h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
        </svg>
        <span>
            <b class="block">No email is being sent</b>
            MAIL_MAILER is set to <code class="font-mono">log</code>, so messages are written to
            storage/logs/mail.log instead of being delivered. Set it to <code class="font-mono">smtp</code> for production.
        </span>
    </div>
<?php endif; ?>

<div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
    <?php
    $cards = [
        ['Queued',  $stats['queued']  ?? 0, 'text-sky-700'],
        ['Sending', $stats['sending'] ?? 0, 'text-amber-700'],
        ['Sent',    $stats['sent']    ?? 0, 'text-emerald-700'],
        ['Failed',  $stats['failed']  ?? 0, 'text-rose-700'],
    ];
    foreach ($cards as [$label, $count, $class]):
    ?>
        <div class="stat-card">
            <span class="stat-label"><?= $view->e($label) ?></span>
            <b class="mt-2 block text-3xl font-extrabold <?= $class ?>"><?= (int) $count ?></b>
        </div>
    <?php endforeach; ?>
</div>

<div class="card-pad mt-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-base font-extrabold text-medical-900">Delivery</h2>
            <p class="mt-1 text-xs text-slate-500">
                The queue is drained by <code class="font-mono">php bin/queue-worker.php</code>, which should run
                from cron every few minutes.
            </p>
        </div>
        <form method="post" action="<?= $view->adminUrl('settings/mail/test') ?>">
            <?= $view->csrfField() ?>
            <button type="submit" class="btn-secondary btn-sm">Test connection</button>
        </form>
    </div>
</div>

<div class="card mt-6">
    <div class="border-b border-slate-100 px-5 py-4">
        <h2 class="text-base font-extrabold text-medical-900">Failed messages</h2>
    </div>

    <?php if ($failures === []): ?>
        <p class="p-10 text-center text-sm text-slate-500">No failures. Everything has been delivered.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr><th>Type</th><th>Recipient</th><th>Subject</th><th>Attempts</th><th>Error</th><th></th></tr>
                </thead>
                <tbody>
                    <?php foreach ($failures as $failure): ?>
                        <tr>
                            <td class="font-mono text-xs text-slate-500"><?= $view->e($failure['mailable']) ?></td>
                            <td class="text-slate-700"><?= $view->e($failure['to_email']) ?></td>
                            <td class="max-w-xs truncate text-slate-600"><?= $view->e($failure['subject']) ?></td>
                            <td class="text-center text-slate-500"><?= (int) $failure['attempts'] ?></td>
                            <td class="max-w-sm truncate text-xs text-rose-600"><?= $view->e($failure['last_error'] ?? '') ?></td>
                            <td class="text-right">
                                <form method="post" action="<?= $view->adminUrl('settings/mail/' . (int) $failure['id'] . '/retry') ?>">
                                    <?= $view->csrfField() ?>
                                    <button type="submit" class="btn-ghost btn-sm text-medical-700">Retry</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
