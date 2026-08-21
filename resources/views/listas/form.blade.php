@extends('layouts.app')

@section('title', $lista->exists ? 'Editar lista' : 'Nova lista')

@section('content')
    <div class="page-heading">
        <div>
            <div class="page-eyebrow">Gestão</div>
            <h1>{{ $lista->exists ? 'Editar lista' : 'Nova lista' }}</h1>
        </div>
    </div>

    <div class="panel form-panel">
        <form action="{{ $lista->exists ? route('listas.update', $lista) : route('listas.store') }}" method="POST">
            @csrf
            @if ($lista->exists)
                @method('PUT')
            @endif

            <div class="form-grid">
                <div class="field-group">
                    <label for="empresa_id">Empresa</label>
                    <select class="form-control" id="empresa_id" name="empresa_id">
                        <option value="" @selected(old('empresa_id', $lista->empresa_id) === null)>— Lista de catálogo (todas as empresas) —</option>
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
                    <textarea class="form-control" id="descricao" name="descricao">{{ old('descricao', $lista->descricao) }}</textarea>
                </div>

                <div class="field-group">
                    <label for="status">Status</label>
                    <select class="form-control" id="status" name="status">
                        <option value="active" @selected(old('status', $lista->status) === 'active')>Ativa</option>
                        <option value="inactive" @selected(old('status', $lista->status) === 'inactive')>Inativa</option>
                    </select>
                </div>

                <div class="field-group">
                    <label for="origem">Origem</label>
                    <select class="form-control" id="origem" name="origem">
                        <option value="manual" @selected(old('origem', $lista->origem ?? 'manual') === 'manual')>Manual (domínios cadastrados aqui)</option>
                        <option value="externa" @selected(old('origem', $lista->origem) === 'externa')>Externa (sincroniza de uma URL automaticamente)</option>
                    </select>
                </div>

                <div class="field-group" id="campo-fonte-url">
                    <label for="fonte_url">URL do feed</label>
                    <input class="form-control" type="url" id="fonte_url" name="fonte_url" value="{{ old('fonte_url', $lista->fonte_url) }}" placeholder="https://exemplo.com/lista-de-bloqueio.txt">
                    <p style="color:var(--text-muted);font-size:10px;margin-top:6px">Precisa ter pelo menos 100 domínios — feeds menores são rejeitados automaticamente (proteção contra feed fora do ar esvaziar a lista sem querer).</p>
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
                <div class="field-group" style="margin-top:18px">
                    <label>
                        Servidores vinculados
                        @if (! $lista->empresa_id)
                            <span style="color:var(--text-muted);font-weight:400;text-transform:none;letter-spacing:0">— lista de catálogo, mostrando servidores de todas as empresas</span>
                        @endif
                    </label>
                    <div style="display:grid;gap:8px">
                        @forelse ($servidoresDisponiveis as $servidor)
                            <label class="checkbox-label">
                                <input type="checkbox" name="servidor_ids[]" value="{{ $servidor->id }}"
                                    @checked($lista->servidores->contains($servidor->id))>
                                <div>
                                    <strong>{{ $servidor->nome }}</strong>
                                    @if (! $lista->empresa_id)
                                        <small>{{ $servidor->empresa->nome }}</small>
                                    @endif
                                </div>
                            </label>
                        @empty
                            <p style="color:var(--text-muted);font-size:11px">Nenhum servidor disponível para vincular.</p>
                        @endforelse
                    </div>
                </div>
            @else
                <p style="color:var(--text-muted);font-size:11px;margin-top:14px">Salve a lista para poder vincular servidores.</p>
            @endif

            <div class="form-actions">
                <a href="{{ route('listas.index') }}" class="button button-secondary">Cancelar</a>
                <button type="submit" class="button button-primary">Salvar</button>
            </div>
        </form>
    </div>
@endsection
