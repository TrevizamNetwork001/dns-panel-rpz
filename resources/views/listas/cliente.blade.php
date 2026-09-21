@extends('layouts.app')

@section('title', 'Listas')

@section('content')
    @include('listas.partials.cliente-styles')
    <div class="client-lists">
        <div class="page-heading page-heading-compact">
            <div>
                <h1>Listas</h1>
                <p>{{ $listas->total() }} cadastradas · {{ $listasAtivas }} ativas</p>
            </div>
            <aside class="client-lists-notice">
                <x-ui-icon name="info" :size="24" />
                <span>Estas listas são mantidas e atualizadas continuamente para oferecer mais segurança à sua rede.</span>
            </aside>
        </div>

        <section class="panel client-lists-panel" aria-label="Listas disponíveis">
            <ul class="client-lists-items">
                @forelse ($listas as $lista)
                    <li>
                        <div class="client-list-card">
                            <span class="client-list-icon"><x-list-category-icon :lista="$lista" :size="34" /></span>
                            <span class="client-list-copy">
                                <strong>{{ $lista->nome }}</strong>
                                @if($lista->descricao)<span>{{ $lista->descricao }}</span>@endif
                            </span>
                            <span class="client-list-type"><span class="status-pill {{ $lista->empresa_id ? 'is-active' : 'is-muted' }}">{{ $lista->empresa_id ? 'Própria' : 'Catálogo' }}</span></span>
                            <span class="client-list-count"><strong>{{ number_format($lista->dominios_ativos_count, 0, ',', '.') }}</strong><span>{{ $lista->dominios_ativos_count == 1 ? 'domínio ativo' : 'domínios ativos' }}</span></span>
                        </div>
                    </li>
                @empty
                    <li class="empty-state empty-state-large">
                        <x-ui-icon name="list" :size="32" />
                        <div>
                            <strong>Nenhuma lista disponível</strong>
                            <span>As listas liberadas para sua empresa aparecerão aqui.</span>
                        </div>
                    </li>
                @endforelse
            </ul>
        </section>
        {{ $listas->links('pagination.compact') }}
    </div>
@endsection
