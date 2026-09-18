@extends('layouts.app')

@section('title', $servidor->nome)

@php
    $panelHost = parse_url(config('app.url'), PHP_URL_HOST) ?: 'localhost';
    $rpzUrl = $servidor->preferredRpzEndpointUrl();
    $isMikrotik = $servidor->tipo_dns === 'mikrotik';
    $mikrotikUrl = $servidor->preferredMikrotikEndpointUrl();
    $usesCompanyEndpoint = $servidor->empresa->rpz_slug !== null;
    $rpzZoneName = $panelHost;
    $rpzZonefile = '/var/lib/unbound/'.$rpzZoneName.'.zone';
    $configSnippet = "rpz:\n    name: \"{$rpzZoneName}\"\n    zonefile: \"{$rpzZonefile}\"\n    url: \"{$rpzUrl}\"\n    # rpz-log: yes\n    # rpz-log-name: \"dns-panel-rpz\"";
    $mikrotikConfig = "/ip dns set allow-remote-requests=yes\n/ip dns adlist add url=\"{$mikrotikUrl}\" ssl-verify=yes";
    $diasSemConsulta = $servidor->diasSemSincronizar();
    $ipsAtivos = $servidor->allowedIps->where('status', 'active');
    $ipsPendentes = $servidor->allowedIps->where('status', 'pending');
    $limpezaConfig = app(\App\Services\ServidoresInativosCleaner::class)->config();
    $avisoRemocao = $limpezaConfig['ativo'] && $servidor->inatividade_avisada_em
        ? $servidor->inatividade_avisada_em->copy()->addDays($limpezaConfig['aviso_dias'])
        : null;
@endphp

@section('content')
    <div class="page-heading">
        <div>
            <div class="page-eyebrow">{{ $isMikrotik ? 'Feed MikroTik Adlist' : 'Endpoint RPZ' }}</div>
            <h1>{{ $servidor->nome }}</h1>
            <p><a href="{{ route('empresas.show', $servidor->empresa) }}" class="inline-link">{{ $servidor->empresa->nome }}</a></p>
        </div>
        <div class="page-actions">
            <a href="{{ route('servidores.edit', $servidor) }}" class="button button-secondary">Editar</a>
        </div>
    </div>

    @if (auth()->user()->isAdmin() && $ipsPendentes->isNotEmpty())
        <div class="alert-error" role="alert">
            Este servidor tem <strong>IP aguardando liberação</strong>: {{ $ipsPendentes->pluck('ip_cidr')->join(', ') }}. Libere em "Restrição de IP", mais abaixo, para o Unbound do cliente receber a zona.
        </div>
    @elseif (! auth()->user()->isAdmin() && $ipsAtivos->isEmpty())
        <div class="alert-error" role="alert">
            @if ($ipsPendentes->isNotEmpty())
                <strong>Aguardando liberação pelo suporte.</strong> O IP informado ({{ $ipsPendentes->pluck('ip_cidr')->join(', ') }}) ainda não foi liberado; enquanto isso o seu Unbound recebe "acesso negado" ao buscar a lista. Assim que for liberado, ele passa a receber sozinho, sem você precisar fazer nada.
            @else
                <strong>Nenhum IP informado.</strong> Edite o servidor e informe o IP de saída do seu DNS. O suporte precisa liberá-lo para o seu Unbound receber a lista.
            @endif
        </div>
    @endif

    @if ($avisoRemocao)
        <div class="alert-error" role="alert">
            Este servidor está sem consultar a zona RPZ há muito tempo e será <strong>removido automaticamente em {{ $avisoRemocao->format('d/m/Y') }}</strong> se não voltar a consultar antes disso.
        </div>
    @endif

    @if (auth()->user()->isAdmin())
        @include('servidores.partials.admin-detail-styles')
    @endif
    <div class="details-grid{{ auth()->user()->isAdmin() ? ' admin-endpoint-detail' : '' }}">
        <div class="panel{{ auth()->user()->isAdmin() ? ' details-card-wide admin-endpoint-status' : '' }}">
            <div class="panel-header"><h2>Status</h2></div>
            @if (auth()->user()->isAdmin())
                @php
                    $estadoOperacional = $diasSemConsulta === null ? 'Sem consulta' : ($diasSemConsulta >= 2 ? 'Atenção' : 'Normal');
                    $classeOperacional = $diasSemConsulta === null ? 'is-muted' : ($diasSemConsulta >= 2 ? 'is-warning' : 'is-active');
                @endphp
                <div class="endpoint-detail-statuses">
                    <div><span>Status cadastral</span><strong class="status-pill {{ $servidor->status === 'active' ? 'is-active' : 'is-inactive' }}">{{ $servidor->status === 'active' ? 'Ativo' : 'Inativo' }}</strong></div>
                    <div><span>Estado operacional</span><strong class="status-pill {{ $classeOperacional }}">{{ $estadoOperacional }}</strong></div>
                    <div><span>Última consulta</span><strong>{{ \App\Http\Controllers\DashboardController::relativoPt($servidor->last_synced_at) }}</strong></div>
                    <div><span>Tipo de DNS</span><strong>{{ $isMikrotik ? 'MikroTik RouterOS v7' : 'Unbound' }}</strong></div>
                    <div><span>Modo de bloqueio</span><strong>{{ $isMikrotik ? 'Adlist (0.0.0.0)' : ($servidor->bloqueio_modo === 'redirect' ? 'Página de bloqueio' : 'NXDOMAIN') }}</strong></div>
                </div>
            @else
                <span class="status-pill @if($servidor->status === 'active') is-active @else is-inactive @endif">
                    {{ $servidor->status === 'active' ? 'Ativo' : 'Inativo' }}
                </span>
            @endif
            @unless (auth()->user()->isAdmin())
            <dl class="details-list" style="margin-top:14px">
                <div><dt>Última consulta</dt><dd>{{ auth()->user()->isAdmin() ? \App\Http\Controllers\DashboardController::relativoPt($servidor->last_synced_at) : (optional($servidor->last_synced_at)->format('d/m/Y H:i:s') ?? 'nunca') }}</dd></div>
                <div><dt>Tipo de DNS</dt><dd>{{ $isMikrotik ? 'MikroTik RouterOS v7' : 'Unbound' }}</dd></div>
                <div><dt>Modo de bloqueio</dt><dd>{{ $isMikrotik ? 'Adlist (0.0.0.0)' : ($servidor->bloqueio_modo === 'redirect' ? 'Página de bloqueio' : 'NXDOMAIN') }}</dd></div>
            </dl>
            @endunless
        </div>

        @if (auth()->user()->isAdmin())
        <div class="panel details-card-wide admin-endpoint-access">
            <div class="panel-header"><h2>Acesso RPZ</h2></div>
            <dl class="details-list" style="margin-bottom:14px">
                <div><dt>Método</dt><dd>{{ $usesCompanyEndpoint ? 'ACL por IP' : 'Token legado (fallback)' }}</dd></div>
                <div><dt>Status da ACL</dt><dd>{{ $servidor->allowedIps->where('status', 'active')->isNotEmpty() ? 'Configurada' : 'Sem IP autorizado' }}</dd></div>
                <div><dt>IPs autorizados</dt><dd class="endpoint-detail-mono">{{ $servidor->allowedIps->where('status', 'active')->pluck('ip_cidr')->join(', ') ?: 'Nenhum' }}</dd></div>
            </dl>
            <div class="field-group">
                <label>{{ $usesCompanyEndpoint ? 'URL curta' : 'URL do feed de fallback' }}</label>
                <div class="endpoint-secret-row">
                    <input class="endpoint-code-field" id="detail-rpz-url" type="text" value="{{ $isMikrotik ? $mikrotikUrl : $rpzUrl }}" readonly spellcheck="false">
                    <button type="button" class="button button-secondary" data-endpoint-copy="detail-rpz-url" aria-live="polite">Copiar</button>
                </div>
            </div>
        </div>
        @endif

        <div class="panel details-card-wide">
            <div class="panel-header">
                <h2>{{ $isMikrotik ? 'Configuração do MikroTik' : 'Configuração do Unbound' }}</h2>
                <button type="button" class="button button-secondary" id="copy-config-btn" data-copy-target="config-snippet">Copiar</button>
            </div>
            @unless ($isMikrotik)
                <p style="color:var(--text-muted);font-size:11px;margin:0 0 10px">
                    <strong>Passo 1 — habilitar os módulos no bloco <code>server:</code>.</strong> O RPZ depende do módulo <code>respip</code>; sem ele o Unbound aceita a configuração, mas não bloqueia nada. <strong>A ordem importa</strong>: <code>respip</code> vem primeiro. Se o seu <code>unbound.conf</code> já tem uma linha <code>module-config</code>, só acrescente <code>respip</code> no início dela, mantendo os demais módulos que você já usa — não duplique a linha.
                </p>
                <pre id="module-snippet" style="background:#080d17;border:1px solid var(--border);border-radius:10px;padding:14px;font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;font-size:12px;color:var(--text);overflow-x:auto;white-space:pre;margin:0 0 6px">server:
    module-config: "respip validator iterator"</pre>
                <div style="margin:0 0 18px"><button type="button" class="button button-secondary" data-endpoint-copy="module-snippet" aria-live="polite">Copiar</button></div>
            @endunless
            <p style="color:var(--text-muted);font-size:11px;margin:0 0 10px">
                @unless ($isMikrotik)<strong>Passo 2 — bloco <code>rpz:</code>.</strong>@endunless
                @if ($isMikrotik)
                    Execute no terminal do RouterOS v7. Antes, confirme que o equipamento possui o menu <code>/ip dns adlist</code> e memória/cache suficientes para o tamanho da lista.
                @elseif (auth()->user()->isAdmin())
                    Adicione este bloco à configuração do Unbound do cliente. Antes de recarregar o serviço, valide com <code>unbound-checkconf</code>.
                    <span class="endpoint-detail-note">No <code>unbound.conf</code>, depois do bloco <code>server:</code> (antes dele, se usar <code>hyperlocal</code>). A zona é buscada periodicamente, sem agente ou SSH.</span>
                @else
                    Cole este bloco no <code>unbound.conf</code> do servidor do cliente, <strong>depois</strong> do fim do bloco <code>server:</code> (antes dele, se usar <code>hyperlocal</code>). O Unbound vai buscar a zona periodicamente sozinho — não precisa de agente nem SSH. Antes de reiniciar o serviço, rode <code>unbound-checkconf</code> para garantir que a configuração está correta.
                @endif
            </p>
            @if (auth()->user()->isAdmin())
                <pre class="endpoint-config-snippet" id="config-snippet">{{ str_replace($servidor->token, '••••••••••••', $isMikrotik ? $mikrotikConfig : $configSnippet) }}</pre>
            @else
                <pre id="config-snippet" style="background:#080d17;border:1px solid var(--border);border-radius:10px;padding:14px;font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;font-size:12px;color:var(--text);overflow-x:auto;white-space:pre">{{ $isMikrotik ? $mikrotikConfig : $configSnippet }}</pre>
            @endif
            @unless ($isMikrotik)
                <dl id="rpz-fields-help" style="margin:14px 0 0;font-size:11px;color:var(--text-muted);line-height:1.6">
                    <dt style="color:var(--text-soft);font-weight:600;margin-top:6px"><code>name</code></dt>
                    <dd style="margin:0">Nome da zona no Unbound. Já vem preenchido — não altere.</dd>
                    <dt style="color:var(--text-soft);font-weight:600;margin-top:6px"><code>zonefile</code></dt>
                    <dd style="margin:0">Arquivo onde o Unbound guarda a cópia local da zona baixada. Já vem preenchido — não altere o nome; o Unbound precisa conseguir gravar nesse caminho.</dd>
                    <dt style="color:var(--text-soft);font-weight:600;margin-top:6px"><code>url</code></dt>
                    <dd style="margin:0">Endereço de onde o Unbound consulta a zona. Ele contém o token do servidor — não compartilhe.</dd>
                    <dt style="color:var(--text-soft);font-weight:600;margin-top:6px"><code>rpz-log</code></dt>
                    <dd style="margin:0">Registra nos logs do Unbound cada consulta afetada pelo RPZ. Vem <strong>comentado</strong> de propósito: não deixe habilitado em produção (enche o disco e pesa no I/O), use só para diagnóstico — descomente, valide com <code>unbound-checkconf</code>, reinicie e volte a comentar depois.</dd>
                    <dt style="color:var(--text-soft);font-weight:600;margin-top:6px"><code>rpz-log-name</code></dt>
                    <dd style="margin:0">Nome que identifica as linhas de log deste RPZ, para você filtrá-las depois. Só faz efeito junto com o <code>rpz-log</code>. Pode ser alterado.</dd>
                </dl>
                <div id="rpz-attention" style="margin-top:16px;padding:12px 14px;border:1px solid var(--border);border-radius:10px;font-size:11px;color:var(--text-muted);line-height:1.6">
                    <strong style="color:var(--text-soft)">Pontos de atenção</strong>
                    <ul style="margin:6px 0 0;padding-left:18px">
                        <li>Não deixe o log habilitado em produção; use só para diagnóstico.</li>
                        <li>A URL não abre no navegador: só servidores cadastrados aqui, com o IP autorizado, conseguem consultar a zona. Para os demais a resposta é 404 e nenhum dado é entregue.</li>
                        <li>O bloco <code>rpz:</code> deve ficar <strong>depois</strong> do fim do bloco <code>server:</code>.</li>
                        <li>Se você usa <code>hyperlocal</code>, coloque o bloco <code>rpz:</code> <strong>antes</strong> dele.</li>
                        <li>Rode <code>unbound-checkconf</code> antes de reiniciar o serviço.</li>
                    </ul>
                </div>
            @endunless
            <p style="color:var(--text-muted);font-size:10px;margin-top:8px">URL isolada, se precisar só dela: <code style="word-break:break-all">{{ $isMikrotik ? $mikrotikUrl : $rpzUrl }}</code></p>
        </div>

        @if (auth()->user()->isAdmin())
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
                    Os IPs abaixo podem acessar a URL curta e o acesso legado.
                @else
                    Desativada para o token legado. A URL curta empresarial continua exigindo pelo menos um IP autorizado.
                @endif
            </p>

            <p style="color:var(--text-muted);font-size:10px;margin:0 0 8px">
                Aceita IPv4 e IPv6, com ou sem CIDR. Em dual-stack, confirme o IP de saída: o servidor pode usar IPv6.
            </p>
            <form action="{{ route('servidores.ips.store', $servidor) }}" method="POST" class="endpoint-ip-form" style="display:flex;gap:8px;margin-bottom:14px">
                @csrf
                <input class="form-control endpoint-detail-mono" type="text" name="ip_cidr" placeholder="203.0.113.10, 203.0.113.0/24 ou 2001:db8::1" required>
                <button type="submit" class="button button-primary">Adicionar IP</button>
            </form>

            @if ($servidor->allowedIps->isEmpty())
                <div class="empty-state"><span>Nenhum IP cadastrado.</span></div>
            @else
                <div class="table-responsive">
                    <table class="data-table">
                        <thead><tr><th>IP / CIDR</th><th>Status</th><th class="table-actions-column"></th></tr></thead>
                        <tbody>
                            @foreach ($servidor->allowedIps as $ip)
                                <tr>
                                    <td class="table-mono">{{ $ip->ip_cidr }}</td>
                                    <td>
                                        @if ($ip->status === 'active')
                                            <span class="status-pill is-active">Liberado</span>
                                        @else
                                            <span class="status-pill is-warning">Aguardando liberação</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($ip->status !== 'active')
                                            <form action="{{ route('servidores.ips.approve', [$servidor, $ip]) }}" method="POST" style="display:inline">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="button button-primary">Liberar</button>
                                            </form>
                                        @endif
                                        <form action="{{ route('servidores.ips.destroy', [$servidor, $ip]) }}" method="POST" style="display:inline">
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

        @if (auth()->user()->isAdmin())
            @include('servidores.partials.admin-event-stream')
        @else
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
        @endif
    </div>

    <script>
        (function () {
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
                    var copyValue = ('value' in target ? target.value : target.textContent).trim();
                    navigator.clipboard.writeText(copyValue).then(function () {
                        var original = copyButton.textContent;
                        copyButton.textContent = 'Copiado!';
                        setTimeout(function () { copyButton.textContent = original; }, 1500);
                    });
                });
            });

        })();
    </script>
@endsection
