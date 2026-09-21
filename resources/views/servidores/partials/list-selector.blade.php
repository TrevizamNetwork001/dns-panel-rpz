@php
    $selectedListIds = collect(session()->hasOldInput()
        ? old('lista_ids', [])
        : ($servidor->exists ? $servidor->listas->modelKeys() : []))->map(fn ($id) => (string) $id);
    $selectedListCount = $listasDisponiveis->filter(fn ($lista) => $selectedListIds->contains((string) $lista->id))->count();
@endphp
<div class="panel form-panel server-list-selector" data-list-selector>
    <div class="panel-header">
        <h2><x-ui-icon name="list" size="18" />Listas habilitadas</h2>
        <span data-list-count role="status">{{ $selectedListCount }} de {{ $listasDisponiveis->count() }} selecionadas</span>
    </div>
    <p class="server-list-help">Pesquise e adicione listas para este servidor.</p>
    <label class="server-list-search">
        <x-ui-icon name="search" />
        <input type="search" class="form-control" placeholder="Pesquisar e adicionar listas..." aria-label="Pesquisar e adicionar listas" data-list-search autocomplete="off">
    </label>
    <div class="server-list-chips" aria-label="Listas selecionadas">
        @foreach ($listasDisponiveis as $lista)
            @php $selected = $selectedListIds->contains((string) $lista->id); @endphp
            <span class="server-list-chip" data-list-chip="{{ $lista->id }}" @if (! $selected) hidden @endif>
                <x-list-category-icon :lista="$lista" />{{ $lista->nome }}
                <button type="button" data-list-remove="{{ $lista->id }}" aria-label="Remover {{ $lista->nome }}"><x-ui-icon name="x" /></button>
                <input type="hidden" name="lista_ids[]" value="{{ $lista->id }}" @disabled(! $selected)>
            </span>
        @endforeach
        <span class="server-lists-empty" data-selection-empty @if ($selectedListCount) hidden @endif>Nenhuma lista selecionada.</span>
    </div>
    <h3 class="server-catalog-title">Catálogo de listas</h3>
    <div class="server-list-catalog">
        @foreach ($listasDisponiveis as $lista)
            @php $selected = $selectedListIds->contains((string) $lista->id); @endphp
            <div class="server-list-row" data-fonte-nome="{{ mb_strtolower($lista->nome.' '.$lista->descricao) }}">
                <x-list-category-icon :lista="$lista" class="server-catalog-icon" />
                <div class="server-list-copy">
                    <strong>{{ $lista->nome }}</strong>
                    <small class="server-list-origin">{{ $lista->empresa_id ? 'Própria' : 'Catálogo' }}</small>
                    @if ($lista->descricao)<p>{{ $lista->descricao }}</p>@endif
                </div>
                <button type="button" class="button button-secondary server-list-add" data-list-add="{{ $lista->id }}" aria-label="Adicionar {{ $lista->nome }}" @disabled($selected)>
                    <x-ui-icon name="plus" class="server-add-symbol" />
                    <x-ui-icon name="check" class="server-added-symbol" />
                    <span data-list-action-label>{{ $selected ? 'Adicionada' : 'Adicionar' }}</span>
                </button>
            </div>
        @endforeach
        <p class="server-lists-empty" data-search-empty role="status" @if ($listasDisponiveis->isNotEmpty()) hidden @endif>{{ $listasDisponiveis->isEmpty() ? 'Nenhuma lista disponível.' : 'Nenhuma lista encontrada.' }}</p>
    </div>
</div>
<script>
(() => {
    const panel = document.querySelector('[data-list-selector]');
    if (!panel) return;
    const chips = Array.from(panel.querySelectorAll('[data-list-chip]'));
    const buttons = Array.from(panel.querySelectorAll('[data-list-add]'));
    const search = panel.querySelector('[data-list-search]');
    const normalize = value => value.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase('pt-BR');
    function select(id, selected) {
        const chip = chips.find(item => item.dataset.listChip === id);
        const button = buttons.find(item => item.dataset.listAdd === id);
        chip.hidden = !selected;
        chip.querySelector('input').disabled = !selected;
        button.disabled = selected;
        button.querySelector('[data-list-action-label]').textContent = selected ? 'Adicionada' : 'Adicionar';
        const count = chips.filter(item => !item.hidden).length;
        panel.querySelector('[data-list-count]').textContent = `${count} de ${chips.length} selecionadas`;
        panel.querySelector('[data-selection-empty]').hidden = count > 0;
    }
    buttons.forEach(button => button.addEventListener('click', () => {
        select(button.dataset.listAdd, true);
        search.focus();
    }));
    panel.querySelectorAll('[data-list-remove]').forEach(button => button.addEventListener('click', () => {
        select(button.dataset.listRemove, false);
        search.focus();
    }));
    search.addEventListener('input', () => {
        const term = normalize(search.value.trim());
        const rows = Array.from(panel.querySelectorAll('[data-fonte-nome]'));
        rows.forEach(row => { row.hidden = !normalize(row.dataset.fonteNome).includes(term); });
        panel.querySelector('[data-search-empty]').hidden = rows.some(row => !row.hidden);
    });
})();
</script>
