@extends('layouts.app')

@section('title', $lista->exists ? 'Editar fonte' : 'Nova fonte')

@section('content')
    <style>
        .admin-list-form { max-width:1120px; margin:0 auto; }
        .admin-list-form .list-form-breadcrumb { display:flex; align-items:center; gap:12px; margin-bottom:24px; color:var(--text-soft); font-size:12px; }
        .admin-list-form .list-form-breadcrumb a { color:var(--cyan); }
        .admin-list-form .page-eyebrow { color:var(--cyan); font-size:11px; letter-spacing:.14em; }
        .admin-list-form h1 { margin:8px 0; font-size:32px; letter-spacing:-.035em; }
        .admin-list-form .page-heading p { margin:0; color:var(--text-soft); font-size:13px; }
        .admin-list-form > .panel { padding:22px; border-radius:13px; box-shadow:none; }
        .admin-list-form > .panel > form > .form-grid { gap:20px 18px; }
        .admin-list-form .form-control { min-height:46px; padding:12px 14px; border:1px solid var(--border); border-radius:9px; background:var(--surface-soft); color:var(--text); font-size:13px; }
        .admin-list-form .form-control:focus { border-color:var(--cyan); box-shadow:0 0 0 3px var(--cyan-soft); }
        .admin-list-form .list-form-section { padding:18px 0 0; margin-top:20px; border-top:1px solid var(--border-soft); }
        .admin-list-form .list-form-section-label { display:flex; align-items:center; gap:10px; color:var(--text); font-size:13px; font-weight:600; }
        .admin-list-form .list-form-section-label svg { color:var(--cyan); }
        .page-content.admin-refined .admin-list-form .source-compact-field label { font-size:13px; }
        .page-content.admin-refined .admin-list-form .source-compact-select { width:auto; min-width:145px; max-width:100%; height:38px; min-height:38px; padding:7px 30px 7px 11px; align-self:flex-start; font-size:14px; }
        .admin-list-form .source-server-section { min-width:0; margin:24px 0 0; padding:22px 0 0; border:0; border-top:1px solid var(--border-soft); }
        .admin-list-form .source-server-section legend { float:left; width:100%; padding:0; margin-bottom:7px; color:var(--text); font-size:15px; font-weight:600; }
        .admin-list-form .source-server-help { clear:both; margin:0 0 14px; color:var(--text-muted); font-size:13px; line-height:1.7; }
        .admin-list-form .source-server-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:10px; max-height:440px; padding:3px; overflow-y:auto; scrollbar-width:none; }
        .admin-list-form .source-server-grid::-webkit-scrollbar { display:none; }
        .admin-list-form .source-server-card { display:flex; align-items:center; gap:12px; min-width:0; min-height:68px; padding:12px 14px; border:1px solid var(--border); border-radius:10px; background:var(--surface-soft); cursor:pointer; }
        .admin-list-form .source-server-card:hover { border-color:var(--text-muted); }
        .admin-list-form .source-server-card:has(input:checked) { border-color:color-mix(in srgb,var(--cyan) 55%,var(--border)); background:color-mix(in srgb,var(--cyan) 6%,var(--surface)); }
        .admin-list-form .source-server-card:focus-within { outline:2px solid var(--cyan); outline-offset:2px; }
        .admin-list-form .source-server-card input { flex:0 0 16px; width:16px; height:16px; margin:0; accent-color:var(--cyan); }
        .admin-list-form .source-server-icon { display:grid; place-items:center; flex:0 0 34px; height:34px; border:1px solid var(--border-soft); border-radius:9px; background:var(--surface); color:var(--text-muted); }
        .admin-list-form .source-server-card:has(input:checked) .source-server-icon { color:var(--cyan); }
        .admin-list-form .source-server-copy { min-width:0; }
        .admin-list-form .source-server-copy strong { display:block; font-size:14px; font-weight:600; line-height:1.6; color:var(--text); overflow-wrap:anywhere; }
        .admin-list-form .source-server-copy small { display:block; margin-top:3px; color:var(--text-soft); font-size:12px; line-height:1.6; overflow-wrap:anywhere; }
        .admin-list-form .source-server-empty { grid-column:1/-1; margin:0; padding:24px 12px; text-align:center; color:var(--text-muted); font-size:12px; }
        @media (max-width:800px) { .admin-list-form .source-server-grid { grid-template-columns:minmax(0,1fr); } }
        html:not([data-theme="light"]) .admin-list-form { --surface-soft:#0b2130; --border:#234151; --border-soft:#173444; --text-soft:#adc5d9; --cyan:#00cbee; }
        @media (max-width:600px) { .admin-list-form h1 { font-size:27px; } .admin-list-form .page-heading { align-items:flex-start; } .admin-list-form > .panel { padding:18px; } }
    </style>
    <div class="admin-list-form">
    <nav class="list-form-breadcrumb" aria-label="Navegação"><a href="{{ route('listas.index') }}">Fontes</a><x-ui-icon name="chevron" size="14" /><span>{{ $lista->exists ? 'Editar fonte' : 'Nova fonte' }}</span></nav>
    <div class="page-heading page-heading-compact">
        <div>
            <div class="page-eyebrow">Gestão</div>
            <h1>{{ $lista->exists ? 'Editar fonte' : 'Nova fonte' }}</h1>
        </div>
    </div>

    <div class="panel form-panel @if(auth()->user()->isAdmin()) admin-fontes-form @endif">
        <div class="panel-header"><h2><x-ui-icon name="list" size="23" /> Dados da fonte</h2></div>
        <form action="{{ $lista->exists ? route('listas.update', $lista) : route('listas.store') }}" method="POST">
            @csrf
            @if ($lista->exists)
                @method('PUT')
            @endif

            <div class="form-grid">
                <div class="field-group">
                    <label for="empresa_id">Empresa</label>
                    <select class="form-control" id="empresa_id" name="empresa_id">
                        <option value="" @selected(old('empresa_id', $lista->empresa_id) === null)>— Fonte de catálogo (todas as empresas) —</option>
                        @foreach ($empresas as $empresa)
                            <option value="{{ $empresa->id }}" @selected(old('empresa_id', $lista->empresa_id) == $empresa->id)>{{ $empresa->nome }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="field-group">
                    <label for="nome">Nome</label>
                    <input class="form-control" type="text" id="nome" name="nome" value="{{ old('nome', $lista->nome) }}" required>
                </div>

                <div class="field-group field-span-2">
                    <label for="descricao">Descrição</label>
                    <textarea class="form-control admin-fontes-description" id="descricao" name="descricao" rows="3">{{ old('descricao', $lista->descricao) }}</textarea>
                </div>

                <div class="field-group source-compact-field">
                    <label for="status">Status</label>
                    <select class="form-control source-compact-select" id="status" name="status">
                        <option value="active" @selected(old('status', $lista->status) === 'active')>Ativa</option>
                        <option value="inactive" @selected(old('status', $lista->status) === 'inactive')>Inativa</option>
                    </select>
                </div>

                <div class="field-group source-compact-field">
                    <label for="origem">Origem</label>
                    <select class="form-control source-compact-select" id="origem" name="origem">
                        <option value="manual" @selected(old('origem', $lista->origem ?? 'manual') === 'manual')>Manual</option>
                        <option value="externa" @selected(old('origem', $lista->origem) === 'externa')>Feed externo</option>
                        <option value="anatel" @selected(old('origem', $lista->origem) === 'anatel')>ANATEL / PDF</option>
                    </select>
                </div>

                <div class="field-group" id="campo-fonte-url">
                    <label for="fonte_url">URL do feed</label>
                    <input class="form-control" type="url" id="fonte_url" name="fonte_url" value="{{ old('fonte_url', $lista->fonte_url) }}" placeholder="https://exemplo.com/lista-de-bloqueio.txt">
                    <p class="admin-fontes-note">Feeds externos precisam atingir o mínimo de 100 domínios para evitar substituição por conteúdo incompleto.</p>
                </div>

                <div class="field-group" id="campo-fonte-formato">
                    <label for="fonte_formato">Formato do feed</label>
                    <select class="form-control" id="fonte_formato" name="fonte_formato">
                        <option value="hostfile" @selected(old('fonte_formato', $lista->fonte_formato ?? 'hostfile') === 'hostfile')>Hosts file (ex: "127.0.0.1 dominio.com" por linha)</option>
                        <option value="plain" @selected(old('fonte_formato', $lista->fonte_formato) === 'plain')>Lista simples (um domínio por linha)</option>
                        <option value="unbound_local_zone" @selected(old('fonte_formato', $lista->fonte_formato) === 'unbound_local_zone')>Unbound local-zone (ex: linhas "local-zone: "dominio.com" redirect")</option>
                    </select>
                </div>
            </div>

            <script>
                (function () {
                    var origemSelect = document.getElementById('origem');
                    var campoUrl = document.getElementById('campo-fonte-url');
                    var campoFormato = document.getElementById('campo-fonte-formato');

                    function atualizar() {
                        var externa = origemSelect.value === 'externa';
                        campoUrl.style.display = externa ? '' : 'none';
                        campoFormato.style.display = externa ? '' : 'none';
                    }

                    origemSelect.addEventListener('change', atualizar);
                    atualizar();
                })();
            </script>

            @if ($lista->exists)
                <fieldset class="source-server-section" id="source-servers">
                    <legend>Servidores vinculados</legend>
                    <p class="source-server-help">
                        @if (! $lista->empresa_id)
                            Fonte de catálogo: selecione os servidores de cada empresa que devem receber esta lista.
                        @else
                            Selecione os servidores da empresa que devem receber esta lista.
                        @endif
                    </p>
                    <div class="source-server-grid">
                        @forelse ($servidoresDisponiveis as $servidor)
                            <label class="source-server-card">
                                <input type="checkbox" name="servidor_ids[]" value="{{ $servidor->id }}"
                                    @checked($lista->servidores->contains($servidor->id))>
                                <span class="source-server-icon"><x-ui-icon name="server" size="17" /></span>
                                <span class="source-server-copy">
                                    <strong>{{ $servidor->nome }}</strong>
                                    @if (! $lista->empresa_id)
                                        <small>{{ $servidor->empresa->nome }}</small>
                                    @endif
                                </span>
                            </label>
                        @empty
                            <p class="source-server-empty">Nenhum servidor disponível para vincular.</p>
                        @endforelse
                    </div>
                </fieldset>
            @else
                <p style="color:var(--text-muted);font-size:11px;margin-top:14px">Salve a fonte para poder vincular endpoints RPZ.</p>
            @endif

            <div class="form-actions">
                <a href="{{ route('listas.index') }}" class="button button-secondary">Cancelar</a>
                <button type="submit" class="button button-primary">Salvar</button>
            </div>
        </form>
    </div>
    </div>
@endsection
