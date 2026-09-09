<style>
    .user-password-dialog { width: min(480px, calc(100% - 32px)); max-height: calc(100dvh - 32px); padding: 24px; border: 1px solid var(--border); border-radius: 14px; background: var(--surface); color: var(--text); }
    .user-password-dialog::backdrop { background: color-mix(in srgb, var(--background) 78%, transparent); }
    .user-password-dialog h2 { margin: 0 0 14px; font-size: 18px; }
    .user-password-dialog p { font-size: 13px; line-height: 1.6; color: var(--text-soft); }
    .user-password-dialog .user-password-note { font-size: 11px; color: var(--text-muted); }
    .user-password-dialog .user-password-actions { display: flex; flex-wrap: wrap; justify-content: flex-end; gap: 10px; margin-top: 24px; }
    .user-password-dialog .user-password-field { display: block; margin-top: 20px; font-size: 12px; color: var(--text-soft); }
    .user-password-dialog .user-password-value { display: block; width: 100%; min-width: 0; margin-top: 8px; padding: 12px; border: 1px solid var(--border); border-radius: 8px; background: var(--surface-soft); color: var(--text); font: 14px ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; }
    .user-password-dialog :focus-visible { outline: 2px solid var(--cyan); outline-offset: 3px; }
    @media (max-width: 480px) {
        .user-password-dialog { padding: 20px; }
        .user-password-dialog .user-password-actions .button { flex: 1 1 auto; }
    }
</style>

<dialog class="user-password-dialog" id="user-password-confirm" role="dialog" aria-modal="true" aria-labelledby="user-password-confirm-title" aria-describedby="user-password-confirm-description user-password-confirm-note">
    <h2 id="user-password-confirm-title">Redefinir senha</h2>
    <p id="user-password-confirm-description">Será gerada uma nova senha temporária para este usuário.</p>
    <p class="user-password-note" id="user-password-confirm-note">O usuário deverá usar essa senha no próximo acesso conforme as regras atuais do sistema.</p>
    <div class="user-password-actions">
        <button type="button" class="button button-secondary" id="user-password-cancel" autofocus>Cancelar</button>
        <button type="button" class="button button-primary" id="user-password-generate">Gerar senha temporária</button>
    </div>
</dialog>

@if ($passwordReset)
    <dialog class="user-password-dialog" id="user-password-result" role="dialog" aria-modal="true" aria-labelledby="user-password-result-title" aria-describedby="user-password-result-description">
        <h2 id="user-password-result-title">Senha temporária gerada</h2>
        <p id="user-password-result-description">Esta senha será exibida somente agora. Copie e envie ao usuário por um canal seguro.</p>
        <label class="user-password-field" for="user-password-value">Senha temporária</label>
        <input class="user-password-value" id="user-password-value" type="text" value="{{ $passwordReset['password'] }}" readonly autocomplete="off" spellcheck="false" autocapitalize="none">
        <p class="user-password-note" id="user-password-copy-feedback" role="status" aria-live="polite"></p>
        <div class="user-password-actions">
            <button type="button" class="button button-secondary" id="user-password-copy" aria-label="Copiar senha" autofocus>Copiar</button>
            <button type="button" class="button button-primary" id="user-password-done">Concluir</button>
        </div>
    </dialog>
@endif

<script>
    (() => {
        const confirmation = document.getElementById('user-password-confirm');
        let pendingForm = null;
        let origin = null;
        const restoreFocus = () => {
            if (origin && origin.isConnected) origin.focus();
        };
        const configureDialog = dialog => {
            dialog.addEventListener('keydown', event => {
                if (event.key !== 'Tab') return;
                const controls = [...dialog.querySelectorAll('button:not(:disabled), input:not(:disabled), [tabindex="0"]')];
                const first = controls[0];
                const last = controls[controls.length - 1];
                if (event.shiftKey && document.activeElement === first) {
                    event.preventDefault();
                    last.focus();
                } else if (!event.shiftKey && document.activeElement === last) {
                    event.preventDefault();
                    first.focus();
                }
            });
            dialog.addEventListener('click', event => {
                const rect = dialog.getBoundingClientRect();
                if (event.target === dialog && (event.clientX < rect.left || event.clientX > rect.right || event.clientY < rect.top || event.clientY > rect.bottom)) dialog.close();
            });
        };
        configureDialog(confirmation);
        document.querySelectorAll('.user-password-reset-form').forEach(form => {
            form.addEventListener('submit', event => {
                event.preventDefault();
                pendingForm = form;
                origin = form.closest('.actions-menu').querySelector('.actions-menu-toggle');
                confirmation.showModal();
                document.getElementById('user-password-cancel').focus();
            });
        });
        document.getElementById('user-password-cancel').addEventListener('click', () => confirmation.close());
        confirmation.addEventListener('close', () => {
            pendingForm = null;
            restoreFocus();
        });
        document.getElementById('user-password-generate').addEventListener('click', event => {
            if (!pendingForm) return;
            event.currentTarget.disabled = true;
            const form = pendingForm;
            confirmation.close();
            HTMLFormElement.prototype.submit.call(form);
        });

        const result = document.getElementById('user-password-result');
        if (!result) return;
        const field = document.getElementById('user-password-value');
        const copy = document.getElementById('user-password-copy');
        const feedback = document.getElementById('user-password-copy-feedback');
        let copyTimer;
        const discardPassword = () => {
            clearTimeout(copyTimer);
            field.value = '';
            field.removeAttribute('value');
            result.remove();
        };
        const resultOrigin = document.getElementById(@json('user-password-reset-'.($passwordReset['user_id'] ?? '')));
        origin = resultOrigin?.closest('.actions-menu').querySelector('.actions-menu-toggle') || document.querySelector('.page-actions a');
        result.addEventListener('close', () => {
            discardPassword();
            restoreFocus();
        });
        window.addEventListener('pagehide', discardPassword, { once: true });
        configureDialog(result);
        document.getElementById('user-password-done').addEventListener('click', () => result.close());
        copy.addEventListener('click', async () => {
            if (!result.open || !field.value) return;
            try {
                if (!navigator.clipboard?.writeText) throw new Error('Clipboard unavailable');
                await navigator.clipboard.writeText(field.value);
                if (!result.isConnected) return;
                copy.textContent = 'Copiada';
                feedback.textContent = 'Senha copiada.';
                clearTimeout(copyTimer);
                copyTimer = setTimeout(() => {
                    copy.textContent = 'Copiar';
                    feedback.textContent = '';
                }, 1500);
            } catch {
                if (!result.isConnected) return;
                field.focus();
                field.select();
                feedback.textContent = 'Cópia automática indisponível. Copie a senha selecionada usando o comando de copiar do seu dispositivo.';
            }
        });
        result.showModal();
        copy.focus();
    })();
</script>
