/**
 * MediCareMini - frontend behaviour.
 *
 * Vanilla ES2020, no framework, no build step for this file. Everything here
 * is an enhancement: every page works with JavaScript disabled, and this
 * layer only makes the experience better. That matters for patients on cheap
 * Android handsets over patchy mobile data, where a 200KB framework bundle
 * costs real conversions.
 */
(() => {
    'use strict';

    const $  = (selector, scope = document) => scope.querySelector(selector);
    const $$ = (selector, scope = document) => Array.from(scope.querySelectorAll(selector));

    const csrfToken = () => $('meta[name="csrf-token"]')?.content ?? '';

    /* =================================================================
       Mobile navigation
       ================================================================= */
    const initMenu = () => {
        const toggle = $('[data-menu-toggle]');
        const menu   = $('[data-menu]');

        if (!toggle || !menu) return;

        toggle.addEventListener('click', () => {
            const open = menu.classList.toggle('hidden');
            toggle.setAttribute('aria-expanded', String(!open));
        });

        // Close on Escape, and return focus to the toggle so keyboard users
        // are not stranded at the bottom of the document.
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && !menu.classList.contains('hidden')) {
                menu.classList.add('hidden');
                toggle.setAttribute('aria-expanded', 'false');
                toggle.focus();
            }
        });
    };

    /* =================================================================
       Dismissible flash messages
       ================================================================= */
    const initFlash = () => {
        $$('[data-dismiss]').forEach((button) => {
            button.addEventListener('click', () => button.closest('[role="alert"]')?.remove());
        });
    };

    /* =================================================================
       Copy-to-clipboard (bank account numbers on the payment page)
       ================================================================= */
    const initCopy = () => {
        $$('[data-copy]').forEach((button) => {
            button.addEventListener('click', async () => {
                const value = button.dataset.copy ?? '';
                const original = button.textContent;

                try {
                    await navigator.clipboard.writeText(value);
                } catch {
                    // clipboard API needs a secure context; fall back to the
                    // legacy path so this still works over plain HTTP in dev.
                    const scratch = document.createElement('textarea');
                    scratch.value = value;
                    scratch.setAttribute('readonly', '');
                    scratch.style.position = 'absolute';
                    scratch.style.left = '-9999px';
                    document.body.appendChild(scratch);
                    scratch.select();
                    try { document.execCommand('copy'); } catch { /* nothing more to try */ }
                    scratch.remove();
                }

                button.textContent = button.dataset.copiedLabel ?? 'Copied';
                setTimeout(() => { button.textContent = original; }, 1800);
            });
        });
    };

    /* =================================================================
       Booking form: live availability + live price
       ================================================================= */
    const initBookingForm = () => {
        const form = $('[data-booking-form]');
        if (!form) return;

        const dateInput    = $('[data-date-input]', form);
        const doctorSelect = $('[data-doctor-select]', form);
        const slotGrid     = $('[data-slot-grid]', form);
        const slotHint     = $('[data-slot-hint]', form);

        /* ---------------- Availability ---------------- */

        let availabilityController = null;

        const setSlotState = (label, { available, remaining, label: countLabel }) => {
            const radio = $('[data-slot-radio]', label);
            const count = $('[data-slot-count]', label);

            label.classList.toggle('slot-full', !available);
            radio.disabled = !available;

            // A disabled radio that was selected must be cleared, or the form
            // would submit a slot that has since filled up.
            if (!available && radio.checked) {
                radio.checked = false;
                label.classList.remove('slot-selected');
            }

            if (count) count.textContent = countLabel;
        };

        const loadAvailability = async () => {
            if (!dateInput?.value) return;

            // Cancel any in-flight request: a patient clicking through dates
            // quickly would otherwise get responses out of order.
            availabilityController?.abort();
            availabilityController = new AbortController();

            slotHint && (slotHint.textContent = slotHint.dataset.checking ?? 'Checking availability...');

            const params = new URLSearchParams({ date: dateInput.value });
            if (doctorSelect?.value) params.set('doctor_id', doctorSelect.value);

            try {
                const response = await fetch(`/api/availability?${params}`, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    signal: availabilityController.signal,
                });

                if (!response.ok) throw new Error('Availability request failed');

                const data = await response.json();
                if (!data.ok) throw new Error(data.error ?? 'Unavailable');

                let anyFree = false;

                data.slots.forEach((slot) => {
                    const label = $(`[data-slot="${CSS.escape(slot.value)}"]`, slotGrid);
                    if (!label) return;

                    if (slot.available) anyFree = true;

                    setSlotState(label, {
                        available: slot.available,
                        remaining: slot.remaining,
                        label: slot.available
                            ? (slotHint?.dataset.left ?? `${slot.remaining} left`).replace(':count', slot.remaining)
                            : (slotHint?.dataset.full ?? 'Full'),
                    });
                });

                slotHint && (slotHint.textContent = anyFree
                    ? ''
                    : (slotHint.dataset.none ?? 'No times available on this date. Please choose another day.'));
            } catch (error) {
                if (error.name === 'AbortError') return;

                // Availability could not be checked, so every slot is left
                // enabled: the server re-validates capacity on submit, and
                // blocking the form on a failed enhancement would cost a
                // booking that would otherwise have succeeded.
                slotHint && (slotHint.textContent = '');
                $$('[data-slot]', slotGrid).forEach((label) => {
                    label.classList.remove('slot-full');
                    $('[data-slot-radio]', label).disabled = false;
                });
            }
        };

        /* ---------------- Slot selection styling ---------------- */

        $$('[data-slot]', slotGrid).forEach((label) => {
            const radio = $('[data-slot-radio]', label);

            radio.addEventListener('change', () => {
                $$('[data-slot]', slotGrid).forEach((other) => other.classList.remove('slot-selected'));
                if (radio.checked) label.classList.add('slot-selected');
            });

            if (radio.checked) label.classList.add('slot-selected');
        });

        dateInput?.addEventListener('change', loadAvailability);
        doctorSelect?.addEventListener('change', loadAvailability);

        if (dateInput?.value) loadAvailability();

        /* ---------------- Live price quote ---------------- */

        const panel = $('[data-quote-panel]');

        const loadQuote = async () => {
            if (!panel) return;

            const params = new URLSearchParams();
            const serviceId = $('#service_id', form)?.value;
            const packageId = $('#package_id', form)?.value;
            const doctorId  = doctorSelect?.value;
            const tier      = $('input[name="queue_tier"]:checked', form)?.value;

            if (serviceId) params.set('service_id', serviceId);
            if (packageId) params.set('package_id', packageId);
            if (doctorId)  params.set('doctor_id', doctorId);
            if (tier)      params.set('queue_tier', tier);

            if (!serviceId && !packageId) {
                panel.hidden = true;
                return;
            }

            try {
                const response = await fetch(`/api/quote?${params}`, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                });

                if (!response.ok) return;

                const data = await response.json();
                if (!data.ok) return;

                panel.hidden = data.isFree;

                $('[data-quote-base]', panel).textContent  = data.base;
                $('[data-quote-total]', panel).textContent = data.total;

                const surchargeRow = $('[data-quote-surcharge-row]', panel);
                surchargeRow.hidden = !data.hasSurcharge;
                $('[data-quote-surcharge]', panel).textContent = data.surcharge;

                const depositRow = $('[data-quote-deposit-row]', panel);
                depositRow.hidden = !data.requiresDeposit;
                $('[data-quote-deposit]', panel).textContent = data.deposit;
            } catch {
                // The server recalculates the real price on submit, so a
                // failed quote is cosmetic only.
                panel.hidden = true;
            }
        };

        $$('[data-quote-input]', form).forEach((input) => {
            input.addEventListener('change', loadQuote);
        });

        loadQuote();

        /* ---------------- Double-submit guard ---------------- */

        let submitting = false;

        form.addEventListener('submit', (event) => {
            if (submitting) {
                event.preventDefault();
                return;
            }

            // The DB unique index is the real guarantee; this just stops the
            // patient seeing a confusing "duplicate booking" error after an
            // impatient double-click.
            submitting = true;

            const button = $('[data-submit]', form);
            const label  = $('[data-submit-label]', form);

            if (button) {
                button.disabled = true;
                button.classList.add('opacity-70');
            }

            if (label && label.dataset.busy) label.textContent = label.dataset.busy;

            // Re-enable if the navigation is cancelled (validation failure,
            // back button), so the form is not permanently dead.
            setTimeout(() => {
                submitting = false;
                if (button) {
                    button.disabled = false;
                    button.classList.remove('opacity-70');
                }
            }, 12000);
        });
    };

    /* =================================================================
       Payment page: method selection + file preview
       ================================================================= */
    const initPaymentForm = () => {
        const form = $('[data-payment-form]');
        if (!form) return;

        // Show the matching transfer instructions for the chosen method.
        const syncMethodPanels = () => {
            const selected = $('input[name="payment_method_id"]:checked', form)?.value;

            $$('[data-method-panel]', form).forEach((panel) => {
                panel.hidden = panel.dataset.methodPanel !== selected;
            });

            // Cash at reception needs no upload, so the whole block is hidden
            // rather than leaving a required field the patient cannot satisfy.
            const needsProof = $(`input[name="payment_method_id"][value="${CSS.escape(selected ?? '')}"]`, form)
                ?.dataset.requiresProof === '1';

            const uploadBlock = $('[data-upload-block]', form);
            if (uploadBlock) uploadBlock.hidden = !needsProof;

            $$('[data-proof-required]', form).forEach((field) => {
                field.required = needsProof;
            });
        };

        $$('input[name="payment_method_id"]', form).forEach((radio) => {
            radio.addEventListener('change', syncMethodPanels);
        });

        syncMethodPanels();

        // Filename + size preview so the patient can tell they attached the
        // right photo before submitting.
        const fileInput = $('[data-proof-input]', form);
        const preview   = $('[data-proof-preview]', form);

        fileInput?.addEventListener('change', () => {
            const file = fileInput.files?.[0];

            if (!file || !preview) {
                if (preview) preview.hidden = true;
                return;
            }

            const sizeMb = (file.size / 1048576).toFixed(1);
            const nameEl = $('[data-proof-name]', preview);
            const sizeEl = $('[data-proof-size]', preview);

            if (nameEl) nameEl.textContent = file.name;
            if (sizeEl) sizeEl.textContent = `${sizeMb} MB`;

            preview.hidden = false;

            const maxMb = Number(fileInput.dataset.maxMb ?? '5');
            const warning = $('[data-proof-warning]', preview);

            if (warning) {
                warning.hidden = file.size <= maxMb * 1048576;
            }
        });
    };

    /* =================================================================
       Admin: confirm destructive actions
       ================================================================= */
    const initConfirms = () => {
        const backdrop = $('[data-confirm-dialog]');

        // No dialog markup on this page (or an old cached layout): fall back to
        // the native prompt rather than letting a destructive action through
        // unconfirmed.
        if (!backdrop) {
            $$('[data-confirm]').forEach((el) => {
                const handler = (event) => {
                    if (!window.confirm(el.dataset.confirm)) event.preventDefault();
                };
                el.addEventListener('submit', handler);
                if (el.tagName === 'BUTTON' || el.tagName === 'A') {
                    el.addEventListener('click', handler);
                }
            });
            return;
        }

        const panel     = $('[data-confirm-panel]', backdrop);
        const titleEl   = $('[data-confirm-title]', backdrop);
        const messageEl = $('[data-confirm-message]', backdrop);
        const acceptBtn = $('[data-confirm-accept]', backdrop);
        const cancelBtn = $('[data-confirm-cancel]', backdrop);
        const iconAsk   = $('[data-confirm-icon-ask]', backdrop);
        const iconWarn  = $('[data-confirm-icon-warn]', backdrop);

        let onAccept    = null;   // what to run if the user says yes
        let lastFocused = null;   // so focus can go back where it came from

        /**
         * Destructive or not?
         *
         * An explicit data-confirm-variant wins. Otherwise it is inferred from
         * the trigger's own styling - the delete buttons already carry
         * btn-danger or text-rose-600 - so existing call sites get the right
         * treatment without being touched.
         */
        const variantOf = (trigger) => {
            if (trigger.dataset.confirmVariant) return trigger.dataset.confirmVariant;

            const probe = trigger.tagName === 'FORM'
                ? (trigger.querySelector('button[type="submit"]') || trigger)
                : trigger;

            return /btn-danger|text-rose|text-red/.test(probe.className) ? 'danger' : 'primary';
        };

        const close = () => {
            backdrop.hidden = true;
            document.body.style.removeProperty('overflow');
            onAccept = null;

            // Returning focus is what makes this usable by keyboard: without
            // it, focus falls back to <body> and tabbing restarts at the top
            // of the page.
            if (lastFocused && document.contains(lastFocused)) lastFocused.focus();
            lastFocused = null;
        };

        const open = (trigger, proceed) => {
            const variant = variantOf(trigger);

            messageEl.textContent = trigger.dataset.confirm || 'Are you sure?';
            titleEl.textContent   = trigger.dataset.confirmTitle
                || (variant === 'danger' ? 'Please confirm' : 'Please confirm');

            acceptBtn.textContent = trigger.dataset.confirmAction
                || (variant === 'danger' ? 'Delete' : 'Confirm');

            acceptBtn.className = (variant === 'danger' ? 'btn-danger' : 'btn-primary') + ' flex-1';
            backdrop.dataset.variant = variant;

            if (iconAsk)  iconAsk.hidden  = variant === 'danger';
            if (iconWarn) iconWarn.hidden = variant !== 'danger';

            onAccept    = proceed;
            lastFocused = document.activeElement;

            backdrop.hidden = false;
            // Stop the page scrolling behind the dialog.
            document.body.style.overflow = 'hidden';

            // Cancel takes focus, not Accept: a reflexive Enter should do the
            // harmless thing.
            cancelBtn.focus();
        };

        acceptBtn.addEventListener('click', () => {
            const run = onAccept;
            close();
            if (run) run();
        });

        cancelBtn.addEventListener('click', close);

        // Clicking the dimmed area cancels; clicking inside the panel does not.
        backdrop.addEventListener('mousedown', (event) => {
            if (event.target === backdrop) close();
        });

        document.addEventListener('keydown', (event) => {
            if (backdrop.hidden) return;

            if (event.key === 'Escape') {
                event.preventDefault();
                close();
                return;
            }

            // Focus trap: Tab must not wander into the page behind the dialog.
            if (event.key === 'Tab') {
                const focusable = panel.querySelectorAll(
                    'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])',
                );

                if (!focusable.length) return;

                const first = focusable[0];
                const last  = focusable[focusable.length - 1];

                if (event.shiftKey && document.activeElement === first) {
                    event.preventDefault();
                    last.focus();
                } else if (!event.shiftKey && document.activeElement === last) {
                    event.preventDefault();
                    first.focus();
                }
            }
        });

        // --- Wire the triggers ---
        $$('[data-confirm]').forEach((element) => {
            if (element.tagName === 'FORM') {
                element.addEventListener('submit', (event) => {
                    // Second pass, after the user accepted: let it through.
                    if (element.dataset.confirmed === '1') {
                        delete element.dataset.confirmed;
                        return;
                    }

                    event.preventDefault();

                    open(element, () => {
                        element.dataset.confirmed = '1';

                        // requestSubmit keeps HTML5 validation working (the
                        // rejection form has a required reason); plain submit()
                        // would skip it silently.
                        if (typeof element.requestSubmit === 'function') {
                            element.requestSubmit();
                        } else {
                            element.submit();
                        }
                    });
                });

                return;
            }

            element.addEventListener('click', (event) => {
                event.preventDefault();

                open(element, () => {
                    if (element.tagName === 'A' && element.href) {
                        window.location.href = element.href;
                    } else {
                        element.closest('form')?.requestSubmit?.();
                    }
                });
            });
        });
    };

    /* =================================================================
       Admin: auto-submit filter forms on change
       ================================================================= */
    const initFilters = () => {
        $$('[data-auto-filter]').forEach((form) => {
            $$('select, input[type="date"]', form).forEach((field) => {
                field.addEventListener('change', () => form.submit());
            });
        });
    };

    /* =================================================================
       Print button
       ================================================================= */
    const initPrint = () => {
        $$('[data-print]').forEach((button) => {
            button.addEventListener('click', () => window.print());
        });
    };

    /* =================================================================
       Admin: Role Management - "select all" per permission domain group
       ================================================================= */
    const initPermissionGroups = () => {
        $$('[data-select-group]').forEach((button) => {
            button.addEventListener('click', () => {
                const domain = button.getAttribute('data-select-group');
                const group  = $(`[data-permission-group="${domain}"]`);
                if (!group) return;

                const boxes      = $$('input[type="checkbox"]', group);
                const allChecked = boxes.every((box) => box.checked);
                boxes.forEach((box) => { box.checked = !allChecked; });
            });
        });
    };

    /* =================================================================
       Admin: load a payment method into the edit form
       ================================================================= */
    const initMethodEditor = () => {
        const form = $('[data-method-form]');
        if (!form) return;

        const title = $('[data-method-form-title]', form);
        const reset = $('[data-method-reset]', form);

        const fill = (data) => {
            Object.entries(data).forEach(([key, value]) => {
                const field = form.querySelector(`[name="${CSS.escape(key)}"]`);
                if (!field) return;

                if (field.type === 'checkbox') {
                    field.checked = Boolean(value);
                } else {
                    field.value = value ?? '';
                }
            });
        };

        $$('[data-edit-method]').forEach((button) => {
            button.addEventListener('click', () => {
                let data;
                try {
                    data = JSON.parse(button.dataset.editMethod);
                } catch {
                    return;
                }

                fill(data);
                if (title) title.textContent = `Edit ${data.provider ?? 'method'}`;
                if (reset) reset.hidden = false;
                form.scrollIntoView({ behavior: 'smooth', block: 'start' });
            });
        });

        reset?.addEventListener('click', () => {
            form.reset();
            form.querySelector('[name="id"]').value = '';
            if (title) title.textContent = 'Add a method';
            reset.hidden = true;
        });
    };

    /* =================================================================
       PWA - service worker registration
       ================================================================= */
    const initServiceWorker = () => {
        if (!('serviceWorker' in navigator)) return;

        // Registered from every page (this file loads everywhere), but the
        // worker itself never caches a page - see sw.js's own docblock for
        // why that boundary matters here specifically.
        window.addEventListener('load', () => {
            navigator.serviceWorker.register('/sw.js').catch(() => {
                // Offline support is an enhancement, not a requirement -
                // a registration failure (unsupported browser, blocked by
                // a policy) should never surface as an error to the user.
            });
        });
    };

    /* =================================================================
       Site assistant

       The transcript lives in this closure and nowhere else: not in
       localStorage, not on the server. A visitor may type a symptom into it,
       and nothing here should outlive the tab.
       ================================================================= */
    const initChat = () => {
        const root = $('[data-chat]');

        if (!root) return;

        const panel  = $('[data-chat-panel]', root);
        const log    = $('[data-chat-log]', root);
        const form   = $('[data-chat-form]', root);
        const input  = $('[data-chat-input]', root);
        const send   = $('[data-chat-send]', root);
        const opener = $('[data-chat-open]', root);
        const closer = $('[data-chat-close]', root);

        if (!panel || !log || !form || !input) return;

        const history = [];
        let busy = false;

        const setOpen = (open) => {
            panel.hidden = !open;
            opener.setAttribute('aria-expanded', String(open));
            if (open) input.focus();
        };

        // Written out in full rather than built by concatenation: Tailwind
        // scans this file for class names as literal strings, and a name
        // assembled at runtime gets purged out of the stylesheet.
        const BUBBLE = {
            me:     'chat__msg chat__msg--me',
            bot:    'chat__msg chat__msg--bot',
            urgent: 'chat__msg chat__msg--urgent',
        };

        // textContent throughout: replies are model output and must never be
        // parsed as HTML.
        const bubble = (text, kind) => {
            const el = document.createElement('div');
            el.className = BUBBLE[kind] || BUBBLE.bot;
            el.textContent = text;
            log.appendChild(el);
            log.scrollTop = log.scrollHeight;
            return el;
        };

        opener.addEventListener('click', () => setOpen(panel.hidden));
        closer?.addEventListener('click', () => { setOpen(false); opener.focus(); });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && !panel.hidden) { setOpen(false); opener.focus(); }
        });

        form.addEventListener('submit', async (event) => {
            event.preventDefault();

            const message = input.value.trim();
            if (message === '' || busy) return;

            busy = true;
            send.disabled = true;
            input.value = '';
            bubble(message, 'me');

            const pending = bubble(form.dataset.thinking || '…', 'bot');

            try {
                const body = new URLSearchParams();
                body.set('message', message);
                body.set('history', JSON.stringify(history));
                body.set('_token', csrfToken());

                const response = await fetch('/assistant', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-CSRF-Token': csrfToken(),
                        'Accept': 'application/json',
                    },
                    body,
                });

                const data = await response.json().catch(() => ({}));
                const reply = data.reply || form.dataset.error || 'Error';

                pending.remove();
                bubble(reply, data.urgent ? 'urgent' : 'bot');

                // Only real exchanges become context; an error message would
                // just teach the model to apologise.
                if (!data.fallback && !data.urgent) {
                    history.push({ role: 'user', content: message });
                    history.push({ role: 'assistant', content: reply });
                }
            } catch (error) {
                pending.remove();
                bubble(form.dataset.error || 'Error', 'bot');
            } finally {
                busy = false;
                send.disabled = false;
                input.focus();
            }
        });
    };

    /* =================================================================
       Admin: laboratory result / catalogue grid

       One component, two screens - lab result entry and the test-panel
       editor post the same whole-row shape (rows[i][name], rows[i][value],
       ...), so both are driven from here.

       The flag chip this draws is a PREVIEW. What is stored and what is
       printed is always derived server-side by LabResultFlag::evaluate();
       this exists so a technician sees a value fall out of range as they
       type it, and its absence costs nothing but that.
       ================================================================= */
    const LAB_FLAGS = {
        NORMAL:        { label: 'Normal',        chip: 'chip--built' },
        LOW:           { label: 'Low',           chip: 'chip--partial' },
        HIGH:          { label: 'High',          chip: 'chip--partial' },
        CRITICAL_LOW:  { label: 'Critical low',  chip: 'chip--absent' },
        CRITICAL_HIGH: { label: 'Critical high', chip: 'chip--absent' },
        ABNORMAL:      { label: 'Abnormal',      chip: 'chip--absent' },
    };

    /** Mirrors LabResultFlag::evaluate(). Keep the two in step. */
    const labFlag = (value, min, max, text) => {
        const raw = (value ?? '').trim();
        if (raw === '') return null;

        const lower   = raw.toLowerCase();
        const hasMin  = min !== null && min !== undefined && min !== '' && !Number.isNaN(Number(min));
        const hasMax  = max !== null && max !== undefined && max !== '' && !Number.isNaN(Number(max));
        const numeric = Number(raw);

        if (Number.isNaN(numeric) || (!hasMin && !hasMax)) {
            for (const marker of ['positive', 'reactive', 'abnormal', 'detected']) {
                if (lower.includes(marker)) {
                    return new RegExp(`\\b(non|not|no)[\\s-]*${marker}`).test(lower)
                        ? LAB_FLAGS.NORMAL
                        : LAB_FLAGS.ABNORMAL;
                }
            }

            const reference = (text ?? '').trim().toLowerCase();
            if (reference === '') return LAB_FLAGS.NORMAL;

            return lower === reference ? LAB_FLAGS.NORMAL : LAB_FLAGS.ABNORMAL;
        }

        // A bound of exactly zero has no meaningful percentage to exceed,
        // so magnitude is never escalated off it - same rule as the PHP.
        const beyond = (excess, bound) => Math.abs(bound) !== 0 && (excess / Math.abs(bound)) > 0.25;

        if (hasMin && numeric < Number(min)) {
            return beyond(Number(min) - numeric, Number(min)) ? LAB_FLAGS.CRITICAL_LOW : LAB_FLAGS.LOW;
        }

        if (hasMax && numeric > Number(max)) {
            return beyond(numeric - Number(max), Number(max)) ? LAB_FLAGS.CRITICAL_HIGH : LAB_FLAGS.HIGH;
        }

        return LAB_FLAGS.NORMAL;
    };

    const initLabGrid = () => {
        $$('[data-lab-entry]').forEach((form) => {
            const body     = $('[data-lab-rows]', form);
            const template = $('[data-lab-row-template]');

            if (!body || !template) return;

            // Start above every index already rendered, so a new row can
            // never collide with an existing one and overwrite it on POST.
            let nextIndex = $$('[data-lab-row]', body).length;

            const buildRow = (index) => {
                const host = document.createElement('tbody');
                host.innerHTML = template.innerHTML.replaceAll('__INDEX__', String(index));

                return host.firstElementChild;
            };

            const paintFlag = (row) => {
                const chip = $('[data-lab-flag]', row);
                if (!chip) return;

                const flag = labFlag(
                    $('[data-lab-value]', row)?.value,
                    $('[data-lab-min]', row)?.value,
                    $('[data-lab-max]', row)?.value,
                    $('[data-lab-text]', row)?.value,
                );

                if (!flag) {
                    chip.hidden = true;
                    return;
                }

                chip.hidden = false;
                chip.className = flag.chip;
                chip.textContent = flag.label;
            };

            const addRow = (values = {}) => {
                const row = buildRow(nextIndex);
                nextIndex += 1;

                Object.entries(values).forEach(([field, value]) => {
                    const input = $(`[name$="[${field}]"]`, row);
                    if (input && value !== null && value !== undefined) input.value = String(value);
                });

                body.appendChild(row);
                paintFlag(row);

                return row;
            };

            $('[data-lab-add]', form)?.addEventListener('click', () => {
                addRow().querySelector('input')?.focus();
            });

            // Delegated: rows come and go, and rebinding per row would
            // leak listeners every time a preset is loaded.
            body.addEventListener('click', (event) => {
                const remove = event.target.closest('[data-lab-remove]');
                if (remove) remove.closest('[data-lab-row]')?.remove();
            });

            body.addEventListener('input', (event) => {
                const row = event.target.closest('[data-lab-row]');
                if (row) paintFlag(row);
            });

            // Preset loader - replaces the grid from the test directory.
            const loader = $('[data-lab-preset-load]', form);
            const picker = $('[data-lab-preset]', form);

            loader?.addEventListener('click', async () => {
                const code = picker?.value;
                if (!code) return;

                loader.disabled = true;

                try {
                    const response = await fetch(`${loader.dataset.endpoint}/${encodeURIComponent(code)}/parameters`, {
                        headers: { 'Accept': 'application/json' },
                    });
                    const data = await response.json();

                    if (!data.ok || !Array.isArray(data.rows)) return;

                    body.innerHTML = '';
                    data.rows.forEach((row) => addRow(row));

                    const heading = $('#report_title', form);
                    if (heading && data.title) heading.value = data.title;
                } catch (error) {
                    // Leave the grid exactly as it was: silently emptying it
                    // because a fetch failed would look like the preset had
                    // loaded and contained nothing.
                } finally {
                    loader.disabled = false;
                }
            });

            $$('[data-lab-row]', body).forEach(paintFlag);
        });
    };

    /* =================================================================
       Admin: requisition - specimen follows the chosen panel

       Only while the field still holds the previous panel default. Once a
       technician types their own specimen description, changing the panel
       must not quietly overwrite it.
       ================================================================= */
    const initLabPanelPicker = () => {
        const picker = $('[data-lab-panel-picker]');
        const target = $('[data-lab-specimen-target]');

        if (!picker || !target) return;

        let applied = target.value;

        picker.addEventListener('change', () => {
            const specimen = picker.selectedOptions[0]?.dataset.specimen ?? '';

            if (target.value === applied || target.value === '') {
                target.value = specimen;
                applied = specimen;
            }
        });
    };

    /* =================================================================
       Boot
       ================================================================= */
    const boot = () => {
        initChat();
        initMenu();
        initFlash();
        initCopy();
        initPrint();
        initBookingForm();
        initPaymentForm();
        initConfirms();
        initFilters();
        initMethodEditor();
        initPermissionGroups();
        initLabGrid();
        initLabPanelPicker();
        initServiceWorker();
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
