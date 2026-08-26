@extends('layouts.app')

@section('title', 'Fontes')

@section('content')
    <div class="page-heading page-heading-compact">
        <div>
            <h1>Fontes</h1>
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
                    <strong>Nenhuma fonte cadastrada</strong>
                    <span>Cadastre a primeira fonte de bloqueio para vincular aos endpoints RPZ.</span>
                </div>
            </div>
        @else
            <div class="table-responsive">
                <table class="data-table @if(auth()->user()->isAdmin()) admin-fontes-table @endif">
                    <thead>
                        <tr>
                            <th>Nome</th>
                            <th>Tipo</th>
                            <th>Empresa · Escopo</th>
                            <th>Domínios ativos</th>
                            <th>Última atualização</th>
                            <th>Status</th>
                            <th class="table-actions-column"></th>
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
                                <td>{{ $lista->empresa?->nome ?? 'Catálogo (todas)' }}</td>
                                <td class="table-mono">{{ auth()->user()->isAdmin() ? number_format($lista->dominios_ativos_count, 0, ',', '.') : $lista->dominios_ativos_count }}</td>
                                <td class="table-mono">
                                    @if (auth()->user()->isAdmin())
                                        <time datetime="{{ $ultimaAtualizacao?->toIso8601String() }}" title="{{ $ultimaAtualizacao?->format('d/m/Y H:i:s') ?? 'Sem atualização' }}">
                                            {{ \App\Http\Controllers\DashboardController::relativoPt($ultimaAtualizacao) }}
                                        </time>
                                    @else
                                        {{ $ultimaAtualizacao?->format('d/m/Y H:i') ?? '-' }}
                                    @endif
                                </td>
                                <td>
                                    <span class="status-pill @if($lista->status === 'active') is-active @else is-inactive @endif">
                                        {{ $lista->status === 'active' ? 'Ativa' : 'Inativa' }}
                                    </span>
                                </td>
                                <td>
                                    @if (auth()->user()->isAdmin())
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
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{ $listas->links() }}
@endsection
