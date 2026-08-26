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
                @if ($lista->isExterna())
                    <span class="status-pill is-info">fonte externa: {{ $lista->fonte_externa }}</span>
                @endif
                @if ($lista->isAnatel()) <span class="status-pill is-info">Fonte oficial · PDF</span> @endif
                @if ($lista->descricao)
                    &middot; {{ $lista->descricao }}
                @endif
            </p>
        </div>
        <div class="page-actions">
            <a href="{{ route('listas.historico', $lista) }}" class="button button-secondary">Histórico</a>
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

        @if ($lista->isExterna())
        <div class="panel">
            <div class="panel-header"><h2>Sincronização automática</h2></div>
            <span class="status-pill @if($lista->sync_ativo) is-active @else is-warning @endif">
                {{ $lista->sync_ativo ? 'Ativa' : 'Pausada' }}
            </span>
            <dl class="details-list" style="margin-top:14px">
                <div><dt>Última sincronização</dt><dd>{{ optional($lista->last_sync_at)->format('d/m/Y H:i:s') ?? 'ainda não rodou' }}</dd></div>
                <div><dt>Fonte</dt><dd style="word-break:break-all">{{ $lista->fonte_url ?? '—' }}</dd></div>
            </dl>
            <p style="color:var(--text-muted);font-size:11px;margin:10px 0 0">Esta lista é populada automaticamente por um feed externo. Domínios adicionados/removidos manualmente serão sobrescritos na próxima sincronização.</p>
            @if (auth()->user()->isAdmin())
            <div style="display:flex;gap:10px;margin-top:12px;flex-wrap:wrap">
                <form action="{{ route('listas.toggle-sync', $lista) }}" method="POST">
                    @csrf
                    @method('PATCH')
                    <button type="submit" class="button button-secondary">{{ $lista->sync_ativo ? 'Pausar sincronização' : 'Reativar sincronização' }}</button>
                </form>
                <form action="{{ route('listas.sync-now', $lista) }}" method="POST">
                    @csrf
                    <button type="submit" class="button button-primary">Sincronizar agora</button>
                </form>
            </div>
            @endif
        </div>
        @endif

        @if ($lista->isAnatel())
        <div class="panel details-card-wide">
            <div class="panel-header"><h2>ANATEL / PDF</h2><div><a href="{{ route('anatel.exclusions',$lista) }}" class="button button-secondary">Exclusões</a> <a href="{{ route('anatel.history',$lista) }}" class="button button-secondary">Histórico</a></div></div>
            <p>{{ number_format($lista->dominios_ativos_count,0,',','.') }} domínios ativos. A importação é incremental e nunca remove domínios ausentes de um PDF novo.</p>
            @if(auth()->user()->isAdmin()) <a class="button button-primary" href="{{ route('anatel.dashboard') }}">Importar PDFs</a> @endif
        </div>
        @endif

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
                <h2>Domínios ({{ $lista->dominios_count }})</h2>
                @if (auth()->user()->isAdmin())
                <a href="{{ route('listas.dominios.index', $lista) }}" class="button button-primary">{{ $lista->isExterna() ? 'Ver domínios' : 'Gerenciar domínios' }}</a>
                @endif
            </div>
            @if ($lista->dominios_count === 0)
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
                @if ($lista->dominios_count > 10)
                    <p style="color:var(--text-muted);font-size:10px;margin-top:12px">mostrando os primeiros 10 de {{ $lista->dominios_count }} domínios &mdash; use "{{ $lista->isExterna() ? 'Ver domínios' : 'Gerenciar domínios' }}" para ver todos.</p>
                @endif
            @endif
        </div>
    </div>
@endsection
