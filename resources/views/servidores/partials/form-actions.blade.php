        <div class="form-actions">
            @if ($servidor->exists && ! auth()->user()->isAdmin())
                <button type="submit" form="delete-server-form" class="button button-secondary server-delete"><x-ui-icon name="trash" />Excluir servidor</button>
            @endif
            <a href="{{ route('servidores.index') }}" class="button button-secondary">Cancelar</a>
            <button type="submit" class="button button-primary" @if(!empty($licencaBlocker)) disabled @endif><x-ui-icon name="check" />{{ auth()->user()->isAdmin() ? 'Salvar' : ($servidor->exists ? 'Salvar alterações' : 'Cadastrar servidor') }}</button>
        </div>
