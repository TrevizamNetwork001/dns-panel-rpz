@extends('layouts.app')

@section('title', 'Usuários')

@section('content')
    <div class="page-heading page-heading-compact">
        <div>
            <h1>Usuários</h1>
            <p>{{ $usuarios->total() }} cadastrados</p>
        </div>
        <div class="page-actions">
            <a href="{{ route('usuarios.create') }}" class="button button-primary">+ Novo usuário</a>
        </div>
    </div>

    <div class="panel">
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
                <table class="data-table">
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
                                <td>{{ $usuario->name }}</td>
                                <td class="table-mono">{{ $usuario->email }}</td>
                                <td>
                                    <span class="status-pill @if($usuario->isAdmin()) is-info @else is-active @endif">
                                        {{ $usuario->isAdmin() ? 'Administrador' : 'Cliente' }}
                                    </span>
                                </td>
                                <td>{{ $usuario->empresa->nome ?? '-' }}</td>
                                <td>
                                    <x-actions-menu label="Ações do usuário {{ $usuario->name }}">
                                        <a href="{{ route('usuarios.edit', $usuario) }}" class="actions-menu-item">Editar</a>
                                        <form action="{{ route('usuarios.reset-password', $usuario) }}" method="POST">
                                            @csrf
                                            <button type="submit" class="actions-menu-item" onclick="return confirm('Gerar nova senha temporária para este usuário?')">Redefinir senha</button>
                                        </form>
                                        <div class="actions-menu-divider"></div>
                                        <form action="{{ route('usuarios.destroy', $usuario) }}" method="POST">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="actions-menu-item actions-menu-item-danger" onclick="return confirm('Remover usuário?')">Remover</button>
                                        </form>
                                    </x-actions-menu>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{ $usuarios->links() }}
@endsection
