<style>
    .suggestion-dialog { box-sizing: border-box; width: min(480px, calc(100% - 32px)); max-height: calc(100dvh - 32px); overflow-y: auto; padding: 24px; border: 1px solid var(--border); border-radius: 14px; background: var(--surface); color: var(--text); }
    .suggestion-dialog::backdrop { background: color-mix(in srgb, var(--background) 78%, transparent); }
    .suggestion-dialog h2 { margin: 0 0 18px; font-size: 18px; }
    .suggestion-dialog p { color: var(--text-soft); font-size: 13px; line-height: 1.6; overflow-wrap: anywhere; }
    .suggestion-context { display: grid; gap: 12px; margin: 0 0 20px; font-size: 13px; }
    .suggestion-context dt { color: var(--text-muted); font-size: 11px; margin-bottom: 4px; }
    .suggestion-context dd { margin: 0; overflow-wrap: anywhere; white-space: pre-wrap; }
    .suggestion-dialog-actions { display: flex; flex-wrap: wrap; justify-content: flex-end; gap: 10px; margin-top: 24px; }
    .suggestion-dialog .suggestion-reject { color: var(--danger); }
    .suggestion-dialog :focus-visible { outline: 2px solid var(--cyan); outline-offset: 3px; }
    @media (max-width: 480px) {
        .suggestion-dialog { padding: 20px; }
        .suggestion-dialog-actions .button { flex: 1 1 auto; }
    }
</style>
@foreach (['aprovar' => 'Aprovar', 'rejeitar' => 'Rejeitar'] as $action => $label)
    <dialog id="suggestion-{{ $action }}" class="suggestion-dialog" role="dialog" aria-modal="true" aria-labelledby="suggestion-{{ $action }}-title" @if ($action === 'rejeitar') aria-describedby="suggestion-reject-description" @endif>
        <h2 id="suggestion-{{ $action }}-title">{{ $label }} sugestão</h2>
        @if ($action === 'rejeitar')
            <p id="suggestion-reject-description">A sugestão para o domínio <strong data-reject-domain></strong> será marcada como rejeitada.</p>
        @else
            <div data-review-context></div>
        @endif
        <form method="POST">
            @csrf
            @if ($action === 'aprovar')
                <div class="field-group">
                    <label for="suggestion-list">Lista de destino</label>
                    <select id="suggestion-list" name="lista_id" class="form-control" required autofocus>
                        <option value="">Selecione uma lista</option>
                        @foreach ($listasParaAprovar as $lista)
                            <option value="{{ $lista->id }}">{{ $lista->nome }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            <div class="suggestion-dialog-actions">
                <button type="button" class="button button-secondary" data-review-cancel @if ($action === 'rejeitar') autofocus @endif>Cancelar</button>
                <button type="submit" class="button {{ $action === 'aprovar' ? 'button-primary' : 'button-secondary suggestion-reject' }}">{{ $label }} sugestão</button>
            </div>
        </form>
    </dialog>
@endforeach
<script>
    (() => {
        let origin = null;
        document.querySelectorAll('[data-suggestion-action]').forEach(button => {
            button.addEventListener('click', () => {
                const menu = button.closest('.actions-menu');
                const dialog = document.getElementById('suggestion-' + button.dataset.suggestionAction);
                const context = menu.querySelector('template').content.cloneNode(true);
                origin = menu.querySelector('[data-actions-menu-toggle]');
                menu.querySelector('[data-actions-menu-dropdown]').hidden = true;
                origin.setAttribute('aria-expanded', 'false');
                const form = dialog.querySelector('form');
                form.reset();
                form.action = button.dataset.url;
                if (button.dataset.suggestionAction === 'aprovar') {
                    dialog.querySelector('[data-review-context]').replaceChildren(context);
                } else {
                    dialog.querySelector('[data-reject-domain]').textContent = context.querySelector('[data-domain]').textContent;
                }
                dialog.showModal();
                dialog.querySelector('[autofocus]').focus();
            });
        });
        document.querySelectorAll('.suggestion-dialog').forEach(dialog => {
            dialog.querySelector('[data-review-cancel]').addEventListener('click', () => dialog.close());
            dialog.addEventListener('close', () => origin?.focus());
            dialog.addEventListener('click', event => {
                const rect = dialog.getBoundingClientRect();
                if (event.target === dialog && (event.clientX < rect.left || event.clientX > rect.right || event.clientY < rect.top || event.clientY > rect.bottom)) dialog.close();
            });
            dialog.addEventListener('keydown', event => {
                if (event.key !== 'Tab') return;
                const controls = [...dialog.querySelectorAll('select, button')];
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
        });
    })();
</script>
