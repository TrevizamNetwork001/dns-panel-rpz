@extends('layouts.app')

@section('title', auth()->user()->isAdmin() ? 'Fontes' : 'Listas')

@section('content')
    <div class="page-heading page-heading-compact">
        <div>
            <h1>{{ auth()->user()->isAdmin() ? 'Fontes' : 'Listas' }}</h1>
            <p>{{ $listas->total() }} cadastradas · {{ $listasAtivas }} ativas</p>
        </div>
        <div class="page-actions">
            @if (auth()->user()->isAdmin())
            <a href="{{ route('anatel.dashboard') }}" class="button button-secondary">Importar PDFs ANATEL</a>
            <a href="{{ route('listas.create') }}" class="button button-primary">+ Nova fonte</a>
            @endif
        </div>
    </div>

    <div class="panel @if(auth()->user()->isAdmin()) admin-fontes-page @endif">
        @if ($listas->isEmpty())
            <div class="empty-state empty-state-large">
                <div class="empty-state-icon">+</div>
                <div>
                    <strong>{{ auth()->user()->isAdmin() ? 'Nenhuma fonte cadastrada' : 'Nenhuma lista disponível' }}</strong>
                    <span>{{ auth()->user()->isAdmin() ? 'Cadastre a primeira fonte de bloqueio para vincular aos endpoints RPZ.' : 'As listas liberadas para sua empresa aparecerão aqui.' }}</span>
                </div>
            </div>
        @else
            <div class="table-responsive">
                <table class="data-table @if(auth()->user()->isAdmin()) admin-fontes-table @endif">
                    <thead>
                        <tr>
                            <th>{{ auth()->user()->isAdmin() ? 'Nome' : 'Lista' }}</th>
                            <th>Tipo</th>
                            @if (auth()->user()->isAdmin())
                            <th>Empresa · Escopo</th>
                            @endif
                            <th>Domínios ativos</th>
                            @if (auth()->user()->isAdmin())
                            <th>Última atualização</th>
                            <th>Status</th>
                            <th class="table-actions-column"></th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($listas as $lista)
                            @php
                                $tipo = $lista->isExterna() ? 'Externa' : ($lista->empresa_id ? 'Própria' : 'Catálogo');
                                $tipoClasse = match ($tipo) {
                                    'Externa' => 'is-info',
                                    'Própria' => 'is-active',
                                    default => 'is-muted',
                                };
                                $ultimaAtualizacao = $lista->last_sync_at ?? $lista->updated_at;
                            @endphp
                            <tr>
                                <td @class(['admin-fontes-name' => auth()->user()->isAdmin()])>
                                    <a href="{{ route('listas.show', $lista) }}" class="table-primary-link">{{ $lista->nome }}</a>
                                </td>
                                <td><span class="status-pill {{ $tipoClasse }}">{{ $tipo }}</span></td>
                                @if (auth()->user()->isAdmin())
                                <td>{{ $lista->empresa?->nome ?? 'Catálogo (todas)' }}</td>
                                @endif
                                <td class="table-mono">{{ number_format($lista->dominios_ativos_count, 0, ',', '.') }}</td>
                                @if (auth()->user()->isAdmin())
                                <td class="table-mono">
                                    <time datetime="{{ $ultimaAtualizacao?->toIso8601String() }}" title="{{ $ultimaAtualizacao?->format('d/m/Y H:i:s') ?? 'Sem atualização' }}">
                                        {{ \App\Http\Controllers\DashboardController::relativoPt($ultimaAtualizacao) }}
                                    </time>
                                </td>
                                <td>
                                    <span class="status-pill @if($lista->status === 'active') is-active @else is-inactive @endif">
                                        {{ $lista->status === 'active' ? 'Ativa' : 'Inativa' }}
                                    </span>
                                </td>
                                <td>
                                    <x-actions-menu label="Ações da fonte {{ $lista->nome }}">
                                            <a href="{{ route('listas.show', $lista) }}" class="actions-menu-item">Ver detalhes</a>
                                            <a href="{{ route('listas.edit', $lista) }}" class="actions-menu-item">Editar</a>
                                            <a href="{{ route('listas.historico', $lista) }}" class="actions-menu-item">Histórico</a>
                                            @if ($lista->isExterna())
                                                <form action="{{ route('listas.toggle-sync', $lista) }}" method="POST">
                                                    @csrf
                                                    @method('PATCH')
                                                    <button type="submit" class="actions-menu-item">{{ $lista->sync_ativo ? 'Pausar sincronização' : 'Reativar sincronização' }}</button>
                                                </form>
                                            @else
                                                <div class="actions-menu-divider"></div>
                                                <form action="{{ route('listas.destroy', $lista) }}" method="POST">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="actions-menu-item actions-menu-item-danger" onclick="return confirm('Remover fonte?')">Remover</button>
                                                </form>
                                            @endif
                                    </x-actions-menu>
                                </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{ $listas->links() }}
@endsection
