@extends('layouts.app')

@section('title', $usuario->exists ? 'Editar usuário' : 'Novo usuário')

@section('content')
    <div class="page-heading">
        <div>
            <div class="page-eyebrow">Gestão</div>
            <h1>{{ $usuario->exists ? 'Editar usuário' : 'Novo usuário' }}</h1>
        </div>
    </div>

    <div class="panel form-panel">
        <form action="{{ $usuario->exists ? route('usuarios.update', $usuario) : route('usuarios.store') }}" method="POST">
            @csrf
            @if ($usuario->exists)
                @method('PUT')
            @endif

            <div class="form-grid">
                <div class="field-group">
                    <label for="name">Nome</label>
                    <input class="form-control" type="text" id="name" name="name" value="{{ old('name', $usuario->name) }}" required>
                </div>

                <div class="field-group">
                    <label for="email">E-mail (login)</label>
                    <input class="form-control" type="email" id="email" name="email" value="{{ old('email', $usuario->email) }}" required>
                </div>

                <div class="field-group">
                    <label for="role">Papel</label>
                    <select class="form-control" id="role" name="role" onchange="document.getElementById('empresa-field').style.display = this.value === 'cliente' ? 'block' : 'none'">
                        <option value="cliente" @selected(old('role', $usuario->role ?? 'cliente') === 'cliente')>Cliente</option>
                        <option value="admin" @selected(old('role', $usuario->role) === 'admin')>Administrador</option>
                    </select>
                </div>

                <div class="field-group" id="empresa-field" style="{{ (old('role', $usuario->role ?? 'cliente')) === 'admin' ? 'display:none' : '' }}">
                    <label for="empresa_id">Empresa</label>
                    <select class="form-control" id="empresa_id" name="empresa_id">
                        <option value="">-- selecione --</option>
                        @foreach ($empresas as $empresa)
                            <option value="{{ $empresa->id }}" @selected(old('empresa_id', $usuario->empresa_id) == $empresa->id)>{{ $empresa->nome }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            @unless ($usuario->exists)
                <p style="color:var(--text-muted);font-size:11px;margin-top:10px">Uma senha temporária é gerada automaticamente e mostrada só uma vez após salvar.</p>
            @endunless

            <div class="form-actions">
                <a href="{{ route('usuarios.index') }}" class="button button-secondary">Cancelar</a>
                <button type="submit" class="button button-primary">Salvar</button>
            </div>
        </form>
    </div>
@endsection
