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
            </div>

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
