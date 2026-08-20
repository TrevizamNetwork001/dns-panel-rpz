@extends('layouts.app')

@section('title', 'Domínios — ' . $lista->nome)

@section('content')
    <div class="page-heading">
        <div>
            <div class="page-eyebrow">Lista</div>
            <h1>Domínios de {{ $lista->nome }}</h1>
            <p><a href="{{ route('listas.show', $lista) }}" class="inline-link">&larr; voltar para a lista</a></p>
        </div>
    </div>

    @if ($lista->isExterna())
        <div class="panel" style="margin-bottom:14px">
            <div class="alert-error" style="margin:0;background:rgba(33,199,232,0.08);border-color:var(--cyan, #21c7e8);color:var(--text)">
                Esta lista é sincronizada automaticamente do feed <strong>{{ $lista->fonte_externa }}</strong>. Edição manual está desabilitada — qualquer alteração seria sobrescrita na próxima sincronização.
            </div>
        </div>
    @else
    <div class="details-grid">
        <div class="panel">
            <div class="panel-header"><h2>Adicionar domínio</h2></div>
            <form action="{{ route('listas.dominios.store', $lista) }}" method="POST">
                @csrf
                <div class="field-group">
                    <label for="dominio">Domínio</label>
                    <input class="form-control" type="text" id="dominio" name="dominio" placeholder="exemplo.com" required>
                </div>
                <div class="form-actions" style="justify-content:flex-start;border-top:0;padding-top:0;margin-top:10px">
                    <button type="submit" class="button button-primary">Adicionar</button>
                </div>
            </form>
        </div>

        <div class="panel">
            <div class="panel-header"><h2>Importar em lote</h2></div>
            <form action="{{ route('listas.dominios.bulk', $lista) }}" method="POST">
                @csrf
                <div class="field-group">
                    <label for="dominios">Um domínio por linha (ou separado por vírgula/espaço)</label>
                    <textarea class="form-control" id="dominios" name="dominios" rows="4" placeholder="malicioso1.example&#10;malicioso2.example" required></textarea>
                </div>
                <div class="form-actions" style="justify-content:flex-start;border-top:0;padding-top:0;margin-top:10px">
                    <button type="submit" class="button button-primary">Importar</button>
                </div>
            </form>
        </div>
    </div>
    @endif

    <div class="panel" style="margin-top:14px">
        <div class="panel-header"><h2>Domínios cadastrados ({{ $dominios->total() }})</h2></div>

        @if ($dominios->isEmpty())
            <div class="empty-state"><span>Nenhum domínio cadastrado ainda.</span></div>
        @else
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Domínio</th>
                            <th>Status</th>
                            @unless ($lista->isExterna())
                            <th class="table-actions-column"></th>
                            @endunless
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($dominios as $dominio)
                            <tr>
                                <td class="table-mono">{{ $dominio->dominio }}</td>
                                <td>
                                    <span class="status-pill @if($dominio->ativo) is-active @else is-inactive @endif">
                                        {{ $dominio->ativo ? 'Ativo' : 'Inativo' }}
                                    </span>
                                </td>
                                @unless ($lista->isExterna())
                                <td>
                                    <div class="table-actions">
                                        <form action="{{ route('dominios.toggle', $dominio) }}" method="POST">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="table-action-link" style="background:none;border:0">
                                                {{ $dominio->ativo ? 'Desativar' : 'Ativar' }}
                                            </button>
                                        </form>
                                        <form action="{{ route('dominios.destroy', $dominio) }}" method="POST">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="table-action-link" style="background:none;border:0" onclick="return confirm('Remover domínio?')">Remover</button>
                                        </form>
                                    </div>
                                </td>
                                @endunless
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="pagination-simple">
                {{ $dominios->links() }}
            </div>
        @endif
    </div>
@endsection
