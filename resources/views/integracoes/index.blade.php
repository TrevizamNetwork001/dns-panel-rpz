@extends('layouts.app')

@section('title', 'Integrações API')

@section('content')
    <style>
        .tenant-integration { display:grid; gap:20px; max-width:1050px; margin-inline:auto; }
        .tenant-integration .panel { min-width:0; }
        .tenant-integration form { display:flex; flex-wrap:wrap; align-items:end; gap:10px; }
        .tenant-integration .table-scroll { overflow-x:auto; }
        .tenant-integration pre { overflow-x:auto; padding:12px; background:var(--surface-soft); border:1px solid var(--border); border-radius:8px; }
        .tenant-integration input[readonly] { width:100%; margin-block:8px; }
        .tenant-integration td form { flex-wrap:nowrap; }
        .tenant-integration td input[type=text] { min-width:12rem; }
    </style>
    <div class="tenant-integration">
        <div class="page-heading">
            <div>
                <h1>Integrações API</h1>
                <p>Gerencie credenciais, bloqueios e integrações do DNS/RPZ da sua empresa.</p>
            </div>
            <a class="btn btn-secondary" href="{{ route('integracoes.openapi') }}">OpenAPI (JSON)</a>
        </div>

        @if ($token)
            <section class="panel" role="status">
                <h2>Credencial gerada</h2>
                <label for="new-api-token">Copie o token agora. Ele só aparece nesta abertura.</label>
                <input class="form-control" id="new-api-token" readonly value="{{ $token }}" autocomplete="off" spellcheck="false">
            </section>
        @endif

        <section class="panel">
            <div class="panel-header"><h2>Credenciais</h2></div>
            <p>Cada credencial é individual, vale por 90 dias e pode ser revogada a qualquer momento.</p>
            <form action="{{ route('integracoes.credentials.store') }}" method="post">
                @csrf
                <div class="field-group">
                    <label for="integration-name">Nome da integração</label>
                    <input class="form-control" id="integration-name" name="name" value="{{ old('name') }}" maxlength="80" required>
                </div>
                <div class="field-group">
                    <label for="integration-scope">Permissão</label>
                    <select class="form-control" id="integration-scope" name="scope">
                        <option value="read" @selected(old('scope') === 'read')>Somente consulta</option>
                        <option value="write" @selected(old('scope') === 'write')>Consulta e edição</option>
                        <option value="report" @selected(old('scope') === 'report')>Confirmar versão de um servidor</option>
                    </select>
                </div>
                <div class="field-group">
                    <label for="integration-server">Servidor (para confirmação)</label>
                    <select class="form-control" id="integration-server" name="server_id">
                        <option value="">Selecione um servidor</option>
                        @foreach ($servers as $server)
                            <option value="{{ $server->id }}" @selected(old('server_id') == $server->id)>{{ $server->nome }}</option>
                        @endforeach
                    </select>
                </div>
                <button class="btn btn-primary">Gerar credencial</button>
            </form>
            @if ($credentials->isNotEmpty())
                <div class="table-scroll">
                    <table class="data-table">
                        <thead><tr><th>Integração</th><th>Permissão</th><th>Validade</th><th>Ação</th></tr></thead>
                        <tbody>
                            @foreach ($credentials as $credential)
                                <tr>
                                    <td>{{ $credential->name }}</td>
                                    <td>{{ match ($credential->scope) { 'write' => 'Consulta e edição', 'report' => 'Confirmação · servidor #'.$credential->servidor_id, default => 'Somente consulta' } }}</td>
                                    <td>{{ $credential->expires_at->isPast() ? 'Expirada' : $credential->expires_at->format('d/m/Y') }}</td>
                                    <td><form method="post" action="{{ route('integracoes.credentials.destroy', $credential->id) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-secondary" aria-label="Revogar {{ $credential->name }}">Revogar</button>
                                    </form></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                {{ $credentials->withQueryString()->links() }}
            @else
                <p>Nenhuma credencial criada.</p>
            @endif
        </section>

        <section class="panel">
            <div class="panel-header"><h2>Bloqueios editáveis</h2></div>
            @forelse ($lists as $list)
                <form action="{{ route('integracoes.rules.store', $list->id) }}" method="post">
                    @csrf
                    <div class="field-group">
                        <label for="new-domain-{{ $list->id }}">Domínio em {{ $list->nome }}</label>
                        <input class="form-control" id="new-domain-{{ $list->id }}" name="dominio" placeholder="exemplo.com" maxlength="255" required>
                    </div>
                    <button class="btn btn-primary">Cadastrar</button>
                </form>
            @empty
                <p>Peça ao administrador para habilitar a edição em uma fonte manual da sua empresa.</p>
            @endforelse
            @if ($rules->isNotEmpty())
                <div class="table-scroll">
                    <table class="data-table">
                        <thead><tr><th>Fonte</th><th>Domínio e status</th><th>Ação</th></tr></thead>
                        <tbody>
                            @foreach ($rules as $rule)
                                <tr>
                                    <td>{{ $lists->find($rule->lista_id)?->nome }}</td>
                                    <td><form action="{{ route('integracoes.rules.update', $rule->id) }}" method="post">
                                        @csrf
                                        @method('PATCH')
                                        <input class="form-control" type="text" name="dominio" value="{{ $rule->dominio }}" aria-label="Domínio da regra {{ $rule->id }}" maxlength="255" required>
                                        <select class="form-control" name="ativo" aria-label="Status da regra {{ $rule->id }}">
                                            <option value="1" @selected($rule->ativo)>Ativo</option>
                                            <option value="0" @selected(! $rule->ativo)>Inativo</option>
                                        </select>
                                        <button class="btn btn-secondary">Salvar</button>
                                    </form></td>
                                    <td><form action="{{ route('integracoes.rules.destroy', $rule->id) }}" method="post">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-secondary" aria-label="Remover {{ $rule->dominio }}">Remover</button>
                                    </form></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                {{ $rules->links() }}
            @endif
        </section>

        <section class="panel">
            <div class="panel-header"><h2>Manual da API</h2></div>
            @include('integracoes.manual')
        </section>
    </div>
@endsection
