<?php
/**
 * Patient Detail - Audit tab.
 *
 * Not just target_type='patient' rows (registration, portal access) -
 * every encounter-scoped event belonging to one of this patient's own
 * encounters too (admit/discharge/override, charge/payment posting,
 * clinical note/order/prescription creation), which is what
 * AuditLogger::forPatient() unions together. See that method's own
 * docblock for why a narrower query would make this tab materially
 * incomplete, not just shorter.
 *
 * @var \Aster\Presentation\View\View $view
 * @var list<array<string,mixed>> $auditEntries
 * @var bool $auditScopedToOwn
 */

declare(strict_types=1);

$t = $view->translator;
?>
<div class="flex items-center justify-between">
    <h2 class="text-base font-extrabold text-medical-900">Audit trail</h2>
    <?php if ($auditScopedToOwn): ?>
        <span class="badge">Your own entries only</span>
    <?php endif; ?>
</div>

<?php if ($auditEntries === []): ?>
    <p class="mt-3 text-sm text-slate-500">No audit entries for this patient<?= $auditScopedToOwn ? ' by you' : '' ?> yet.</p>
<?php else: ?>
    <div class="table-wrap mt-4">
        <table class="table">
            <thead><tr><th>When</th><th>Who</th><th>Action</th><th>Detail</th></tr></thead>
            <tbody>
                <?php foreach ($auditEntries as $entry): ?>
                    <tr>
                        <td class="whitespace-nowrap text-xs text-slate-500">
                            <?= $view->e($t->dateTime(new DateTimeImmutable($entry['created_at'] . ' UTC'))) ?>
                        </td>
                        <td class="text-sm font-semibold text-slate-800">
                            <?= $view->e($entry['user_name'] ?? $entry['actor_label'] ?? 'System') ?>
                        </td>
                        <td><span class="badge"><?= $view->e($entry['action']) ?></span></td>
                        <td class="max-w-md text-xs text-slate-600"><?= $view->e($entry['summary'] ?? '') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
