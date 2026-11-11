{{--
    EquipTrack's own confirmation window, used instead of the browser's
    "OK / Cancel" pop-up (Admin and Department layouts).

    Usage (JavaScript):
        if (!(await equipConfirm({
            title: 'Approve request?',
            message: 'The borrower will be notified.',
            confirmText: 'Approve',
            tone: 'primary',      // 'primary' (blue), 'success' (green) or 'danger' (red)
        }))) return;

    Resolves true when the main button is clicked; false on Cancel, Esc or a
    click outside the window.

    With askPassword: true the window also asks for the signed-in account's
    password (for sensitive changes) and resolves with the typed password
    instead of true:
        const password = await equipConfirm({ ..., askPassword: true });
        if (!password) return;
--}}
<div class="et-confirm" id="etConfirm" role="alertdialog" aria-modal="true" aria-labelledby="etConfirmTitle" aria-describedby="etConfirmMessage" hidden>
    <div class="et-confirm-box">
        <div class="et-confirm-icon" id="etConfirmIcon" aria-hidden="true">
            <i class="fa-solid fa-circle-question"></i>
        </div>
        <h3 class="et-confirm-title" id="etConfirmTitle"></h3>
        <p class="et-confirm-message" id="etConfirmMessage"></p>
        <div class="et-confirm-password" id="etConfirmPasswordGroup" hidden>
            <label for="etConfirmPassword">Your password</label>
            <input type="password" id="etConfirmPassword" autocomplete="current-password" placeholder="Enter your password">
            <p class="et-confirm-password-hint" id="etConfirmPasswordHint" hidden>Enter your password to continue.</p>
        </div>
        <div class="et-confirm-actions">
            <button type="button" class="et-confirm-cancel" id="etConfirmCancel">Cancel</button>
            <button type="button" class="et-confirm-ok" id="etConfirmOk">OK</button>
        </div>
    </div>
</div>

<style>
    .et-confirm {
        position: fixed;
        inset: 0;
        z-index: 5000;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 16px;
        background: rgba(15, 23, 42, 0.55);
        backdrop-filter: blur(2px);
        opacity: 0;
        transition: opacity 0.18s ease;
    }

    .et-confirm[hidden] {
        display: none;
    }

    .et-confirm.is-open {
        opacity: 1;
    }

    .et-confirm-box {
        width: 100%;
        max-width: 420px;
        padding: 28px 24px 22px;
        border-radius: 18px;
        border: 1px solid var(--border-color, #e2e8f0);
        background: var(--card-bg, #ffffff);
        box-shadow: 0 24px 60px rgba(15, 23, 42, 0.25);
        text-align: center;
        transform: translateY(8px) scale(0.98);
        transition: transform 0.18s ease;
    }

    .et-confirm.is-open .et-confirm-box {
        transform: none;
    }

    .et-confirm-icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 56px;
        height: 56px;
        margin-bottom: 14px;
        border-radius: 50%;
        font-size: 26px;
    }

    .et-confirm-icon.tone-primary { background: rgba(37, 99, 235, 0.12); color: #2563eb; }
    .et-confirm-icon.tone-success { background: rgba(16, 185, 129, 0.14); color: #059669; }
    .et-confirm-icon.tone-danger  { background: rgba(239, 68, 68, 0.12); color: #dc2626; }

    .et-confirm-title {
        margin: 0 0 8px;
        font-size: 18px;
        font-weight: 700;
        color: var(--text-main, #0f172a);
        overflow-wrap: anywhere;
    }

    .et-confirm-message {
        margin: 0 0 22px;
        font-size: 14px;
        line-height: 1.55;
        color: var(--text-muted, #64748b);
        white-space: pre-line;
        overflow-wrap: anywhere;
    }

    .et-confirm-password {
        margin: -8px 0 20px;
        text-align: left;
    }

    .et-confirm-password[hidden] {
        display: none;
    }

    .et-confirm-password label {
        display: block;
        margin-bottom: 6px;
        font-size: 13px;
        font-weight: 600;
        color: var(--text-main, #0f172a);
    }

    .et-confirm-password input {
        width: 100%;
        box-sizing: border-box;
        min-height: 44px;
        padding: 10px 12px;
        border: 1px solid var(--border-color, #cbd5e1);
        border-radius: 10px;
        background: var(--card-bg, #ffffff);
        color: var(--text-main, #0f172a);
        font-size: 14px;
        font-family: inherit;
    }

    .et-confirm-password input:focus {
        outline: none;
        border-color: #3b82f6;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.25);
    }

    .et-confirm-password input.is-invalid {
        border-color: #dc2626;
    }

    .et-confirm-password-hint {
        margin: 6px 0 0;
        font-size: 12px;
        color: #dc2626;
    }

    .dark-theme .et-confirm-password-hint {
        color: #f87171;
    }

    .et-confirm-actions {
        display: flex;
        justify-content: center;
        gap: 10px;
    }

    .et-confirm-actions button {
        flex: 1 1 0;
        min-height: 44px;
        padding: 10px 18px;
        border-radius: 10px;
        font-size: 14px;
        font-weight: 600;
        font-family: inherit;
        cursor: pointer;
        transition: background-color 0.15s ease, border-color 0.15s ease, transform 0.15s ease;
    }

    .et-confirm-cancel {
        border: 1px solid var(--border-color, #e2e8f0);
        background: transparent;
        color: var(--text-main, #0f172a);
    }

    .et-confirm-cancel:hover {
        background: var(--bg-color, #f1f5f9);
    }

    .et-confirm-ok {
        border: 1px solid transparent;
        color: #ffffff;
    }

    .et-confirm-ok.tone-primary { background: #2563eb; }
    .et-confirm-ok.tone-primary:hover { background: #1d4ed8; }
    .et-confirm-ok.tone-success { background: #059669; }
    .et-confirm-ok.tone-success:hover { background: #047857; }
    .et-confirm-ok.tone-danger  { background: #dc2626; }
    .et-confirm-ok.tone-danger:hover { background: #b91c1c; }

    .et-confirm-actions button:focus-visible {
        outline: none;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.45);
    }

    .dark-theme .et-confirm {
        background: rgba(2, 6, 23, 0.7);
    }

    .dark-theme .et-confirm-box {
        box-shadow: 0 24px 60px rgba(0, 0, 0, 0.55);
    }

    .dark-theme .et-confirm-icon.tone-primary { background: rgba(96, 165, 250, 0.15); color: #60a5fa; }
    .dark-theme .et-confirm-icon.tone-success { background: rgba(52, 211, 153, 0.15); color: #34d399; }
    .dark-theme .et-confirm-icon.tone-danger  { background: rgba(248, 113, 113, 0.15); color: #f87171; }

    .dark-theme .et-confirm-cancel:hover {
        background: rgba(148, 163, 184, 0.12);
    }

    @media (max-width: 480px) {
        .et-confirm-actions {
            flex-direction: column-reverse;
        }
    }

    @media (prefers-reduced-motion: reduce) {
        .et-confirm,
        .et-confirm-box {
            transition: none;
        }
    }
</style>

<script>
    (function () {
        const dialog = document.getElementById('etConfirm');
        const icon = document.getElementById('etConfirmIcon');
        const title = document.getElementById('etConfirmTitle');
        const message = document.getElementById('etConfirmMessage');
        const ok = document.getElementById('etConfirmOk');
        const cancel = document.getElementById('etConfirmCancel');
        const passwordGroup = document.getElementById('etConfirmPasswordGroup');
        const passwordInput = document.getElementById('etConfirmPassword');
        const passwordHint = document.getElementById('etConfirmPasswordHint');
        const ICONS = { primary: 'fa-circle-question', success: 'fa-circle-check', danger: 'fa-triangle-exclamation' };
        let settle = null;
        let returnFocus = null;
        let wantsPassword = false;

        function close(answer) {
            if (!settle) return;
            const done = settle;
            settle = null;
            dialog.classList.remove('is-open');
            setTimeout(() => { if (!settle) dialog.hidden = true; }, 180);
            if (returnFocus && typeof returnFocus.focus === 'function') returnFocus.focus();
            // Never keep a typed password in the page.
            const password = passwordInput.value;
            passwordInput.value = '';
            done(answer && wantsPassword ? password : answer);
        }

        function confirmAnswer() {
            if (wantsPassword && passwordInput.value === '') {
                passwordHint.hidden = false;
                passwordInput.classList.add('is-invalid');
                passwordInput.focus();
                return;
            }
            close(true);
        }

        window.equipConfirm = function ({ title: heading = 'Are you sure?', message: text = '', confirmText = 'OK', cancelText = 'Cancel', tone = 'primary', askPassword = false } = {}) {
            if (settle) close(false);
            tone = ICONS[tone] ? tone : 'primary';

            title.textContent = heading;
            message.textContent = text;
            message.hidden = !text;
            ok.textContent = confirmText;
            cancel.textContent = cancelText;
            ok.className = 'et-confirm-ok tone-' + tone;
            icon.className = 'et-confirm-icon tone-' + tone;
            icon.innerHTML = '<i class="fa-solid ' + ICONS[tone] + '"></i>';

            wantsPassword = !!askPassword;
            passwordGroup.hidden = !wantsPassword;
            passwordInput.value = '';
            passwordHint.hidden = true;
            passwordInput.classList.remove('is-invalid');

            returnFocus = document.activeElement;
            dialog.hidden = false;
            requestAnimationFrame(() => dialog.classList.add('is-open'));
            // Deletes start on Cancel so a stray Enter never deletes anything;
            // with a password field, typing the password comes first.
            (wantsPassword ? passwordInput : (tone === 'danger' ? cancel : ok)).focus();

            return new Promise(resolve => { settle = resolve; });
        };

        ok.addEventListener('click', confirmAnswer);
        cancel.addEventListener('click', () => close(false));
        dialog.addEventListener('click', e => { if (e.target === dialog) close(false); });
        passwordInput.addEventListener('input', () => {
            passwordHint.hidden = true;
            passwordInput.classList.remove('is-invalid');
        });
        document.addEventListener('keydown', e => {
            if (!settle) return;
            if (e.key === 'Escape') {
                e.preventDefault();
                e.stopPropagation();
                close(false);
            } else if (e.key === 'Enter' && document.activeElement === passwordInput) {
                e.preventDefault();
                confirmAnswer();
            } else if (e.key === 'Tab') {
                // Keep keyboard focus inside the window.
                e.preventDefault();
                const stops = wantsPassword ? [passwordInput, cancel, ok] : [cancel, ok];
                const at = stops.indexOf(document.activeElement);
                const step = e.shiftKey ? -1 : 1;
                stops[(at + step + stops.length) % stops.length].focus();
            }
        }, true);
    })();
</script>
