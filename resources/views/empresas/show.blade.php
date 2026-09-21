@extends('layouts.app')

@section('title', $empresa->nome)

@section('content')
    @php
        $isAdmin = auth()->user()->isAdmin();
        $servidoresUtilizados = $empresa->servidores->count();
        $capacidadeLicencaAtiva = $empresa->licencas->filter->isValid()->sum('max_servidores');
        $rpzUrl = $empresa->rpz_slug ? url('/rpz/'.$empresa->rpz_slug.'.zone') : null;
        $rpzHost = parse_url(config('app.url'), PHP_URL_HOST) ?: 'localhost';
        $rpzZonefile = '/var/lib/unbound/'.$rpzHost.'.zone';
        $rpzConfig = "rpz:\n    name: \"{$rpzHost}\"\n    zonefile: \"{$rpzZonefile}\"\n    url: \"{$rpzUrl}\"\n    # rpz-log: yes\n    # rpz-log-name: \"dns-panel-rpz\"";
        $rpzAllowedIps = $empresa->servidores->where('status', 'active')->flatMap->allowedIps->where('status', 'active')->unique('ip_cidr')->sortBy('ip_cidr');
        $rpzListas = $empresa->servidores->where('status', 'active')->flatMap->listas->where('status', 'active')->unique('id')->sortBy('nome');
    @endphp

    @include('empresas.partials.company-profile')
    @if ($isAdmin)
    @include('empresas.partials.admin-servers')
    <div class="details-grid">
        {{-- Listas próprias do cliente são função do painel 2.0: por ora só o admin vê este painel. --}}
        @if ($isAdmin)
        <div class="panel details-card-wide">
            <div class="panel-header"><h2>Fontes</h2></div>
            @if ($empresa->listas->isEmpty())
                <div class="empty-state"><span>Nenhuma fonte cadastrada.</span></div>
            @else
                <div class="table-responsive">
                    <table class="data-table">
                        <tbody>
                            @foreach ($empresa->listas as $lista)
                                <tr><td><a href="{{ route('listas.show', $lista) }}" class="table-primary-link">{{ $lista->nome }}</a></td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
        @endif

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
            <summary class="panel-header"><h2>Distribuição RPZ empresarial</h2></summary>
            <dl class="details-list">
                <div><dt>Slug</dt><dd class="table-mono">{{ $empresa->rpz_slug ?? 'não configurado' }}</dd></div>
                <div><dt>Método de acesso</dt><dd>ACL por IP</dd></div>
                <div><dt>Status</dt><dd>{{ $empresa->status === 'active' ? 'Ativo' : 'Inativo' }}</dd></div>
            </dl>
            @if ($rpzUrl)
                <div class="field-group" style="margin-top:14px">
                    <label>URL RPZ</label>
                    <div class="endpoint-secret-row"><input class="endpoint-code-field" id="company-rpz-url" value="{{ $rpzUrl }}" readonly><button type="button" class="button button-secondary" data-company-copy="company-rpz-url">Copiar URL</button></div>
                </div>
                <div class="field-group" style="margin-top:14px">
                    <label>Configuração do Unbound</label>
                    <pre class="endpoint-config-snippet" id="company-rpz-config">{{ $rpzConfig }}</pre>
                    <button type="button" class="button button-secondary" data-company-copy="company-rpz-config">Copiar configuração Unbound</button>
                    <small>Requer o módulo <code>respip</code>. O <code>zonefile</code> é relativo ao diretório/chroot do Unbound; use caminho absoluto se a instalação exigir.</small>
                </div>
            @endif
            <h3 style="margin-top:18px">IPs autorizados</h3>
            @forelse ($rpzAllowedIps as $ip)<code style="display:block">{{ $ip->ip_cidr }}</code>@empty<p>Nenhuma ACL ativa; a URL curta nega todo acesso.</p>@endforelse
            <h3 style="margin-top:18px">Listas vinculadas</h3>
            <p>{{ $rpzListas->pluck('nome')->join(', ') ?: 'Nenhuma lista ativa vinculada.' }}</p>
            <form action="{{ route('empresas.rpz-acl-test', $empresa) }}" method="POST" style="display:flex;gap:8px;align-items:end;margin-top:18px">
                @csrf
                <div class="field-group" style="flex:1"><label for="rpz-acl-test-ip">Testar ACL</label><input class="form-control" id="rpz-acl-test-ip" name="ip" placeholder="IPv4 ou IPv6" required></div>
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
