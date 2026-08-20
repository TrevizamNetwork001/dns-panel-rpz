@extends('layouts.app')

@section('title', 'Sugestões de domínio')

@section('content')
    <div class="page-heading">
        <div>
            <div class="page-eyebrow">Sugestões</div>
            <h1>Sugestões de domínio</h1>
            <p>{{ auth()->user()->isAdmin() ? 'Todas as sugestões recebidas de todas as empresas.' : 'Sugestões enviadas pela sua empresa.' }}</p>
        </div>
        <div class="page-actions">
            @unless (auth()->user()->isAdmin())
            <a href="{{ route('sugestoes.create') }}" class="button button-primary">+ Nova sugestão</a>
            @endunless
        </div>
    </div>

    <div class="panel">
        @if ($sugestoes->isEmpty())
            <div class="empty-state empty-state-large">
                <div class="empty-state-icon">+</div>
                <div>
                    <strong>Nenhuma sugestão ainda</strong>
                    <span>Sugira um domínio que deveria ser bloqueado.</span>
                </div>
            </div>
        @else
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Domínio</th>
                            @if (auth()->user()->isAdmin())
                                <th>Empresa</th>
                                <th>Enviado por</th>
                                <th>Data/hora</th>
                                <th>IP</th>
                            @endif
                            <th>Motivo</th>
                            <th>Status</th>
                            @if (auth()->user()->isAdmin())
                                <th class="table-actions-column"></th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($sugestoes as $sugestao)
                            <tr>
                                <td class="table-mono">{{ $sugestao->dominio }}</td>
                                @if (auth()->user()->isAdmin())
                                    <td>{{ $sugestao->empresa->nome }}</td>
                                    <td>{{ $sugestao->criadoPor->name ?? '-' }}</td>
                                    <td class="table-mono">{{ $sugestao->created_at->format('d/m/Y H:i') }}</td>
                                    <td class="table-mono">{{ $sugestao->ip_address ?? '-' }}</td>
                                @endif
                                <td class="table-secondary-text" style="max-width:260px">{{ $sugestao->motivo ?? '-' }}</td>
                                <td>
                                    @if ($sugestao->status === 'pending')
                                        <span class="status-pill is-inactive">Pendente</span>
                                    @elseif ($sugestao->status === 'approved')
                                        <span class="status-pill is-active">Aprovada — {{ $sugestao->lista->nome ?? '-' }}</span>
                                    @else
                                        <span class="status-pill is-inactive">Rejeitada</span>
                                    @endif
                                </td>
                                @if (auth()->user()->isAdmin())
                                    <td>
                                        @if ($sugestao->status === 'pending')
                                            <div class="table-actions" style="align-items:center">
                                                <form action="{{ route('sugestoes.aprovar', $sugestao) }}" method="POST" style="display:flex;gap:6px;align-items:center">
                                                    @csrf
                                                    <select name="lista_id" class="form-control" style="padding:4px 8px;min-height:auto;font-size:11px" required>
                                                        <option value="">lista...</option>
                                                        @foreach ($listasParaAprovar as $lista)
                                                            <option value="{{ $lista->id }}">{{ $lista->nome }}</option>
                                                        @endforeach
                                                    </select>
                                                    <button type="submit" class="table-action-link">Aprovar</button>
                                                </form>
                                                <form action="{{ route('sugestoes.rejeitar', $sugestao) }}" method="POST">
                                                    @csrf
                                                    <button type="submit" class="table-action-link" style="background:none;border:0">Rejeitar</button>
                                                </form>
                                            </div>
                                        @endif
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{ $sugestoes->links() }}
@endsection
