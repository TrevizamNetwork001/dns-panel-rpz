@extends('layouts.app')

@section('title', $servidor->nome)

@php
    $panelHost = parse_url(config('app.url'), PHP_URL_HOST) ?: 'localhost';
    $rpzUrl = $servidor->preferredRpzEndpointUrl();
    $legacyRpzUrl = $servidor->legacyRpzEndpointUrl();
    $usesCompanyEndpoint = $servidor->empresa->rpz_slug !== null;
    $rpzZoneName = $panelHost;
    $rpzZonefile = '/var/lib/unbound/'.$rpzZoneName.'.zone';
    $configSnippet = "rpz:\n    name: \"{$rpzZoneName}\"\n    zonefile: \"{$rpzZonefile}\"\n    url: \"{$rpzUrl}\"\n    rpz-log: yes\n    rpz-log-name: \"dns-panel-rpz\"";
    $diasSemConsulta = $servidor->diasSemSincronizar();
@endphp

@section('content')
    <div class="page-heading">
        <div>
            <div class="page-eyebrow">Endpoint RPZ</div>
            <h1>{{ $servidor->nome }}</h1>
            <p><a href="{{ route('empresas.show', $servidor->empresa) }}" class="inline-link">{{ $servidor->empresa->nome }}</a></p>
        </div>
        <div class="page-actions">
            <a href="{{ route('servidores.edit', $servidor) }}" class="button button-secondary">Editar</a>
        </div>
    </div>

    <div class="details-grid">
        <div class="panel">
            <div class="panel-header"><h2>Status</h2></div>
            @if (auth()->user()->isAdmin())
                @php
                    $estadoOperacional = $diasSemConsulta === null ? 'Sem consulta' : ($diasSemConsulta >= 2 ? 'Atenção' : 'Normal');
                    $classeOperacional = $diasSemConsulta === null ? 'is-muted' : ($diasSemConsulta >= 2 ? 'is-warning' : 'is-active');
                @endphp
                <div class="endpoint-detail-statuses">
                    <div><span>Status cadastral</span><strong class="status-pill {{ $servidor->status === 'active' ? 'is-active' : 'is-inactive' }}">{{ $servidor->status === 'active' ? 'Ativo' : 'Inativo' }}</strong></div>
                    <div><span>Estado operacional</span><strong class="status-pill {{ $classeOperacional }}">{{ $estadoOperacional }}</strong></div>
                </div>
            @else
                <span class="status-pill @if($servidor->status === 'active') is-active @else is-inactive @endif">
                    {{ $servidor->status === 'active' ? 'Ativo' : 'Inativo' }}
                </span>
            @endif
            <dl class="details-list" style="margin-top:14px">
                <div><dt>Última consulta</dt><dd>{{ auth()->user()->isAdmin() ? \App\Http\Controllers\DashboardController::relativoPt($servidor->last_synced_at) : (optional($servidor->last_synced_at)->format('d/m/Y H:i:s') ?? 'nunca') }}</dd></div>
                <div><dt>Modo de bloqueio</dt><dd>{{ $servidor->bloqueio_modo === 'redirect' ? 'Página de bloqueio' : 'NXDOMAIN' }}</dd></div>
            </dl>
        </div>

        @if (auth()->user()->isAdmin())
        <div class="panel details-card-wide admin-endpoint-access">
            <div class="panel-header"><h2>Acesso RPZ</h2></div>
            <dl class="details-list" style="margin-bottom:14px">
                <div><dt>Método</dt><dd>{{ $usesCompanyEndpoint ? 'ACL por IP' : 'Token legado (fallback)' }}</dd></div>
                <div><dt>Status da ACL</dt><dd>{{ $servidor->allowedIps->where('status', 'active')->isNotEmpty() ? 'Configurada' : 'Sem IP autorizado' }}</dd></div>
                <div><dt>IPs autorizados</dt><dd>{{ $servidor->allowedIps->where('status', 'active')->pluck('ip_cidr')->join(', ') ?: 'Nenhum' }}</dd></div>
            </dl>
            <div class="field-group">
                <label>{{ $usesCompanyEndpoint ? 'URL curta' : 'URL RPZ de fallback' }}</label>
                <div class="endpoint-secret-row">
                    <input class="endpoint-code-field" id="detail-rpz-url" type="text" value="{{ $rpzUrl }}" readonly spellcheck="false">
                    <button type="button" class="button button-secondary" data-endpoint-copy="detail-rpz-url" aria-live="polite">Copiar</button>
                </div>
            </div>
        </div>
        @endif

        <div class="panel details-card-wide">
            <div class="panel-header">
                <h2>Configuração do Unbound</h2>
                <button type="button" class="button button-secondary" id="copy-config-btn" data-copy-target="config-snippet">Copiar</button>
            </div>
            <p style="color:var(--text-muted);font-size:11px;margin:0 0 10px">
                Cole este bloco no <code>unbound.conf</code> do servidor do cliente, <strong>depois</strong> do fim do bloco <code>server:</code> (antes dele, se usar <code>hyperlocal</code>). O Unbound vai buscar a zona periodicamente sozinho — não precisa de agente nem SSH. Antes de reiniciar o serviço, rode <code>unbound-checkconf</code> para garantir que a configuração está correta.
            </p>
            @if (auth()->user()->isAdmin())
                <pre class="endpoint-config-snippet" id="config-snippet">{{ str_replace($servidor->token, '••••••••••••', $configSnippet) }}</pre>
            @else
                <pre id="config-snippet" style="background:#080d17;border:1px solid var(--border);border-radius:10px;padding:14px;font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;font-size:12px;color:var(--text);overflow-x:auto;white-space:pre">{{ $configSnippet }}</pre>
            @endif
            <p style="color:var(--text-muted);font-size:10px;margin-top:8px">URL isolada, se precisar só dela: <code style="word-break:break-all">{{ $rpzUrl }}</code></p>
        </div>

        @if (auth()->user()->isAdmin())
        <details class="panel details-card-wide" id="legacy-token-access">
            <summary style="cursor:pointer;font-weight:700">Acesso legado por token</summary>
            <p style="color:var(--text-muted);font-size:11px">Compatibilidade para clientes existentes. Para novas configurações, prefira a URL curta com ACL.</p>
            <div class="field-group">
                <label>URL legada</label>
                <div class="endpoint-secret-row"><input class="endpoint-code-field" id="legacy-rpz-url" value="{{ $legacyRpzUrl }}" readonly><button type="button" class="button button-secondary" data-endpoint-copy="legacy-rpz-url">Copiar</button></div>
            </div>
            <div class="field-group" style="margin-top:14px">
                <label>Token</label>
                <div class="endpoint-secret-row">
                    <code class="endpoint-code-field endpoint-token-field" id="detail-token-value">••••••••••••••••••••••••••••</code>
                    <button type="button" class="button button-secondary" id="detail-token-toggle">Mostrar</button>
                    <button type="button" class="button button-secondary" data-endpoint-copy="detail-token-value">Copiar</button>
                </div>
            </div>
        </details>

        <div class="panel details-card-wide">
            <div class="panel-header">
                <h2>Restrição de IP</h2>
                <form action="{{ route('servidores.ip-restriction.toggle', $servidor) }}" method="POST">
                    @csrf
                    <button type="submit" class="button button-secondary">
                        {{ $servidor->ip_restriction_enabled ? 'Desativar restrição' : 'Ativar restrição' }}
                    </button>
                </form>
            </div>
            <p style="color:var(--text-muted);font-size:11px;margin:0 0 14px">
                @if ($servidor->ip_restriction_enabled)
                    Ativa: os IPs listados abaixo autorizam a URL curta e também restringem o acesso legado.
                @else
                    Desativada para o token legado. A URL curta empresarial continua exigindo pelo menos um IP autorizado.
                @endif
            </p>

            <p style="color:var(--text-muted);font-size:10px;margin:0 0 8px">
                Aceita IPv4 e IPv6 (com ou sem CIDR). Confirme com o cliente por qual IP o Unbound dele realmente sai antes de cadastrar &mdash; se o servidor for dual-stack, pode sair por IPv6 mesmo você esperando IPv4.
            </p>
            <form action="{{ route('servidores.ips.store', $servidor) }}" method="POST" style="display:flex;gap:8px;margin-bottom:14px">
                @csrf
                <input class="form-control" type="text" name="ip_cidr" placeholder="203.0.113.10, 203.0.113.0/24 ou 2001:db8::1" required>
                <button type="submit" class="button button-primary">Adicionar IP</button>
            </form>

            @if ($servidor->allowedIps->isEmpty())
                <div class="empty-state"><span>Nenhum IP cadastrado.</span></div>
            @else
                <div class="table-responsive">
                    <table class="data-table">
                        <thead><tr><th>IP / CIDR</th><th class="table-actions-column"></th></tr></thead>
                        <tbody>
                            @foreach ($servidor->allowedIps as $ip)
                                <tr>
                                    <td class="table-mono">{{ $ip->ip_cidr }}</td>
                                    <td>
                                        <form action="{{ route('servidores.ips.destroy', [$servidor, $ip]) }}" method="POST">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="table-action-link" style="background:none;border:0" onclick="return confirm('Remover este IP?')">Remover</button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
        @endif

        <div class="panel details-card-wide">
            <div class="panel-header"><h2>Fontes vinculadas ({{ $servidor->listas->count() }})</h2></div>
            @if ($servidor->listas->isEmpty())
                <div class="empty-state"><span>Nenhuma fonte vinculada ainda.</span></div>
            @else
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr><th>Fonte</th><th>Tipo</th><th class="table-actions-column"></th></tr>
                        </thead>
                        <tbody>
                            @foreach ($servidor->listas as $lista)
                                @php $tipoFonte = auth()->user()->isAdmin() ? ($lista->isAnatel() ? 'Catálogo / Importação ANATEL' : ($lista->isExterna() ? 'Externa' : ($lista->empresa_id ? 'Própria' : 'Catálogo'))) : ($lista->empresa_id ? 'Própria' : 'Catálogo'); @endphp
                                <tr>
                                    <td><a href="{{ route('listas.show', $lista) }}" class="table-primary-link">{{ $lista->nome }}</a></td>
                                    <td>
                                        <span class="status-pill status-pill-normal-case {{ auth()->user()->isAdmin() && $lista->isExterna() ? 'is-info' : ($lista->empresa_id ? 'is-active' : 'is-muted') }}">{{ $tipoFonte }}</span>
                                    </td>
                                    <td>
                                        <form action="{{ route('servidores.listas.detach', [$servidor, $lista]) }}" method="POST">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="table-action-link" style="background:none;border:0" onclick="return confirm('Remover esta fonte do endpoint?')">Remover</button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <div class="panel details-card-wide">
            <div class="panel-header"><h2>Fontes disponíveis para adicionar</h2></div>
            @if ($listasDisponiveis->isEmpty())
                <div class="empty-state"><span>Nenhuma fonte disponível no momento.</span></div>
            @else
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr><th>Fonte</th><th>Tipo</th><th class="table-actions-column"></th></tr>
                        </thead>
                        <tbody>
                            @foreach ($listasDisponiveis as $lista)
                                @php $tipoFonte = auth()->user()->isAdmin() ? ($lista->isAnatel() ? 'Catálogo / Importação ANATEL' : ($lista->isExterna() ? 'Externa' : ($lista->empresa_id ? 'Própria' : 'Catálogo'))) : ($lista->empresa_id ? 'Própria' : 'Catálogo'); @endphp
                                <tr>
                                    <td>{{ $lista->nome }}</td>
                                    <td>
                                        <span class="status-pill status-pill-normal-case {{ auth()->user()->isAdmin() && $lista->isExterna() ? 'is-info' : ($lista->empresa_id ? 'is-active' : 'is-muted') }}">{{ $tipoFonte }}</span>
                                    </td>
                                    <td>
                                        <form action="{{ route('servidores.listas.attach', [$servidor, $lista]) }}" method="POST">
                                            @csrf
                                            <button type="submit" class="table-action-link">Adicionar</button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <div class="panel details-card-wide">
            <div class="panel-header">
                <h2>Log do servidor (últimos 30 dias)</h2>
                <span class="status-pill is-muted">Sincronizações + listas</span>
            </div>
            <p style="color:var(--text-muted);font-size:11px;margin:0 0 10px">
                Uma linha por evento: quando o Unbound deste servidor buscou a zona (com quantos domínios foram entregues) e quando uma lista vinculada a ele ganhou/perdeu domínios — junto, dá pra ver a causa e o efeito: a lista muda, e a próxima sincronização depois já reflete isso na contagem entregue. "Perdeu" é desativado, não apagado do banco.
            </p>
            @if (empty($logServidor))
                <div class="empty-state"><span>Nenhum evento nos últimos 30 dias — o servidor ainda não sincronizou e nenhuma lista vinculada mudou.</span></div>
            @else
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr><th>Data/hora</th><th>Tipo</th><th>Evento</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($logServidor as $evento)
                                @php
                                    $pillClass = match($evento['tipo']) {
                                        'sync' => 'is-info',
                                        'lista_add' => 'is-active',
                                        'lista_remove' => 'is-warning',
                                        default => 'is-muted',
                                    };
                                    $pillLabel = match($evento['tipo']) {
                                        'sync' => 'Sincronização',
                                        'lista_add' => 'Fonte +',
                                        'lista_remove' => 'Fonte -',
                                        default => '-',
                                    };
                                @endphp
                                <tr>
                                    <td class="table-mono">
                                        {{ $evento['timestamp']->format($evento['tipo'] === 'sync' ? 'd/m/Y H:i:s' : 'd/m/Y') }}
                                    </td>
                                    <td><span class="status-pill {{ $pillClass }}">{{ $pillLabel }}</span></td>
                                    <td>
                                        @if ($evento['lista_id'])
                                            <a href="{{ route('listas.historico', $evento['lista_id']) }}" class="table-primary-link">{{ $evento['detalhe'] }}</a>
                                        @else
                                            {{ $evento['detalhe'] }}
                                            @if ($evento['meta'])
                                                <span class="table-mono" style="color:var(--text-muted)">— IP {{ $evento['meta'] }}</span>
                                            @endif
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <p style="color:var(--text-muted);font-size:10px;margin-top:12px">Mostrando os últimos 80 eventos. Detalhe domínio-por-domínio disponível no histórico de cada fonte.</p>
            @endif
        </div>
    </div>

    <script>
        (function () {
            var detailEndpointToken = @json(auth()->user()->isAdmin() ? $servidor->token : null);
            var endpointConfigSnippet = @json($configSnippet);
            var btn = document.getElementById('copy-config-btn');
            if (btn) {
                btn.addEventListener('click', function () {
                    navigator.clipboard.writeText(endpointConfigSnippet).then(function () {
                        var original = btn.textContent;
                        btn.textContent = 'Copiado!';
                        setTimeout(function () { btn.textContent = original; }, 1500);
                    });
                });
            }

            document.querySelectorAll('[data-endpoint-copy]').forEach(function (copyButton) {
                copyButton.addEventListener('click', function () {
                    var target = document.getElementById(copyButton.dataset.endpointCopy);
                    if (!target) return;
                    var copyValue = target.id === 'detail-token-value' && detailEndpointToken
                        ? detailEndpointToken
                        : ('value' in target ? target.value : target.textContent).trim();
                    navigator.clipboard.writeText(copyValue).then(function () {
                        var original = copyButton.textContent;
                        copyButton.textContent = 'Copiado!';
                        setTimeout(function () { copyButton.textContent = original; }, 1500);
                    });
                });
            });

            var tokenToggle = document.getElementById('detail-token-toggle');
            var tokenValue = document.getElementById('detail-token-value');
            if (tokenToggle && tokenValue && detailEndpointToken) {
                var detailTokenVisible = false;
                tokenToggle.addEventListener('click', function () {
                    detailTokenVisible = !detailTokenVisible;
                    tokenValue.textContent = detailTokenVisible ? detailEndpointToken : '••••••••••••••••••••••••••••';
                    tokenToggle.textContent = detailTokenVisible ? 'Ocultar' : 'Mostrar';
                });
            }
        })();
    </script>
@endsection
