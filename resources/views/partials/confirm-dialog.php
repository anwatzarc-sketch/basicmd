<?php
/**
 * Branded confirmation dialog.
 *
 * Rendered once per layout and reused by every `data-confirm` trigger, so the
 * native window.confirm() - which cannot be styled, says "localhost:9000 says",
 * and looks nothing like the rest of the product - never appears.
 *
 * Progressive enhancement: with JavaScript disabled this markup stays hidden
 * and the form simply submits, which is the safe outcome. The dialog is a
 * speed bump, never the thing that enforces a rule - the server re-checks
 * every state transition regardless.
 *
 * @var \MediCareMini\Presentation\View\View $view
 */

declare(strict_types=1);
?>
<div class="confirm-backdrop" data-confirm-dialog hidden>
    <div class="confirm-panel"
         role="dialog"
         aria-modal="true"
         aria-labelledby="confirm-title"
         aria-describedby="confirm-message"
         data-confirm-panel>

        <div class="confirm-icon" data-confirm-icon aria-hidden="true">
            <!-- Question mark: neutral confirmations -->
            <svg class="h-6 w-6" data-confirm-icon-ask fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <!-- Warning triangle: destructive confirmations -->
            <svg class="h-6 w-6" data-confirm-icon-warn fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" hidden>
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
            </svg>
        </div>

        <h2 class="confirm-title" id="confirm-title" data-confirm-title>Please confirm</h2>
        <p class="confirm-message" id="confirm-message" data-confirm-message></p>

        <div class="confirm-actions">
            <?php /* Cancel first in the DOM so it takes initial focus: the safe
                     option should be the one a hurried Enter press selects. */ ?>
            <button type="button" class="btn-secondary flex-1" data-confirm-cancel>
                <?= $view->t('form.cancel') ?>
            </button>
            <button type="button" class="btn-primary flex-1" data-confirm-accept>
                <?= $view->t('form.yes') ?>
            </button>
        </div>
    </div>
</div>
