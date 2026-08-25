@extends('layouts.app')

@section('title', 'Domínios')

@section('content')
    @php
        $fontesManuais = $fontesDisponiveis->where('origem', '!=', 'externa');
        $queryBase = array_filter([
            'dominio' => $termoOriginal !== '' ? $termoOriginal : null,
            'status' => $statusFiltro,
            'fonte_id' => $fonteId,
        ]);
        $temFiltroAtivo = $termoOriginal !== '' || $statusFiltro !== null || $fonteId !== null;
    @endphp

    <div class="page-heading page-heading-compact">
        <div>
            <h1>Domínios</h1>
            <p>Central de bloqueios RPZ — pesquise, analise e gerencie os domínios distribuídos pelas fontes RPZ.</p>
        </div>
        @if (auth()->user()->isAdmin() && $fontesManuais->isNotEmpty())
            <div class="page-actions">
                <button type="button" class="button button-primary" id="btn-toggle-adicionar-dominio">+ Adicionar domínio</button>
            </div>
        @endif
    </div>

    @if (auth()->user()->isAdmin() && $fontesManuais->isNotEmpty())
        <div class="panel form-panel" id="painel-adicionar-dominio" style="margin-bottom:14px" hidden>
            <div class="panel-header"><h2>Adicionar domínio</h2></div>
            <form action="{{ route('listas.dominios.store', $fontesManuais->first()) }}" method="POST" id="form-adicionar-dominio" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
                @csrf
                <div class="field-group" style="margin:0;flex:1;min-width:220px">
                    <label for="novo-dominio">Domínio</label>
                    <input class="form-control" type="text" id="novo-dominio" name="dominio" placeholder="exemplo.com" required>
                </div>
                <div class="field-group" style="margin:0;min-width:200px">
                    <label for="novo-dominio-fonte">Fonte</label>
                    <select class="form-control" id="novo-dominio-fonte" data-form-adicionar-fonte>
                        @foreach ($fontesManuais as $fonte)
                            <option value="{{ $fonte->id }}" data-action="{{ route('listas.dominios.store', $fonte) }}">{{ $fonte->nome }}</option>
                        @endforeach
                    </select>
                    <p style="color:var(--text-muted);font-size:10px;margin-top:6px">Só fontes manuais aparecem aqui — fontes externas sincronizam sozinhas e não aceitam edição manual.</p>
                </div>
                <button type="submit" class="button button-primary">Adicionar</button>
            </form>
        </div>
    @endif

    <div class="panel" style="margin-bottom:14px">
        <form action="{{ route('dominios.index') }}" method="GET" style="display:flex;gap:10px;flex-wrap:wrap">
            <input type="text" name="dominio" class="form-control" placeholder="Pesquisar domínio, ex.: exemplo.com" value="{{ $termoOriginal }}" style="flex:1;min-width:240px;font-size:14px" autofocus>
            @if ($statusFiltro)
                <input type="hidden" name="status" value="{{ $statusFiltro }}">
            @endif
            @if ($fonteId)
                <input type="hidden" name="fonte_id" value="{{ $fonteId }}">
            @endif
            <button type="submit" class="button button-primary">Buscar</button>
            @if ($termoOriginal !== '')
                <a href="{{ route('dominios.index', array_filter(['status' => $statusFiltro, 'fonte_id' => $fonteId])) }}" class="button button-secondary">Limpar</a>
            @endif
        </form>
    </div>

    @if ($resultadoExato)
        @php
            $unicoMatch = $resultadoExato['matches']->count() === 1 ? $resultadoExato['matches']->first() : null;
        @endphp
        <div class="panel" style="margin-bottom:14px;border-color:rgba(240,106,122,0.35)">
            <div class="panel-header">
                <h2 class="table-mono">{{ $resultadoExato['termo'] }}</h2>
                <span class="status-pill status-pill-normal-case is-inactive">Bloqueado</span>
            </div>
            <dl class="details-list">
                <div>
                    <dt>Fontes</dt>
                    <dd>
                        @foreach ($resultadoExato['fontes'] as $fonte)
                            <a href="{{ route('listas.show', $fonte) }}" class="table-primary-link">{{ $fonte->nome }}</a>@if (! $loop->last), @endif
                        @endforeach
                    </dd>
                </div>
                <div>
                    <dt>Primeira ocorrência</dt>
                    <dd>{{ optional($resultadoExato['primeiraOcorrencia'])->format('d/m/Y') ?? '—' }}</dd>
                </div>
                <div>
                    <dt>Última atualização</dt>
                    <dd>{{ optional($resultadoExato['ultimaAtualizacao'])->format('d/m/Y') ?? '—' }}</dd>
                </div>
                <div>
                    <dt>Estado</dt>
                    <dd><span class="status-pill status-pill-normal-case is-active">Ativo</span></dd>
                </div>
            </dl>
            @if (auth()->user()->isAdmin() && $unicoMatch && ! $unicoMatch['lista']->isExterna())
                <div class="form-actions" style="justify-content:flex-start;border-top:0;padding-top:10px;margin-top:4px">
                    <form action="{{ route('dominios.toggle', $unicoMatch['id']) }}" method="POST">
                        @csrf
                        @method('PATCH')
                        <button type="submit" class="button button-secondary">Desativar</button>
                    </form>
                </div>
            @endif
        </div>
    @elseif ($termo !== null)
        <div class="panel" style="margin-bottom:14px">
            <div class="empty-state"><span class="status-pill is-active" style="margin-right:8px">Livre</span><span>"{{ $termo }}" não está bloqueado em nenhuma fonte visível (sem match exato ou por wildcard).</span></div>
        </div>
    @endif

    <div class="domain-counters">
        <span><strong>{{ number_format($contadores['ativos'], 0, ',', '.') }}</strong> ativos</span>
        <span><strong>{{ number_format($contadores['inativos'], 0, ',', '.') }}</strong> inativos</span>
        <span><strong>{{ number_format($contadores['total'], 0, ',', '.') }}</strong> total</span>
    </div>

    <div class="panel" style="margin-bottom:14px">
        <div style="display:flex;gap:14px;flex-wrap:wrap;align-items:center;justify-content:space-between">
            <div style="display:flex;gap:8px;flex-wrap:wrap">
                <a href="{{ route('dominios.index', array_filter(['dominio' => $termoOriginal ?: null, 'fonte_id' => $fonteId])) }}" class="button {{ $statusFiltro === null ? 'button-primary' : 'button-secondary' }}">Todos</a>
                <a href="{{ route('dominios.index', array_filter(['dominio' => $termoOriginal ?: null, 'fonte_id' => $fonteId, 'status' => 'ativos'])) }}" class="button {{ $statusFiltro === 'ativos' ? 'button-primary' : 'button-secondary' }}">Ativos</a>
                <a href="{{ route('dominios.index', array_filter(['dominio' => $termoOriginal ?: null, 'fonte_id' => $fonteId, 'status' => 'inativos'])) }}" class="button {{ $statusFiltro === 'inativos' ? 'button-primary' : 'button-secondary' }}">Inativos</a>
            </div>
            <form action="{{ route('dominios.index') }}" method="GET" style="display:flex;gap:8px;align-items:center">
                @if ($termoOriginal !== '')
                    <input type="hidden" name="dominio" value="{{ $termoOriginal }}">
                @endif
                @if ($statusFiltro)
                    <input type="hidden" name="status" value="{{ $statusFiltro }}">
                @endif
                <label for="fonte_id" style="font-size:11px;color:var(--text-muted)">Fonte</label>
                <select name="fonte_id" id="fonte_id" class="form-control" style="min-width:190px" onchange="this.form.submit()">
                    <option value="">Todas as fontes</option>
                    @foreach ($fontesDisponiveis as $fonte)
                        <option value="{{ $fonte->id }}" @selected($fonteId === $fonte->id)>{{ $fonte->nome }}</option>
                    @endforeach
                </select>
            </form>
        </div>
    </div>

    <div class="panel">
        @if ($dominios->isEmpty())
            <div class="empty-state empty-state-large">
                <div class="empty-state-icon">◇</div>
                <div>
                    @if ($termoOriginal !== '')
                        <strong>Nenhum registro encontrado para "{{ $termoOriginal }}"</strong>
                        <span>Tente outro trecho do domínio ou limpe a busca.</span>
                    @elseif ($temFiltroAtivo)
                        <strong>Nenhum domínio corresponde aos filtros selecionados</strong>
                        <span>Tente ajustar o status ou a fonte selecionada.</span>
                    @else
                        <strong>Pesquise um domínio ou utilize os filtros para explorar a base RPZ</strong>
                        <span>A listagem completa aparece aqui, paginada.</span>
                    @endif
                </div>
            </div>
        @else
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Domínio</th>
                            <th>Fontes</th>
                            <th>Status</th>
                            <th>Adicionado em</th>
                            <th>Atualizado em</th>
                            <th class="table-actions-column"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($dominios as $linha)
                            @php
                                $fontesLinha = $fontesPorDominio->get($linha->dominio, collect());
                                $nomesFontes = $fontesLinha->pluck('lista.nome')->filter()->unique()->sort()->values();
                                $statusBloqueado = (bool) $linha->algum_ativo;
                                $unicaFonte = $fontesLinha->count() === 1 ? $fontesLinha->first() : null;
                            @endphp
                            <tr>
                                <td class="table-mono">{{ $linha->dominio }}</td>
                                <td>
                                    @if ($nomesFontes->isEmpty())
                                        <span class="table-secondary-text">—</span>
                                    @elseif ($nomesFontes->count() === 1)
                                        {{ $nomesFontes->first() }}
                                    @else
                                        <span title="{{ $nomesFontes->join(', ') }}">{{ $nomesFontes->first() }} +{{ $nomesFontes->count() - 1 }}</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="status-pill status-pill-normal-case {{ $statusBloqueado ? 'is-inactive' : 'is-muted' }}">
                                        {{ $statusBloqueado ? 'Bloqueado' : 'Inativo' }}
                                    </span>
                                </td>
                                <td class="table-mono">{{ $linha->adicionado_em ? \Illuminate\Support\Carbon::parse($linha->adicionado_em)->format('d/m/Y') : '-' }}</td>
                                <td class="table-mono">{{ $linha->atualizado_em ? \Illuminate\Support\Carbon::parse($linha->atualizado_em)->format('d/m/Y') : '-' }}</td>
                                <td>
                                    @if (auth()->user()->isAdmin())
                                        <x-actions-menu label="Ações do domínio {{ $linha->dominio }}">
                                            @if ($unicaFonte)
                                                <a href="{{ route('listas.show', $unicaFonte->lista_id) }}" class="actions-menu-item">Ver fonte</a>
                                                @if ($unicaFonte->lista && ! $unicaFonte->lista->isExterna())
                                                    <form action="{{ route('dominios.toggle', $unicaFonte->id) }}" method="POST">
                                                        @csrf
                                                        @method('PATCH')
                                                        <button type="submit" class="actions-menu-item">{{ $unicaFonte->ativo ? 'Desativar' : 'Ativar' }}</button>
                                                    </form>
                                                    <div class="actions-menu-divider"></div>
                                                    <form action="{{ route('dominios.destroy', $unicaFonte->id) }}" method="POST">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="actions-menu-item actions-menu-item-danger" onclick="return confirm('Remover este domínio?')">Remover</button>
                                                    </form>
                                                @endif
                                            @else
                                                @foreach ($fontesLinha as $f)
                                                    <a href="{{ route('listas.show', $f->lista_id) }}" class="actions-menu-item">{{ $f->lista->nome ?? ('Fonte #' . $f->lista_id) }}</a>
                                                @endforeach
                                            @endif
                                        </x-actions-menu>
                                    @elseif ($unicaFonte)
                                        <a href="{{ route('listas.show', $unicaFonte->lista_id) }}" class="table-action-link">Ver fonte</a>
                                    @else
                                        <span class="table-secondary-text">{{ $fontesLinha->count() }} fontes</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{ $dominios->links() }}

    @if (auth()->user()->isAdmin() && $fontesManuais->isNotEmpty())
        <script>
            (function () {
                var toggleBtn = document.getElementById('btn-toggle-adicionar-dominio');
                var painel = document.getElementById('painel-adicionar-dominio');
                if (toggleBtn && painel) {
                    toggleBtn.addEventListener('click', function () {
                        painel.hidden = !painel.hidden;
                        if (!painel.hidden) {
                            document.getElementById('novo-dominio').focus();
                        }
                    });
                }

                var form = document.getElementById('form-adicionar-dominio');
                var fonteSelect = document.querySelector('[data-form-adicionar-fonte]');
                if (form && fonteSelect) {
                    fonteSelect.addEventListener('change', function () {
                        var selected = fonteSelect.options[fonteSelect.selectedIndex];
                        if (selected && selected.dataset.action) {
                            form.action = selected.dataset.action;
                        }
                    });
                }
            })();
        </script>
    @endif
@endsection
