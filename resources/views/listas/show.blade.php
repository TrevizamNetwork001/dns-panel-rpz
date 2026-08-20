@extends('layouts.app')

@section('title', $lista->nome)

@section('content')
    <div class="page-heading">
        <div>
            <div class="page-eyebrow">Lista</div>
            <h1>{{ $lista->nome }}</h1>
            <p>
                @if ($lista->empresa)
                    <a href="{{ route('empresas.show', $lista->empresa) }}" class="inline-link">{{ $lista->empresa->nome }}</a>
                @else
                    <span class="status-pill is-active">Catálogo — todas as empresas</span>
                @endif
                @if ($lista->descricao)
                    &middot; {{ $lista->descricao }}
                @endif
            </p>
        </div>
        <div class="page-actions">
            @if (auth()->user()->isAdmin())
            <a href="{{ route('listas.edit', $lista) }}" class="button button-secondary">Editar</a>
            @endif
        </div>
    </div>

    <div class="details-grid">
        <div class="panel">
            <div class="panel-header"><h2>Status</h2></div>
            <span class="status-pill @if($lista->status === 'active') is-active @else is-inactive @endif">
                {{ $lista->status === 'active' ? 'Ativa' : 'Inativa' }}
            </span>
        </div>

        <div class="panel">
            <div class="panel-header"><h2>Servidores vinculados</h2></div>
            @if ($lista->servidores->isEmpty())
                <div class="empty-state"><span>Nenhum servidor vinculado.</span></div>
            @else
                <div class="table-responsive">
                    <table class="data-table">
                        <tbody>
                            @foreach ($lista->servidores as $servidor)
                                <tr>
                                    <td>
                                        <a href="{{ route('servidores.show', $servidor) }}" class="table-primary-link">{{ $servidor->nome }}</a>
                                        @if (! $lista->empresa_id)
                                            <span class="table-secondary-text">{{ $servidor->empresa->nome }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <div class="panel details-card-wide">
            <div class="panel-header">
                <h2>Domínios ({{ $lista->dominios()->count() }})</h2>
                @if (auth()->user()->isAdmin())
                <a href="{{ route('listas.dominios.index', $lista) }}" class="button button-primary">Gerenciar domínios</a>
                @endif
            </div>
            @if ($lista->dominios()->count() === 0)
                <div class="empty-state"><span>Nenhum domínio cadastrado nesta lista ainda.</span></div>
            @else
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr><th>Domínio</th><th>Status</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($lista->dominios()->orderBy('dominio')->take(10)->get() as $dominio)
                                <tr>
                                    <td class="table-mono">{{ $dominio->dominio }}</td>
                                    <td>
                                        <span class="status-pill @if($dominio->ativo) is-active @else is-inactive @endif">
                                            {{ $dominio->ativo ? 'Ativo' : 'Inativo' }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if ($lista->dominios()->count() > 10)
                    <p style="color:var(--text-muted);font-size:10px;margin-top:12px">mostrando os primeiros 10 de {{ $lista->dominios()->count() }} domínios &mdash; use "Gerenciar domínios" para ver todos.</p>
                @endif
            @endif
        </div>
    </div>
@endsection
