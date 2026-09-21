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
    <div class="endpoint-workspace">
    <nav class="endpoint-breadcrumb" aria-label="Navegação"><a href="{{ route('servidores.index') }}">Endpoints RPZ</a><x-ui-icon name="chevron" size="14" /><span>{{ $servidor->nome }}</span></nav>
    <div class="page-heading">
        <div>
            <div class="page-eyebrow">{{ $isMikrotik ? 'Feed MikroTik Adlist' : 'Endpoint RPZ' }}</div>
            <h1>{{ $servidor->nome }}</h1>
            <p><a href="{{ route('empresas.show', $servidor->empresa) }}" class="inline-link">{{ $servidor->empresa->nome }}</a></p>
        </div>
        <div class="page-actions">
            @if ($servidor->tipo_dns === 'unbound')
                <a href="#rpz-manual-sync" class="button button-primary endpoint-sync-action" data-open-sync title="Abrir instruções para sincronizar no servidor DNS"><x-ui-icon name="refresh" size="19" />Sincronizar agora</a>
            @endif
            <a href="{{ route('servidores.edit', $servidor) }}" class="button button-secondary"><x-ui-icon name="pencil" size="18" />Editar</a>
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

    @include('servidores.partials.admin-detail-styles')
    <div class="details-grid endpoint-overview admin-endpoint-detail">
        <div class="panel details-card-wide admin-endpoint-status">
                @php
                    $estadoOperacional = $diasSemConsulta === null ? 'Sem consulta' : ($diasSemConsulta >= 2 ? 'Atenção' : 'Normal');
                    $classeOperacional = $diasSemConsulta === null ? 'is-muted' : ($diasSemConsulta >= 2 ? 'is-warning' : 'is-active');
                @endphp
                <div class="endpoint-detail-statuses">
                    <div><x-ui-icon name="server" size="23" /><span>Status cadastral</span><strong class="status-pill {{ $servidor->status === 'active' ? 'is-active' : 'is-inactive' }}">{{ $servidor->status === 'active' ? 'Ativo' : 'Inativo' }}</strong></div>
                    <div><x-ui-icon name="activity" size="23" /><span>Estado operacional</span><strong class="status-pill {{ $classeOperacional }}">{{ $estadoOperacional }}</strong></div>
                    <div><x-ui-icon name="clock" size="23" /><span>Última consulta</span><strong>{{ \App\Http\Controllers\DashboardController::relativoPt($servidor->last_synced_at) }}</strong></div>
                    <div><x-ui-icon name="server" size="23" /><span>Tipo de DNS</span><strong>{{ $isMikrotik ? 'MikroTik RouterOS v7' : 'Unbound' }}</strong></div>
                    <div><x-ui-icon name="shield" size="23" /><span>Modo de bloqueio</span><strong>{{ $isMikrotik ? 'Adlist (0.0.0.0)' : ($servidor->bloqueio_modo === 'redirect' ? 'Página de bloqueio' : 'NXDOMAIN') }}</strong></div>
                </div>
        </div>

        <div class="endpoint-left-column">
        <div class="panel endpoint-lists-card">
            <div class="panel-header"><div class="endpoint-card-title"><x-ui-icon name="link" size="26" /><div><h2>Listas vinculadas</h2><p class="endpoint-detail-note">{{ $servidor->listas->count() }} listas vinculadas</p></div></div><button type="button" class="button button-secondary" data-endpoint-dialog="endpoint-lists"><x-ui-icon name="list" size="16" />Ver listas<x-ui-icon name="chevron" size="13" /></button></div>
            <div class="endpoint-tags">
                @forelse ($servidor->listas->take(3) as $lista)
                    <span class="endpoint-tag" title="{{ $lista->nome }}">{{ $lista->nome }}</span>
                @empty
                    <span class="endpoint-detail-note">Nenhuma lista vinculada.</span>
                @endforelse
                @if ($servidor->listas->count() > 3)<span class="endpoint-tag">+{{ $servidor->listas->count() - 3 }}</span>@endif
            </div>
        </div>
        <dialog id="endpoint-lists" class="endpoint-drawer" aria-labelledby="endpoint-lists-title">
            <div class="panel-header endpoint-drawer-heading"><div class="endpoint-card-title"><x-ui-icon name="link" size="27" /><div><h2 id="endpoint-lists-title">Listas vinculadas</h2><p class="endpoint-detail-note">{{ $servidor->listas->count() }} listas vinculadas a este servidor</p></div></div><button type="button" class="endpoint-dismiss" data-close-dialog aria-label="Fechar listas"><x-ui-icon name="x" size="20" /></button></div>
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
                                    <td><div class="endpoint-source-name"><x-list-category-icon :lista="$lista" /><div><a href="{{ route('listas.show', $lista) }}" class="table-primary-link">{{ $lista->nome }}</a>
                                        @if ($lista->descricao)<small class="lista-descricao" style="display:block;margin-top:2px;color:var(--text-muted);font-size:11px;line-height:1.4">{{ $lista->descricao }}</small>@endif</div></div></td>
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
            <div class="endpoint-drawer-footer"><x-ui-icon name="info" size="21" /><p>As alterações nas listas são aplicadas na próxima sincronização do servidor.</p><button type="button" class="button button-secondary" data-close-dialog>Fechar</button></div>
        </dialog>
        @if (auth()->user()->isAdmin())
        <div class="panel endpoint-ip-card">
            <div class="panel-header">
                <div class="endpoint-card-title"><x-ui-icon name="shield" size="26" /><div><h2>Restrição de IP</h2><p class="endpoint-detail-note">{{ $ipsAtivos->count() }} IPs autorizados</p></div></div>
                <button type="button" class="button button-secondary" data-endpoint-dialog="endpoint-ips"><x-ui-icon name="settings" size="16" />Gerenciar IPs</button>
            </div>
            <div class="endpoint-tags">
                @foreach ($ipsAtivos->take(3) as $ip)
                    <span class="endpoint-tag endpoint-ip-tag">{{ $ip->ip_cidr }}<small>{{ str_contains($ip->ip_cidr, ':') ? 'IPv6' : 'IPv4' }}</small></span>
                @endforeach
                @if ($ipsAtivos->count() > 3)<span class="endpoint-tag">+{{ $ipsAtivos->count() - 3 }}</span>@endif
                @if ($ipsPendentes->isNotEmpty())<span class="status-pill is-warning">{{ $ipsPendentes->count() }} pendentes</span>@endif
            </div>
        </div>
        <dialog id="endpoint-ips" class="endpoint-drawer endpoint-ip-drawer" aria-labelledby="endpoint-ips-title">
            <div class="panel-header endpoint-drawer-heading">
                <div class="endpoint-card-title"><x-ui-icon name="shield" size="27" /><div><h2 id="endpoint-ips-title">Restrição de IP</h2><p class="endpoint-detail-note">{{ $ipsAtivos->count() }} autorizados · {{ $ipsPendentes->count() }} aguardando liberação</p></div></div>
                <button type="button" class="endpoint-dismiss" data-close-dialog aria-label="Fechar gerenciamento de IPs"><x-ui-icon name="x" size="20" /></button>
            </div>

            <section class="endpoint-ip-access" aria-label="Controle de acesso">
                <div class="endpoint-ip-access-heading"><x-ui-icon name="lock" size="20" /><div><h3>Controle de acesso</h3><p class="endpoint-detail-note">Método: {{ $usesCompanyEndpoint ? 'ACL por IP' : 'Token legado (fallback)' }}</p></div></div>
                <form action="{{ route('servidores.ip-restriction.toggle', $servidor) }}" method="POST">
                    @csrf
                    <button type="submit" class="button button-secondary">{{ $servidor->ip_restriction_enabled ? 'Desativar restrição' : 'Ativar restrição' }}</button>
                </form>
                <p class="endpoint-detail-note">A URL empresarial exige um IP autorizado. Este controle ativa ou desativa a restrição no acesso por token.</p>
            </section>

            <form action="{{ route('servidores.ips.store', $servidor) }}" method="POST" class="endpoint-ip-add">
                @csrf
                <label for="endpoint-new-ip">Adicionar IP ou rede</label>
                <p id="endpoint-ip-help" class="endpoint-detail-note">IPv4 ou IPv6, com ou sem CIDR. Informe o IP de saída do servidor DNS.</p>
                <div class="endpoint-ip-form">
                    <input id="endpoint-new-ip" class="form-control endpoint-detail-mono" type="text" name="ip_cidr" placeholder="Ex.: 203.0.113.10 ou 2001:db8::/64" aria-describedby="endpoint-ip-help" required>
                    <button type="submit" class="button button-primary"><x-ui-icon name="plus" size="17" />Adicionar IP</button>
                </div>
            </form>

            <div class="endpoint-ip-list-heading"><h3>IPs cadastrados</h3><span class="endpoint-detail-note">{{ $servidor->allowedIps->count() }} registros</span></div>
            <ul class="endpoint-ip-list" aria-label="IPs cadastrados">
                @forelse ($servidor->allowedIps as $ip)
                    <li class="endpoint-ip-entry">
                        <x-ui-icon name="{{ $ip->status === 'active' ? 'shield' : 'clock' }}" size="23" />
                        <div class="endpoint-ip-entry-copy">
                            <strong>{{ $ip->ip_cidr }}</strong>
                            <div class="endpoint-ip-entry-meta"><span>{{ str_contains($ip->ip_cidr, ':') ? 'IPv6' : 'IPv4' }}</span><span class="status-pill {{ $ip->status === 'active' ? 'is-active' : 'is-warning' }}">{{ $ip->status === 'active' ? 'Liberado' : 'Aguardando liberação' }}</span></div>
                        </div>
                        <div class="endpoint-ip-entry-actions">
                            @if ($ip->status !== 'active')
                                <form action="{{ route('servidores.ips.approve', [$servidor, $ip]) }}" method="POST">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="endpoint-ip-approve" aria-label="Liberar IP {{ $ip->ip_cidr }}"><x-ui-icon name="check" size="16" />Liberar</button>
                                </form>
                            @endif
                            <form action="{{ route('servidores.ips.destroy', [$servidor, $ip]) }}" method="POST">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="endpoint-ip-remove" aria-label="Remover IP {{ $ip->ip_cidr }}" onclick="return confirm('Remover este IP?')"><x-ui-icon name="trash" size="15" />Remover</button>
                            </form>
                        </div>
                    </li>
                @empty
                    <li class="empty-state"><x-ui-icon name="shield" size="28" /><span>Nenhum IP cadastrado.</span></li>
                @endforelse
            </ul>
            <div class="endpoint-drawer-footer"><x-ui-icon name="info" size="21" /><p>Em redes dual-stack, confira também o IPv6 de saída do servidor.</p><button type="button" class="button button-secondary" data-close-dialog>Fechar</button></div>
        </dialog>
        @endif

        <section class="panel endpoint-technical">
            <div class="panel-header"><div class="endpoint-card-title"><x-ui-icon name="settings" size="26" /><div><h2>Configuração técnica</h2><p class="endpoint-detail-note">Detalhes de configuração e solução de problemas</p></div></div></div>
        <details class="endpoint-disclosure">
            <summary><x-ui-icon name="server" size="18" />{{ $isMikrotik ? 'Configuração do MikroTik' : 'Configuração do Unbound' }}<x-ui-icon name="chevron" size="15" /></summary>
            <div class="endpoint-disclosure-body">
            <div class="panel-header">
                <button type="button" class="button button-secondary" id="copy-config-btn" data-copy-target="config-snippet">Copiar</button>
            </div>
            <div class="field-group">
                <label>{{ $usesCompanyEndpoint ? 'URL curta' : 'URL do feed de fallback' }}</label>
                <div class="endpoint-secret-row">
                    <input class="endpoint-code-field" id="detail-rpz-url" type="text" value="{{ $isMikrotik ? $mikrotikUrl : $rpzUrl }}" readonly spellcheck="false">
                    <button type="button" class="button button-secondary" data-endpoint-copy="detail-rpz-url" aria-live="polite">Copiar</button>
                </div>
            </div>
            @unless ($isMikrotik)
                <p style="color:var(--text-muted);font-size:11px;margin:0 0 10px">
                    <strong>Passo 1 — habilitar os módulos no bloco <code>server:</code>.</strong> O RPZ depende do módulo <code>respip</code>; sem ele o Unbound aceita a configuração, mas não bloqueia nada. <strong>A ordem importa</strong>: <code>respip</code> vem primeiro. Se o seu <code>unbound.conf</code> já tem uma linha <code>module-config</code>, só acrescente <code>respip</code> no início dela, mantendo os demais módulos que você já usa — não duplique a linha.
                </p>
                <pre id="module-snippet" style="background:var(--command-background, #080d17);border:1px solid var(--border);border-radius:10px;padding:14px;font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;font-size:12px;color:var(--text);overflow-x:auto;white-space:pre;margin:0 0 6px">server:
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
                <pre id="config-snippet" style="background:var(--command-background, #080d17);border:1px solid var(--border);border-radius:10px;padding:14px;font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;font-size:12px;color:var(--text);overflow-x:auto;white-space:pre">{{ $isMikrotik ? $mikrotikConfig : $configSnippet }}</pre>
            @endif
            @unless ($isMikrotik)
                <div id="rpz-restart-steps" style="margin-top:16px">
                    <p style="color:var(--text-muted);font-size:11px;margin:0 0 8px">
                        <strong>Passo 3 — validar e reiniciar.</strong> Depois de salvar o <code>unbound.conf</code>, valide a configuração e só reinicie se aparecer <em>no errors</em>. Se houver erro, corrija antes: um arquivo inválido impede o serviço de subir e derruba o DNS da sua rede.
                    </p>
                    <pre id="restart-snippet" style="background:var(--command-background, #080d17);border:1px solid var(--border);border-radius:10px;padding:14px;font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;font-size:12px;color:var(--text);overflow-x:auto;white-space:pre;margin:0 0 6px">unbound-checkconf
systemctl restart unbound</pre>
                    <div style="margin:0 0 6px"><button type="button" class="button button-secondary" data-endpoint-copy="restart-snippet" aria-live="polite">Copiar</button></div>
                    <p style="color:var(--text-muted);font-size:10px;margin:0 0 16px">Reinicie só nesta primeira configuração ou quando alterar o <code>unbound.conf</code>. Depois disso o Unbound atualiza a lista sozinho, a cada hora, sem reiniciar.</p>

                    <p style="color:var(--text-muted);font-size:11px;margin:0 0 8px">
                        <strong>Passo 4 — conferir.</strong> Consulte o domínio de teste, que é sempre bloqueado:
                    </p>
                    <pre id="verify-snippet" style="background:var(--command-background, #080d17);border:1px solid var(--border);border-radius:10px;padding:14px;font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;font-size:12px;color:var(--text);overflow-x:auto;white-space:pre;margin:0 0 6px">dig @127.0.0.1 blocktest.{{ $rpzZoneName }}</pre>
                    <div style="margin:0 0 6px"><button type="button" class="button button-secondary" data-endpoint-copy="verify-snippet" aria-live="polite">Copiar</button></div>
                    <p style="color:var(--text-muted);font-size:10px;margin:0">A resposta esperada é <code>status: NXDOMAIN</code>. Logo depois do reinício a primeira consulta pode demorar alguns segundos, enquanto a lista é baixada. Se não bloquear, confira se o IP do seu DNS já foi liberado (aviso no topo desta tela).</p>
                </div>

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
                <div class="endpoint-disclosure-footer"><button type="button" class="button button-secondary" data-close-disclosure><x-ui-icon name="x" size="16" />Fechar</button></div>
            </div>
        </details>

        @if ($servidor->tipo_dns === 'unbound')
            <details class="endpoint-disclosure" id="rpz-manual-sync">
                <summary><x-ui-icon name="wrench" size="18" />Solução de problemas<x-ui-icon name="chevron" size="15" /></summary>
                <div class="endpoint-disclosure-body">
                <p style="color:var(--text-muted);font-size:11px;line-height:1.6;margin:0 0 10px">Se as listas não atualizarem após vincular novas fontes ou se a zona estiver vazia, copie o comando abaixo e <strong>execute no terminal do servidor DNS do cliente ({{ $servidor->nome }})</strong>, como root ou com sudo. Ele solicita uma nova busca sem esperar o intervalo de atualização.</p>
                <pre id="rpz-transfer-command" style="background:var(--command-background, #080d17);border:1px solid var(--border);border-radius:10px;padding:14px;font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;font-size:12px;color:var(--text);overflow-x:auto;white-space:pre;margin:0 0 6px">unbound-control auth_zone_transfer {{ escapeshellarg($rpzZoneName) }}</pre>
                <div style="margin:0 0 14px"><button type="button" class="button button-secondary" data-endpoint-copy="rpz-transfer-command" aria-live="polite">Copiar</button></div>
                <p style="color:var(--text-muted);font-size:11px;line-height:1.6;margin:0 0 10px">O botão copia o comando; a execução acontece no DNS do cliente. É necessário ter o <code>unbound-control</code> habilitado. Se o nome no bloco <code>rpz:</code> foi personalizado, use esse nome no comando.</p>
                <p style="color:var(--text-muted);font-size:11px;line-height:1.6;margin:0 0 10px">O retorno <code>ok</code> indica que a busca foi solicitada. Aguarde o download e atualize esta página para conferir a última consulta e a quantidade de domínios no histórico. Esse registro confirma a consulta ao painel; confira também os logs do Unbound para confirmar que a zona foi carregada.</p>
                <details style="color:var(--text-muted);font-size:11px;line-height:1.6">
                    <summary style="cursor:pointer;margin-bottom:10px">Se o comando apresentar erro</summary>
                    <p style="color:var(--text-muted);font-size:11px;line-height:1.6;margin:0 0 10px">Se aparecer erro de conexão ou de controle desabilitado, confira a configuração local do <code>unbound-control</code>. Se a zona não for encontrada, consulte os nomes configurados:</p>
                    <pre id="rpz-list-zones-command" style="background:var(--command-background, #080d17);border:1px solid var(--border);border-radius:10px;padding:14px;font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;font-size:12px;color:var(--text);overflow-x:auto;white-space:pre;margin:0 0 6px">unbound-control list_auth_zones</pre>
                    <div style="margin:0 0 14px"><button type="button" class="button button-secondary" data-endpoint-copy="rpz-list-zones-command" aria-live="polite">Copiar</button></div>
                    <p style="color:var(--text-muted);font-size:11px;line-height:1.6;margin:0 0 10px">Reiniciar o serviço pode reutilizar o arquivo de zona salvo. Para antecipar a atualização, use <code>auth_zone_transfer</code>.</p>
                </details>
                <div class="endpoint-disclosure-footer"><button type="button" class="button button-secondary" data-close-disclosure><x-ui-icon name="x" size="16" />Fechar</button></div>
                </div>
            </details>
        @endif

        </section>
        </div>
        @include('servidores.partials.admin-event-stream', ['recent' => true])
        <dialog id="endpoint-history" class="endpoint-drawer endpoint-history-drawer" aria-labelledby="endpoint-history-title">
            <div class="panel-header"><h2 id="endpoint-history-title">Histórico completo</h2><button type="button" class="button button-secondary" data-close-dialog>Fechar</button></div>
            <p class="endpoint-detail-note">Últimos 30 dias, até 80 eventos disponíveis. Detalhes no histórico de cada fonte.</p>
            @include('servidores.partials.admin-event-stream', ['recent' => false])
        </dialog>
    </div>

    </div>

    <script>
        (function () {
            var endpointConfigSnippet = @json($isMikrotik ? $mikrotikConfig : $configSnippet);
            document.querySelectorAll('[data-endpoint-dialog]').forEach(function (button) {
                button.addEventListener('click', function () { document.getElementById(button.dataset.endpointDialog).showModal(); });
            });
            document.querySelectorAll('[data-close-dialog]').forEach(function (button) {
                button.addEventListener('click', function () { button.closest('dialog').close(); });
            });
            document.querySelectorAll('[data-close-disclosure]').forEach(function (button) {
                button.addEventListener('click', function () {
                    var section = button.closest('.endpoint-disclosure');
                    section.open = false;
                    var summary = section.querySelector('summary');
                    summary.focus({ preventScroll: true });
                    summary.scrollIntoView({ block: 'nearest' });
                });
            });
            function openSync() {
                var section = document.getElementById('rpz-manual-sync');
                if (section) section.open = true;
            }
            document.querySelectorAll('[data-open-sync]').forEach(function (button) { button.addEventListener('click', openSync); });
            if (location.hash === '#rpz-manual-sync') openSync();
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
                    if (!navigator.clipboard || !navigator.clipboard.writeText) {
                        copyButton.textContent = 'Selecione e copie o comando acima';
                        return;
                    }
                    navigator.clipboard.writeText(copyValue).then(function () {
                        var original = copyButton.textContent;
                        copyButton.textContent = 'Copiado!';
                        setTimeout(function () { copyButton.textContent = original; }, 1500);
                    }).catch(function () {
                        copyButton.textContent = 'Selecione e copie o texto acima';
                    });
                });
            });

        })();
    </script>
@endsection
