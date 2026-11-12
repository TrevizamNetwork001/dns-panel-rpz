@extends('layouts.app')

@section('title', $empresa->nome)

@section('content')
    @php
        $isAdmin = auth()->user()->isAdmin();
        $servidoresUtilizados = $empresa->servidores->count();
        $capacidadeLicencaAtiva = $empresa->licencas->filter->isValid()->sum('max_servidores');
        $rpzUrl = $empresa->rpzEndpointUrl();
        $rpzHost = parse_url(config('app.url'), PHP_URL_HOST) ?: 'localhost';
        $rpzZonefile = '/var/lib/unbound/'.$rpzHost.'.zone';
        $rpzConfig = "rpz:\n    name: \"{$rpzHost}\"\n    zonefile: \"{$rpzZonefile}\"\n    url: \"{$rpzUrl}\"\n    # rpz-log: yes\n    # rpz-log-name: \"dns-panel-rpz\"";
        $rpzAllowedIps = $empresa->servidores->where('status', 'active')->flatMap->allowedIps->where('status', 'active')->unique('ip_cidr')->sortBy('ip_cidr');
        $rpzListas = $empresa->servidores->where('status', 'active')->flatMap->listas->where('status', 'active')->unique('id')->sortBy('nome');
    @endphp

    <style>
        #company-rpz-access > summary { cursor:pointer; }
        .company-feed-help,.company-feed-ips { font-size:12px; color:var(--text-soft); line-height:1.8; }
        .company-feed-ips { margin-top:18px; padding:12px 14px; border:1px solid var(--border-soft); border-radius:8px; }
        .company-feed-ips summary { cursor:pointer; }
        .company-feed-ips code { display:block; margin-top:8px; overflow-wrap:anywhere; }
        .company-acl-form { display:flex; flex-wrap:wrap; gap:8px; align-items:flex-end; margin-top:18px; }
        .company-acl-form .field-group { flex:1; min-width:160px; margin:0; }
    </style>
    @include('empresas.partials.company-profile')
    @if ($isAdmin)
    <div class="details-grid">
        <div class="panel details-card-wide"><div class="panel-header"><div><h2>Exceções de bloqueio</h2><p>Domínios liberados somente para esta empresa.</p></div><a href="{{ route('empresas.exceptions.index', $empresa) }}" class="button button-primary">Gerenciar exceções</a></div></div>
        @if (auth()->user()->isAdmin())
        <div class="panel details-card-wide">
            <div class="panel-header">
                <h2>Usuários</h2>
                <a href="{{ route('usuarios.create', ['empresa_id' => $empresa->id]) }}" class="button button-primary">+ Criar acesso</a>
            </div>
            @if ($empresa->users->isEmpty())
                <div class="empty-state"><span>Nenhum usuário com acesso a esta empresa ainda.</span></div>
            @else
                <div class="table-responsive">
                    <table class="data-table">
                        <thead><tr><th>Nome</th><th>E-mail</th><th class="table-actions-column"></th></tr></thead>
                        <tbody>
                            @foreach ($empresa->users as $user)
                                <tr>
                                    <td>{{ $user->name }}</td>
                                    <td class="table-mono">{{ $user->email }}</td>
                                    <td><a href="{{ route('usuarios.edit', $user) }}" class="table-action-link">Editar</a></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
        @endif

        <details class="panel details-card-wide" id="company-rpz-access" @if(session('acl_test')) open @endif>
            <summary class="panel-header"><h2>Feed agregado da empresa</h2></summary>
            <p class="company-feed-help">URL que reúne as fontes dos servidores ativos desta empresa. Acesso com ACL por IP: somente endereços autorizados.</p>
            @if ($rpzUrl)
                <div class="field-group" style="margin-top:14px">
                    <label for="company-rpz-url">URL RPZ da empresa</label>
                    <div class="endpoint-secret-row"><input class="endpoint-code-field" id="company-rpz-url" value="{{ $rpzUrl }}" readonly><button type="button" class="button button-secondary" data-company-copy="company-rpz-url">Copiar URL</button></div>
                </div>
                @php $servidorConfiguracao = $empresa->servidores->first(fn ($servidor) => $servidor->tipo_dns !== 'mikrotik'); @endphp
                @if ($servidorConfiguracao)
                    <p style="font-size:12px;color:var(--text-soft);margin-top:14px">A configuração e os testes de resolução ficam nos detalhes do servidor.</p>
                    <a href="{{ route('servidores.show', $servidorConfiguracao) }}" class="button button-secondary">Ver configuração no servidor</a>
                @endif
            @endif
            <details class="company-feed-ips"><summary>Ver IPs autorizados</summary>
            @forelse ($rpzAllowedIps as $ip)<code>{{ $ip->ip_cidr }}</code>@empty<p>Nenhuma ACL ativa; a URL curta nega todo acesso.</p>@endforelse
            </details>
            <form action="{{ route('empresas.rpz-acl-test', $empresa) }}" method="POST" class="company-acl-form">
                @csrf
                <div class="field-group"><label for="rpz-acl-test-ip">Testar ACL</label><input class="form-control" id="rpz-acl-test-ip" name="ip" placeholder="IPv4 ou IPv6" required></div>
                <button class="button button-secondary" type="submit">Testar ACL</button>
            </form>
            @if (session('acl_test'))<p><strong>{{ session('acl_test.allowed') ? 'AUTORIZADO' : 'NEGADO' }}</strong> — <code>{{ session('acl_test.ip') }}</code></p>@endif
        </details>
    </div>
    <script>
        document.querySelectorAll('[data-company-copy]').forEach(function (button) {
            button.addEventListener('click', function () {
                var target = document.getElementById(button.dataset.companyCopy);
                navigator.clipboard.writeText(('value' in target ? target.value : target.textContent).trim());
            });
        });
    </script>
    @endif
@endsection
