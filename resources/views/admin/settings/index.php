<?php
/**
 * Clinic settings.
 *
 * Only keys that already exist in system_settings are writable, so adding
 * fields to the POST body cannot inject new configuration.
 *
 * @var \Aster\Presentation\View\View $view
 * @var array<string, list<array<string,mixed>>> $groups
 * @var array<string,int> $mailStats
 */

declare(strict_types=1);

$labels = [
    'general'       => 'Clinic identity',
    'contact'       => 'Contact details',
    'location'      => 'Map & location',
    'localization'  => 'Language & calendar',
    'booking'       => 'Booking rules',
    'notifications' => 'Notifications',
    'uploads'       => 'Uploads',
    'social'        => 'Social links',
];
?>
<form method="post" action="<?= $view->adminUrl('settings') ?>" class="grid gap-6">
    <?= $view->csrfField() ?>

    <?php foreach ($groups as $group => $rows): ?>
        <section class="card-pad">
            <h2 class="text-base font-extrabold text-medical-900">
                <?= $view->e($labels[$group] ?? ucfirst(str_replace('_', ' ', $group))) ?>
            </h2>

            <div class="mt-5 grid gap-5 sm:grid-cols-2">
                <?php foreach ($rows as $row): ?>
                    <?php
                    $key    = (string) $row['setting_key'];
                    $value  = (string) ($row['value'] ?? '');
                    $type   = (string) $row['value_type'];
                    $label  = (string) ($row['label'] ?? $key);
                    $isAm   = str_ends_with($key, '_am');
                    $inputId = 'setting-' . $key;
                    ?>
                    <div class="field <?= $type === 'text' ? 'sm:col-span-2' : '' ?>">
                        <?php if ($type === 'bool'): ?>
                            <?php /* Unchecked checkboxes are absent from the POST, so the key is
                                     also listed in bool_keys and reconciled server-side. */ ?>
                            <input type="hidden" name="bool_keys[]" value="<?= $view->e($key) ?>">
                            <label class="flex items-center gap-2.5 text-sm font-semibold text-slate-700">
                                <input class="checkbox" type="checkbox"
                                       id="<?= $view->e($inputId) ?>"
                                       name="settings[<?= $view->e($key) ?>]" value="1"
                                       <?= $view->attr(in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true), 'checked') ?>>
                                <?= $view->e($label) ?>
                            </label>
                        <?php elseif ($type === 'text'): ?>
                            <label class="label" for="<?= $view->e($inputId) ?>"><?= $view->e($label) ?></label>
                            <textarea class="textarea <?= $isAm ? 'font-ethiopic' : '' ?>"
                                      id="<?= $view->e($inputId) ?>"
                                      name="settings[<?= $view->e($key) ?>]" rows="3"
                                      <?= $isAm ? 'lang="am-ET"' : '' ?>><?= $view->e($value) ?></textarea>
                        <?php else: ?>
                            <label class="label" for="<?= $view->e($inputId) ?>"><?= $view->e($label) ?></label>
                            <input class="input <?= $isAm ? 'font-ethiopic' : '' ?>"
                                   type="<?= $type === 'int' ? 'number' : 'text' ?>"
                                   id="<?= $view->e($inputId) ?>"
                                   name="settings[<?= $view->e($key) ?>]"
                                   value="<?= $view->e($value) ?>"
                                   <?= $isAm ? 'lang="am-ET"' : '' ?>>
                        <?php endif; ?>

                        <span class="hint font-mono text-[10px] text-slate-400"><?= $view->e($key) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endforeach; ?>

    <div class="flex flex-wrap items-center gap-3">
        <button type="submit" class="btn-primary">Save settings</button>
        <a href="<?= $view->adminUrl('settings/mail') ?>" class="btn-secondary">
            Email queue
            <?php if (($mailStats['failed'] ?? 0) > 0): ?>
                <span class="badge border-rose-200 bg-rose-50 text-rose-700"><?= (int) $mailStats['failed'] ?> failed</span>
            <?php endif; ?>
        </a>
        <a href="<?= $view->adminUrl('settings/audit') ?>" class="btn-secondary">Audit trail</a>
    </div>
</form>
