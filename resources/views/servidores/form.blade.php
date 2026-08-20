@extends('layouts.app')

@section('title', $servidor->exists ? 'Editar servidor' : 'Novo servidor')

@section('content')
    <div class="page-heading">
        <div>
            <div class="page-eyebrow">Gestão</div>
            <h1>{{ $servidor->exists ? 'Editar servidor' : 'Novo servidor' }}</h1>
        </div>
    </div>

    @isset($licencaBlocker)
        @if ($licencaBlocker)
            <div class="panel">
                <div class="alert-error" style="margin:0">{{ $licencaBlocker }}</div>
            </div>
        @endif
    @endisset

    <div class="panel form-panel">
        <form action="{{ $servidor->exists ? route('servidores.update', $servidor) : route('servidores.store') }}" method="POST">
            @csrf
            @if ($servidor->exists)
                @method('PUT')
            @endif

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

                <div class="field-group">
                    <label for="tipo_dns">Tipo de DNS</label>
                    <select class="form-control" id="tipo_dns" name="tipo_dns">
                        <option value="unbound" @selected(old('tipo_dns', $servidor->tipo_dns ?? 'unbound') === 'unbound')>Unbound</option>
                        <option value="bind9" @selected(old('tipo_dns', $servidor->tipo_dns) === 'bind9')>BIND9 (em breve)</option>
                        <option value="outro" @selected(old('tipo_dns', $servidor->tipo_dns) === 'outro')>Outro</option>
                    </select>
                    <p style="color:var(--text-muted);font-size:10px;margin-top:6px">Hoje o painel só gera zonefile no formato RPZ padrão (funciona com Unbound). BIND9 é suporte futuro.</p>
                </div>

                <div class="field-group">
                    <label for="ip_v4">IPv4 (opcional)</label>
                    <input class="form-control" type="text" id="ip_v4" name="ip_v4" value="{{ old('ip_v4', $servidor->ip_v4) }}" placeholder="203.0.113.10">
                </div>

                <div class="field-group">
                    <label for="ip_v6">IPv6 (opcional)</label>
                    <input class="form-control" type="text" id="ip_v6" name="ip_v6" value="{{ old('ip_v6', $servidor->ip_v6) }}" placeholder="2001:db8::1">
                </div>

                @if ($servidor->exists)
                    <div class="field-group">
                        <label>Token</label>
                        <code>{{ $servidor->token }}</code>
                    </div>
                @endif
            </div>

            @unless ($servidor->exists)
                <p style="color:var(--text-muted);font-size:11px;margin-top:10px">O token é gerado automaticamente ao salvar. IPv4/IPv6 são só informativos por enquanto — não alimentam a restrição de IP automaticamente.</p>
            @endunless

            @if (isset($listasDisponiveis) && $listasDisponiveis->isNotEmpty())
                <div class="field-group" style="margin-top:18px">
                    <label>Listas de bloqueio</label>
                    <div style="display:grid;gap:8px">
                        @foreach ($listasDisponiveis as $lista)
                            <label class="checkbox-label">
                                <input type="checkbox" name="lista_ids[]" value="{{ $lista->id }}"
                                    @checked($servidor->exists && $servidor->listas->contains($lista->id))>
                                <div>
                                    <strong>{{ $lista->nome }}</strong>
                                    <small>{{ $lista->empresa_id ? 'Própria' : 'Catálogo' }}</small>
                                </div>
                            </label>
                        @endforeach
                    </div>
                </div>
            @elseif (auth()->user()->isAdmin() && ! $servidor->exists)
                <p style="color:var(--text-muted);font-size:11px;margin-top:14px">Selecione a empresa e salve para poder escolher as listas.</p>
            @endif

            <div class="form-actions">
                <a href="{{ route('servidores.index') }}" class="button button-secondary">Cancelar</a>
                <button type="submit" class="button button-primary" @if(!empty($licencaBlocker)) disabled @endif>Salvar</button>
            </div>
        </form>
    </div>
@endsection
