<?php
/**
 * Site assistant widget.
 *
 * Rendered only when a model is configured, so an unconfigured install shows
 * nothing rather than a button that fails when pressed.
 *
 * No inline handlers and no inline styles: the CSP allows neither. Behaviour
 * lives in app.js and hangs off the data- attributes below, the same way every
 * other interactive piece in this codebase works.
 *
 * @var \Aster\Presentation\View\View $view
 */

declare(strict_types=1);
?>
<div class="chat" data-chat>
    <button type="button" class="chat__launcher" data-chat-open
            aria-expanded="false" aria-controls="chat-panel">
        <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round"
                  d="M8 10h8M8 14h5m7-1c0 4.4-3.9 8-8.7 8-1 0-2-.2-2.9-.5L4 22l1.6-3.2A7.6 7.6 0 0 1 3 13c0-4.4 3.9-8 8.7-8s8.3 3.6 8.3 8z"/>
        </svg>
        <span class="sr-only"><?= $view->t('chat.open') ?></span>
    </button>

    <?php /* hidden (not display utilities) so the attribute is the single
             source of truth for open/closed, for CSS and for JS alike. */ ?>
    <section id="chat-panel" class="chat__panel" data-chat-panel hidden
             aria-label="<?= $view->e($view->tRaw('chat.title')) ?>">
        <header class="chat__header">
            <h2 class="chat__title"><?= $view->t('chat.title') ?></h2>
            <button type="button" class="chat__close" data-chat-close>
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                </svg>
                <span class="sr-only"><?= $view->t('chat.close') ?></span>
            </button>
        </header>

        <p class="chat__disclaimer"><?= $view->t('chat.disclaimer') ?></p>

        <?php /* aria-live so a screen reader hears replies as they arrive,
                 polite so it waits for a pause rather than interrupting. */ ?>
        <div class="chat__log" data-chat-log role="log" aria-live="polite" aria-atomic="false">
            <div class="chat__msg chat__msg--bot">
                <span class="sr-only"><?= $view->t('chat.assistant') ?>:</span>
                <?= $view->t('chat.greeting') ?>
            </div>
        </div>

        <?php /* The transient strings the script needs, resolved server-side
                 so the widget speaks the page's language without shipping a
                 translation table to the browser. */ ?>
        <form class="chat__form" data-chat-form
              data-thinking="<?= $view->e($view->tRaw('chat.thinking')) ?>"
              data-error="<?= $view->e($view->tRaw('chat.error')) ?>">
            <label class="sr-only" for="chat-input"><?= $view->t('chat.placeholder') ?></label>
            <input class="chat__input" id="chat-input" name="message" type="text"
                   autocomplete="off" maxlength="600"
                   placeholder="<?= $view->e($view->tRaw('chat.placeholder')) ?>"
                   data-chat-input>
            <button type="submit" class="chat__send" data-chat-send>
                <span class="sr-only"><?= $view->t('chat.send') ?></span>
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14M13 6l6 6-6 6"/>
                </svg>
            </button>
        </form>
    </section>
</div>
