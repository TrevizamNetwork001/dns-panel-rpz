@extends('layouts.app')

@section('title', $usuario->exists ? 'Editar usuário' : 'Novo usuário')

@section('content')
    <style>
        .admin-user-form { max-width: 980px; margin: 0 auto; }
        .admin-user-form .user-form-breadcrumb { display:flex; align-items:center; gap:12px; margin-bottom:24px; color:var(--text-soft); font-size:12px; }
        .admin-user-form .user-form-breadcrumb a { color:var(--cyan); }
        .admin-user-form .page-eyebrow { color:var(--cyan); font-size:11px; letter-spacing:.14em; }
        .admin-user-form h1 { margin:8px 0; font-size:32px; letter-spacing:-.035em; }
        .admin-user-form .page-heading p { margin:0; color:var(--text-soft); font-size:13px; }
        .admin-user-form .form-panel { padding:22px; border-radius:13px; box-shadow:none; }
        .admin-user-form .panel-header { padding-bottom:18px; margin-bottom:20px; border-color:var(--border-soft); }
        .admin-user-form .panel-header h2 { display:flex; align-items:center; gap:12px; margin:0; font-size:15px; }
        .admin-user-form .panel-header h2 > svg { color:var(--cyan); }
        .admin-user-form .form-control { min-height:46px; padding:12px 14px; border:1px solid var(--border); border-radius:9px; background:var(--surface-soft); color:var(--text); font-size:13px; }
        .admin-user-form .form-control:focus { border-color:var(--cyan); box-shadow:0 0 0 3px var(--cyan-soft); }
        .admin-user-form .user-form-note { padding:12px 14px; margin:18px 0 0; border:1px solid var(--border-soft); border-radius:9px; background:var(--surface-soft); color:var(--text-soft); font-size:11px; line-height:1.6; }
        .admin-user-form .form-actions { justify-content:flex-end; margin-top:18px; }
        html:not([data-theme="light"]) .admin-user-form { --surface-soft:#0b2130; --border:#234151; --border-soft:#173444; --text-soft:#adc5d9; --cyan:#00cbee; }
        @media (max-width:600px) { .admin-user-form h1 { font-size:27px; } .admin-user-form .page-heading { align-items:flex-start; } .admin-user-form .form-panel { padding:18px; } }
    </style>
    <div class="admin-user-form">
        <nav class="user-form-breadcrumb" aria-label="Navegação"><a href="{{ route('usuarios.index') }}">Usuários</a><x-ui-icon name="chevron" size="14" /><span>{{ $usuario->exists ? 'Editar usuário' : 'Novo usuário' }}</span></nav>
    <div class="page-heading page-heading-compact">
        <div>
            <div class="page-eyebrow">Gestão</div>
            <h1>{{ $usuario->exists ? 'Editar usuário' : 'Novo usuário' }}</h1>
        </div>
    </div>

    <div class="panel form-panel">
        <div class="panel-header"><h2><x-ui-icon name="user" size="23" /> Dados de acesso</h2></div>
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
                <p class="user-form-note">Uma senha temporária é gerada automaticamente e mostrada só uma vez após salvar.</p>
            @endunless

            <div class="form-actions">
                <a href="{{ route('usuarios.index') }}" class="button button-secondary">Cancelar</a>
                <button type="submit" class="button button-primary">Salvar</button>
            </div>
        </form>
    </div>
    </div>
@endsection
