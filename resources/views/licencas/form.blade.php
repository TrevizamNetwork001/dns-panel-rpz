@extends('layouts.app')

@section('title', $licenca->exists ? 'Editar licença' : 'Nova licença')

@section('content')
    <div class="page-heading">
        <div>
            <div class="page-eyebrow">Gestão</div>
            <h1>{{ $licenca->exists ? 'Editar licença' : 'Nova licença' }}</h1>
        </div>
    </div>

    <div class="panel form-panel">
        <form action="{{ $licenca->exists ? route('licencas.update', $licenca) : route('licencas.store') }}" method="POST">
            @csrf
            @if ($licenca->exists)
                @method('PUT')
            @endif

            <div class="form-grid">
                <div class="field-group field-span-2">
                    <label for="empresa_id">Empresa</label>
                    <select class="form-control" id="empresa_id" name="empresa_id" required>
                        <option value="">-- selecione --</option>
                        @foreach ($empresas as $empresa)
                            <option value="{{ $empresa->id }}" @selected(old('empresa_id', $licenca->empresa_id) == $empresa->id)>{{ $empresa->nome }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="field-group">
                    <label for="starts_at">Início</label>
                    <input class="form-control" type="date" id="starts_at" name="starts_at" value="{{ old('starts_at', optional($licenca->starts_at)->format('Y-m-d')) }}" required>
                </div>

                <div class="field-group">
                    <label for="expires_at">Expiração</label>
                    <input class="form-control" type="date" id="expires_at" name="expires_at" value="{{ old('expires_at', optional($licenca->expires_at)->format('Y-m-d')) }}">
                </div>

                <div class="field-group">
                    <label for="max_servidores">Máximo de Endpoints RPZ</label>
                    <input class="form-control" type="number" id="max_servidores" name="max_servidores" min="1" value="{{ old('max_servidores', $licenca->max_servidores ?? 1) }}" required>
                </div>

                <div class="field-group">
                    <label for="status">Status</label>
                    <select class="form-control" id="status" name="status">
                        <option value="active" @selected(old('status', $licenca->status) === 'active')>Ativa</option>
                        <option value="inactive" @selected(old('status', $licenca->status) === 'inactive')>Inativa</option>
                        <option value="expired" @selected(old('status', $licenca->status) === 'expired')>Expirada</option>
                    </select>
                </div>
            </div>

            <div class="form-actions">
                <a href="{{ route('licencas.index') }}" class="button button-secondary">Cancelar</a>
                <button type="submit" class="button button-primary">Salvar</button>
            </div>
        </form>
    </div>
@endsection
