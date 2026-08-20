@extends('layouts.app')

@section('title', 'Usuários')

@section('content')
    <div class="page-heading">
        <div>
            <div class="page-eyebrow">Gestão</div>
            <h1>Usuários</h1>
            <p>Contas de acesso ao painel — administradores e clientes.</p>
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
                                    <div class="table-actions">
                                        <a href="{{ route('usuarios.edit', $usuario) }}" class="table-action-link">Editar</a>
                                        <form action="{{ route('usuarios.reset-password', $usuario) }}" method="POST">
                                            @csrf
                                            <button type="submit" class="table-action-link" style="background:none;border:0" onclick="return confirm('Gerar nova senha temporária para este usuário?')">Resetar senha</button>
                                        </form>
                                        <form action="{{ route('usuarios.destroy', $usuario) }}" method="POST">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="table-action-link" style="background:none;border:0" onclick="return confirm('Remover usuário?')">Remover</button>
                                        </form>
                                    </div>
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
