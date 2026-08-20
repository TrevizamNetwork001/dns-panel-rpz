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

                @if ($servidor->exists)
                    <div class="field-group">
                        <label>Token</label>
                        <code>{{ $servidor->token }}</code>
                    </div>
                @endif
            </div>

            @unless ($servidor->exists)
                <p style="color:var(--text-muted);font-size:11px;margin-top:10px">O token é gerado automaticamente ao salvar.</p>
            @endunless

            <div class="form-actions">
                <a href="{{ route('servidores.index') }}" class="button button-secondary">Cancelar</a>
                <button type="submit" class="button button-primary" @if(!empty($licencaBlocker)) disabled @endif>Salvar</button>
            </div>
        </form>
    </div>
@endsection
