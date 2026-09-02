@extends('layouts.app')

@section('title', auth()->user()->isAdmin()
    ? ($servidor->exists ? 'Editar endpoint RPZ' : 'Novo endpoint RPZ')
    : ($servidor->exists ? 'Editar servidor' : 'Novo servidor'))

@php
    $feedUrl = $servidor->exists
        ? ($servidor->tipo_dns === 'mikrotik' ? $servidor->legacyMikrotikEndpointUrl() : $servidor->legacyRpzEndpointUrl())
        : null;
@endphp

@section('content')
    <div @if(auth()->user()->isAdmin()) class="admin-endpoint-form" @endif>
    <div class="page-heading page-heading-compact">
        <div>
            <h1>{{ auth()->user()->isAdmin()
                ? ($servidor->exists ? 'Editar endpoint RPZ' : 'Novo endpoint RPZ')
                : ($servidor->exists ? 'Editar servidor' : 'Novo servidor') }}</h1>
        </div>
    </div>

    @isset($licencaBlocker)
        @if ($licencaBlocker)
            <div class="panel">
                <div class="alert-error" style="margin:0">{{ $licencaBlocker }}</div>
            </div>
        @endif
    @endisset

    <form action="{{ $servidor->exists ? route('servidores.update', $servidor) : route('servidores.store') }}" method="POST">
        @csrf
        @if ($servidor->exists)
            @method('PUT')
        @endif

        <div class="panel form-panel" style="margin-bottom:14px">
            <div class="panel-header"><h2>Identificação</h2></div>
            <div class="form-grid">
                @if (auth()->user()->isAdmin())
                    <div class="field-group">
                        <label for="empresa_id">Empresa</label>
                        <select class="form-control" id="empresa_id" name="empresa_id" required>
                            <option value="">-- selecione --</option>
                            @foreach ($empresas as $empresa)
                                <option value="{{ $empresa->id }}" @selected(old('empresa_id', $servidor->empresa_id) == $empresa->id)>{{ $empresa->nome }}</option>
                            @endforeach
                        </select>
                    </div>
                @else
                    <div class="field-group">
                        <label>Empresa</label>
                        <input class="form-control" type="text" value="{{ $empresas->first()->nome ?? '-' }}" disabled>
                    </div>
                @endif

                <div class="field-group">
                    <label for="nome">Nome</label>
                    <input class="form-control" type="text" id="nome" name="nome" value="{{ old('nome', $servidor->nome) }}" required>
                </div>

                <div class="field-group">
                    <label for="status">Status</label>
                    <select class="form-control" id="status" name="status">
                        <option value="active" @selected(old('status', $servidor->status) === 'active')>Ativo</option>
                        <option value="inactive" @selected(old('status', $servidor->status) === 'inactive')>Inativo</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="panel form-panel" style="margin-bottom:14px">
            <div class="panel-header"><h2>{{ auth()->user()->isAdmin() ? 'Configuração RPZ' : 'Configuração do servidor' }}</h2></div>
            <div class="form-grid">
                <div class="field-group">
                    <label for="tipo_dns">Tipo de DNS</label>
                    @php $tipoDnsAtual = old('tipo_dns', $servidor->tipo_dns ?? 'unbound'); @endphp
                    <select class="form-control" id="tipo_dns" name="tipo_dns">
                        <option value="unbound" @selected($tipoDnsAtual === 'unbound')>Unbound (RPZ)</option>
                        <option value="mikrotik" @selected($tipoDnsAtual === 'mikrotik')>MikroTik RouterOS v7 (Adlist)</option>
                    </select>
                </div>

                <div class="field-group">
                    <label for="bloqueio_modo">Modo de bloqueio</label>
                    <select class="form-control" id="bloqueio_modo" name="bloqueio_modo">
                        <option value="nxdomain" @selected(old('bloqueio_modo', $servidor->bloqueio_modo ?? 'nxdomain') === 'nxdomain')>NXDOMAIN (domínio não existe)</option>
                        <option value="redirect" @selected(old('bloqueio_modo', $servidor->bloqueio_modo) === 'redirect')>Página de bloqueio (redireciona)</option>
                    </select>
                    <p style="color:var(--text-muted);font-size:10px;margin-top:6px">No MikroTik, o Adlist sempre responde com 0.0.0.0; a página de bloqueio só se aplica ao RPZ/Unbound.</p>
                </div>

                <div class="field-group">
                    <label for="ip_v4">IPv4 (opcional)</label>
                    <input class="form-control" type="text" id="ip_v4" name="ip_v4" value="{{ old('ip_v4', $servidor->ip_v4) }}" placeholder="203.0.113.10">
                </div>

                <div class="field-group">
                    <label for="ip_v6">IPv6 (opcional)</label>
                    <input class="form-control" type="text" id="ip_v6" name="ip_v6" value="{{ old('ip_v6', $servidor->ip_v6) }}" placeholder="2001:db8::1">
                </div>
            </div>
            @if (auth()->user()->isAdmin())
                <p class="endpoint-form-note">IPv4 e IPv6 são informativos e não controlam restrição de acesso ao feed RPZ.</p>
            @else
                <p style="color:var(--text-muted);font-size:10px;margin-top:6px">IPv4 e IPv6 são informativos e não controlam o acesso ao feed RPZ.</p>
            @endif
        </div>

        @if ($servidor->exists)
            <div class="panel form-panel" style="margin-bottom:14px">
                <div class="panel-header"><h2>Acesso ao feed</h2></div>
                <div class="field-group">
                    <label>URL do feed</label>
                    @if (auth()->user()->isAdmin())
                        <div class="endpoint-secret-row">
                            <input class="endpoint-code-field" id="rpz-url-value" type="text" value="{{ $feedUrl }}" readonly spellcheck="false">
                            <button type="button" class="button button-secondary" data-copy-target="rpz-url-value" aria-live="polite">Copiar</button>
                        </div>
                    @else
                        <div style="display:flex;gap:8px;align-items:center">
                            <code id="rpz-url-value" style="flex:1;word-break:break-all">{{ $feedUrl }}</code>
                            <button type="button" class="button button-secondary" data-copy-target="rpz-url-value">Copiar</button>
                        </div>
                    @endif
                </div>
                <div class="field-group" style="margin-top:14px">
                    <label>Token</label>
                    <div @if(auth()->user()->isAdmin()) class="endpoint-secret-row" @else style="display:flex;gap:8px;align-items:center" @endif>
                        <code @if(auth()->user()->isAdmin()) class="endpoint-code-field endpoint-token-field" @endif id="token-value">{{ auth()->user()->isAdmin() ? '••••••••••••••••••••••••••••' : '••••••••••••••••••••' }}</code>
                        <button type="button" class="button button-secondary" id="token-toggle-btn">Mostrar</button>
                        <button type="button" class="button button-secondary" data-copy-target="token-value" aria-live="polite">Copiar</button>
                    </div>
                    <p style="color:var(--text-muted);font-size:10px;margin-top:6px">O token dá acesso à zona RPZ {{ auth()->user()->isAdmin() ? 'deste endpoint' : 'deste servidor' }}. Não compartilhe fora do que for necessário.</p>
                </div>
            </div>
        @else
            <p style="color:var(--text-muted);font-size:11px;margin-top:10px">O token é gerado automaticamente ao salvar.</p>
        @endif

        @if (isset($listasDisponiveis) && $listasDisponiveis->isNotEmpty())
            <div class="panel form-panel" style="margin-bottom:14px" id="fontes-habilitadas-panel">
                <div class="panel-header">
                    <h2>{{ auth()->user()->isAdmin() ? 'Fontes habilitadas' : 'Listas habilitadas' }}</h2>
                    <div class="endpoint-sources-heading">
                        @if (auth()->user()->isAdmin())
                            <button type="button" class="inline-link endpoint-selection-action" id="fontes-selecionar-todas">Selecionar todas</button>
                            <button type="button" class="inline-link endpoint-selection-action" id="fontes-limpar-selecao">Limpar seleção</button>
                        @endif
                        <span id="fontes-selecionadas-count"></span>
                    </div>
                </div>
                <input type="text" class="form-control" id="fontes-filtro" placeholder="{{ auth()->user()->isAdmin() ? 'Pesquisar fontes...' : 'Pesquisar listas...' }}" style="margin-bottom:10px">
                <div style="display:grid;gap:6px;max-height:340px;overflow-y:auto">
                    @foreach ($listasDisponiveis as $lista)
                        @php
                            $tipoFonte = $lista->isAnatel()
                                ? 'Catálogo / Importação ANATEL'
                                : ($lista->isExterna() ? 'Externa' : ($lista->empresa_id ? 'Própria' : 'Catálogo'));
                            $tipoListaCliente = $lista->isExterna()
                                ? 'Externa'
                                : ($lista->empresa_id ? 'Própria' : 'Catálogo');
                        @endphp
                        <label class="checkbox-label" data-fonte-nome="{{ strtolower($lista->nome) }}">
                            <input type="checkbox" name="lista_ids[]" value="{{ $lista->id }}" data-fonte-checkbox
                                @checked($servidor->exists && $servidor->listas->contains($lista->id))>
                            <div>
                                <strong>{{ $lista->nome }}</strong>
                                <small>{{ auth()->user()->isAdmin() ? $tipoFonte : $tipoListaCliente }}</small>
                            </div>
                        </label>
                    @endforeach
                </div>
            </div>
        @elseif (auth()->user()->isAdmin() && ! $servidor->exists)
            <p style="color:var(--text-muted);font-size:11px;margin-top:14px">Selecione a empresa e salve para poder escolher as fontes.</p>
        @endif

        <div class="form-actions">
            <a href="{{ route('servidores.index') }}" class="button button-secondary">Cancelar</a>
            <button type="submit" class="button button-primary" @if(!empty($licencaBlocker)) disabled @endif>Salvar</button>
        </div>
    </form>

    <script>
        (function () {
            var isAdminEndpointForm = {{ auth()->user()->isAdmin() ? 'true' : 'false' }};
            var endpointToken = @json($servidor->exists ? $servidor->token : null);
            var maskedEndpointToken = isAdminEndpointForm ? '••••••••••••••••••••••••••••' : '••••••••••••••••••••';
            document.querySelectorAll('[data-copy-target]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var target = document.getElementById(btn.dataset.copyTarget);
                    if (!target) return;
                    var value = target.id === 'token-value' && endpointToken
                        ? endpointToken
                        : ('value' in target ? target.value : target.textContent.trim());
                    navigator.clipboard.writeText(value).then(function () {
                        var original = btn.textContent;
                        btn.textContent = 'Copiado!';
                        setTimeout(function () { btn.textContent = original; }, 1500);
                    });
                });
            });

            var tokenToggle = document.getElementById('token-toggle-btn');
            var tokenValue = document.getElementById('token-value');
            if (tokenToggle && tokenValue && endpointToken) {
                var tokenVisible = false;
                tokenToggle.addEventListener('click', function () {
                    tokenVisible = !tokenVisible;
                    tokenValue.textContent = tokenVisible ? endpointToken : maskedEndpointToken;
                    tokenToggle.textContent = tokenVisible ? 'Ocultar' : 'Mostrar';
                });
            }

            var fontesFiltro = document.getElementById('fontes-filtro');
            var fontesPanel = document.getElementById('fontes-habilitadas-panel');
            var fontesCount = document.getElementById('fontes-selecionadas-count');
            var selecionarTodas = document.getElementById('fontes-selecionar-todas');
            var limparSelecao = document.getElementById('fontes-limpar-selecao');
            if (fontesPanel) {
                var linhas = fontesPanel.querySelectorAll('[data-fonte-nome]');
                var checkboxes = fontesPanel.querySelectorAll('[data-fonte-checkbox]');

                function atualizarContador() {
                    var marcadas = fontesPanel.querySelectorAll('[data-fonte-checkbox]:checked').length;
                    fontesCount.textContent = isAdminEndpointForm
                        ? marcadas + ' de ' + checkboxes.length + ' ' + (marcadas === 1 ? 'selecionada' : 'selecionadas')
                        : marcadas + ' de ' + checkboxes.length + ' selecionadas';
                }

                function definirSelecao(marcado) {
                    checkboxes.forEach(function (checkbox) { checkbox.checked = marcado; });
                    atualizarContador();
                }

                if (selecionarTodas) selecionarTodas.addEventListener('click', function () { definirSelecao(true); });
                if (limparSelecao) limparSelecao.addEventListener('click', function () { definirSelecao(false); });

                if (fontesFiltro) {
                    fontesFiltro.addEventListener('input', function () {
                        var termo = fontesFiltro.value.trim().toLowerCase();
                        linhas.forEach(function (linha) {
                            var nome = linha.getAttribute('data-fonte-nome') || '';
                            linha.style.display = nome.indexOf(termo) === -1 ? 'none' : '';
                        });
                    });
                }

                checkboxes.forEach(function (checkbox) {
                    checkbox.addEventListener('change', atualizarContador);
                });

                atualizarContador();
            }
        })();
    </script>
    </div>
@endsection
