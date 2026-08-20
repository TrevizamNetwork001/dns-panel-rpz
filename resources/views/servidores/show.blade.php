@extends('layouts.app')

@section('title', $servidor->nome)

@php
    $panelHost = parse_url(config('app.url'), PHP_URL_HOST) ?: 'localhost';
    $rpzUrl = url('/rpz/' . $servidor->token . '.zone');
    $rpzZoneName = $panelHost;
    $configSnippet = "rpz:\n    name: \"{$rpzZoneName}\"\n    url: \"{$rpzUrl}\"\n    rpz-log: yes\n    rpz-log-name: \"dns-panel-rpz\"";
@endphp

@section('content')
    <div class="page-heading">
        <div>
            <div class="page-eyebrow">Servidor</div>
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
            <span class="status-pill @if($servidor->status === 'active') is-active @else is-inactive @endif">
                {{ $servidor->status === 'active' ? 'Ativo' : 'Inativo' }}
            </span>
            <dl class="details-list" style="margin-top:14px">
                <div><dt>Última sincronização</dt><dd>{{ optional($servidor->last_synced_at)->format('d/m/Y H:i:s') ?? 'nunca' }}</dd></div>
                <div><dt>Modo de bloqueio</dt><dd>{{ $servidor->bloqueio_modo === 'redirect' ? 'Página de bloqueio' : 'NXDOMAIN' }}</dd></div>
            </dl>
        </div>

        <div class="panel details-card-wide">
            <div class="panel-header">
                <h2>Configuração do Unbound</h2>
                <button type="button" class="button button-secondary" id="copy-config-btn" data-copy-target="config-snippet">Copiar</button>
            </div>
            <p style="color:var(--text-muted);font-size:11px;margin:0 0 10px">
                Cole este bloco no <code>unbound.conf</code> do servidor do cliente, <strong>depois</strong> do fim do bloco <code>server:</code> (antes dele, se usar <code>hyperlocal</code>). O Unbound vai buscar a zona periodicamente sozinho — não precisa de agente nem SSH. Antes de reiniciar o serviço, rode <code>unbound-checkconf</code> para garantir que a configuração está correta.
            </p>
            <pre id="config-snippet" style="background:#080d17;border:1px solid var(--border);border-radius:10px;padding:14px;font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;font-size:12px;color:var(--text);overflow-x:auto;white-space:pre">{{ $configSnippet }}</pre>
            <p style="color:var(--text-muted);font-size:10px;margin-top:8px">URL isolada, se precisar só dela: <code style="word-break:break-all">{{ $rpzUrl }}</code></p>
        </div>

        @if (auth()->user()->isAdmin())
        <div class="panel details-card-wide">
            <div class="panel-header">
                <h2>Restrição de IP (opcional)</h2>
                <form action="{{ route('servidores.ip-restriction.toggle', $servidor) }}" method="POST">
                    @csrf
                    <button type="submit" class="button button-secondary">
                        {{ $servidor->ip_restriction_enabled ? 'Desativar restrição' : 'Ativar restrição' }}
                    </button>
                </form>
            </div>
            <p style="color:var(--text-muted);font-size:11px;margin:0 0 14px">
                @if ($servidor->ip_restriction_enabled)
                    Ativa: só os IPs listados abaixo conseguem sincronizar, mesmo com o token correto.
                @else
                    Desativada: qualquer IP com o token válido consegue sincronizar. O token (48 caracteres aleatórios) já é a proteção principal — isso é uma camada extra, útil se o IP do servidor do cliente for fixo.
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
            <div class="panel-header"><h2>Listas vinculadas ({{ $servidor->listas->count() }})</h2></div>
            @if ($servidor->listas->isEmpty())
                <div class="empty-state"><span>Nenhuma lista vinculada ainda.</span></div>
            @else
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr><th>Lista</th><th>Tipo</th><th class="table-actions-column"></th></tr>
                        </thead>
                        <tbody>
                            @foreach ($servidor->listas as $lista)
                                <tr>
                                    <td><a href="{{ route('listas.show', $lista) }}" class="table-primary-link">{{ $lista->nome }}</a></td>
                                    <td>
                                        <span class="status-pill is-inactive">{{ $lista->empresa_id ? 'Própria' : 'Catálogo' }}</span>
                                    </td>
                                    <td>
                                        <form action="{{ route('servidores.listas.detach', [$servidor, $lista]) }}" method="POST">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="table-action-link" style="background:none;border:0" onclick="return confirm('Remover esta lista do servidor?')">Remover</button>
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
            <div class="panel-header"><h2>Listas disponíveis para adicionar</h2></div>
            @if ($listasDisponiveis->isEmpty())
                <div class="empty-state"><span>Nenhuma lista disponível no momento.</span></div>
            @else
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr><th>Lista</th><th>Tipo</th><th class="table-actions-column"></th></tr>
                        </thead>
                        <tbody>
                            @foreach ($listasDisponiveis as $lista)
                                <tr>
                                    <td>{{ $lista->nome }}</td>
                                    <td>
                                        <span class="status-pill is-inactive">{{ $lista->empresa_id ? 'Própria' : 'Catálogo' }}</span>
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
            <div class="panel-header"><h2>Histórico de sincronizações</h2></div>
            @if ($syncLogs->isEmpty())
                <div class="empty-state"><span>Este servidor ainda não sincronizou.</span></div>
            @else
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr><th>Data/hora</th><th>IP</th><th>Domínios entregues</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($syncLogs as $log)
                                <tr>
                                    <td class="table-mono">{{ $log->created_at->format('d/m/Y H:i:s') }}</td>
                                    <td class="table-mono">{{ $log->ip_address ?? '-' }}</td>
                                    <td class="table-mono">{{ $log->dominios_count }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <p style="color:var(--text-muted);font-size:10px;margin-top:12px">Mostrando os últimos 30 registros.</p>
            @endif
        </div>
    </div>

    <script>
        (function () {
            var btn = document.getElementById('copy-config-btn');
            if (!btn) return;
            btn.addEventListener('click', function () {
                var target = document.getElementById(btn.dataset.copyTarget);
                if (!target) return;
                navigator.clipboard.writeText(target.textContent).then(function () {
                    var original = btn.textContent;
                    btn.textContent = 'Copiado!';
                    setTimeout(function () { btn.textContent = original; }, 1500);
                });
            });
        })();
    </script>
@endsection
