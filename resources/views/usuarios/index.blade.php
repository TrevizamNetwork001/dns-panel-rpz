@extends('layouts.app')

@section('title', 'Usuários')

@section('content')
    <style>
        .admin-users-compact { max-width: 1400px; margin: 0 auto; }
        .admin-users-compact .page-heading { align-items: center; margin-bottom: 22px; }
        .admin-users-compact .page-heading h1 { font-size: 28px; letter-spacing: -.03em; }
        .admin-users-compact .page-heading p { color: var(--text-soft); font-size: 12px; }
        .admin-users-compact .page-heading .button { min-height: 40px; }
        .admin-users-compact .users-page { padding: 0; overflow: hidden; }
        .admin-users-compact .users-table { table-layout: fixed; }
        .admin-users-compact .users-table th { padding: 13px 12px; }
        .admin-users-compact .users-table th:first-child { width: 23%; }
        .admin-users-compact .users-table th:nth-child(2) { width: 25%; }
        .admin-users-compact .users-table th:nth-child(3) { width: 18%; }
        .admin-users-compact .users-table th:nth-child(4) { width: 24%; }
        .admin-users-compact .users-table th:last-child { width: 10%; }
        .admin-users-compact .users-table td { padding: 16px 12px; }
        .admin-users-compact .users-table td:first-child { padding-left: 18px; }
        .admin-users-compact .users-table tbody tr:hover { background: var(--surface-soft); }
        .admin-user-name { display: inline-flex; align-items: center; gap: 8px; color: var(--text); font-weight: 550; }
        .admin-user-name svg { color: var(--cyan); flex-shrink: 0; }
        .admin-users-compact .user-actions { display: flex; justify-content: flex-end; }
        .admin-users-compact .actions-menu-toggle { width: 31px; height: 31px; }
        @media (max-width: 700px) {
            .admin-users-compact .page-heading { align-items: flex-start; flex-direction: column; }
            .admin-users-compact .users-table thead { display: none; }
            .admin-users-compact .users-table, .admin-users-compact .users-table tbody, .admin-users-compact .users-table tr { display: block; width: 100%; }
            .admin-users-compact .users-table tr { padding: 14px 16px; border-top: 1px solid var(--border-soft); }
            .admin-users-compact .users-table td, .admin-users-compact .users-table td:first-child { display: block; width: 100%; padding: 7px 0; border: 0; }
            .admin-users-compact .users-table td:not(:first-child)::before { content: attr(data-label); display: block; margin-bottom: 4px; color: var(--text-muted); font-size: 9px; text-transform: uppercase; }
            .admin-users-compact .user-actions { justify-content: flex-start; margin-top: 6px; }
        }
    </style>
    <div class="admin-users-compact">
    <div class="page-heading page-heading-compact">
        <div>
            <h1>Usuários</h1>
            <p>{{ $usuarios->total() }} cadastrados</p>
        </div>
        <div class="page-actions">
            <a href="{{ route('usuarios.create') }}" class="button button-primary">+ Novo usuário</a>
        </div>
    </div>

    <div class="panel users-page">
        @if ($usuarios->isEmpty())
            <div class="empty-state empty-state-large">
                <div class="empty-state-icon">+</div>
                <div>
                    <strong>Nenhum usuário cadastrado</strong>
                    <span>Crie o primeiro acesso.</span>
                </div>
            </div>
        @else
            <div class="table-responsive">
                <table class="data-table users-table">
                    <thead>
                        <tr>
                            <th>Nome</th>
                            <th>E-mail</th>
                            <th>Papel</th>
                            <th>Empresa</th>
                            <th class="table-actions-column"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($usuarios as $usuario)
                            <tr>
                                <td><span class="admin-user-name"><x-ui-icon name="user" size="18" />{{ $usuario->name }}</span></td>
                                <td class="table-mono" data-label="E-mail">{{ $usuario->email }}</td>
                                <td data-label="Papel">
                                    <span class="status-pill @if($usuario->isAdmin()) is-info @else is-active @endif">
                                        {{ $usuario->isAdmin() ? 'Administrador' : 'Cliente' }}
                                    </span>
                                </td>
                                <td data-label="Empresa">{{ $usuario->empresa->nome ?? '-' }}</td>
                                <td data-label="Ações"><div class="user-actions">
                                    <x-actions-menu label="Ações do usuário {{ $usuario->name }}">
                                        <a href="{{ route('usuarios.edit', $usuario) }}" class="actions-menu-item">Editar</a>
                                        <form action="{{ route('usuarios.reset-password', $usuario) }}" method="POST" class="user-password-reset-form" id="user-password-reset-{{ $usuario->id }}">
                                            @csrf
                                            <button type="submit" class="actions-menu-item" aria-haspopup="dialog" aria-controls="user-password-confirm">Redefinir senha</button>
                                        </form>
                                        <div class="actions-menu-divider"></div>
                                        <form action="{{ route('usuarios.destroy', $usuario) }}" method="POST">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="actions-menu-item actions-menu-item-danger" onclick="return confirm('Remover usuário?')">Remover</button>
                                        </form>
                                    </x-actions-menu></div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{ $usuarios->links('pagination.compact') }}
    </div>

    @include('usuarios.partials.password-reset-dialogs')
@endsection
