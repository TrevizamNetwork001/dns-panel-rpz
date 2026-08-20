@extends('layouts.app')

@section('title', $empresa->exists ? 'Editar empresa' : 'Nova empresa')

@section('content')
    <div class="page-heading">
        <div>
            <div class="page-eyebrow">Gestão</div>
            <h1>{{ $empresa->exists ? 'Editar empresa' : 'Nova empresa' }}</h1>
        </div>
    </div>

    <div class="panel form-panel">
        <form action="{{ $empresa->exists ? route('empresas.update', $empresa) : route('empresas.store') }}" method="POST">
            @csrf
            @if ($empresa->exists)
                @method('PUT')
            @endif

            <div class="form-grid">
                <div class="field-group field-span-2">
                    <label for="nome">Nome</label>
                    <input class="form-control" type="text" id="nome" name="nome" value="{{ old('nome', $empresa->nome) }}" required>
                </div>

                <div class="field-group">
                    <label for="documento">Documento (CNPJ)</label>
                    <input class="form-control" type="text" id="documento" name="documento" value="{{ old('documento', $empresa->documento) }}">
                </div>

                <div class="field-group">
                    <label for="email_contato">E-mail de contato</label>
                    <input class="form-control" type="email" id="email_contato" name="email_contato" value="{{ old('email_contato', $empresa->email_contato) }}">
                </div>

                <div class="field-group">
                    <label for="status">Status</label>
                    <select class="form-control" id="status" name="status">
                        <option value="active" @selected(old('status', $empresa->status) === 'active')>Ativa</option>
                        <option value="inactive" @selected(old('status', $empresa->status) === 'inactive')>Inativa</option>
                    </select>
                </div>
            </div>

            <div class="form-actions">
                <a href="{{ route('empresas.index') }}" class="button button-secondary">Cancelar</a>
                <button type="submit" class="button button-primary">Salvar</button>
            </div>
        </form>
    </div>
@endsection
