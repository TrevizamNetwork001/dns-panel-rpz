@extends('layouts.app')

@section('title', 'Licença #' . $licenca->id)

@section('content')
    <div class="page-heading">
        <div>
            <div class="page-eyebrow">Licença</div>
            <h1>Licença #{{ $licenca->id }}</h1>
            <p><a href="{{ route('empresas.show', $licenca->empresa) }}" class="inline-link">{{ $licenca->empresa->nome }}</a></p>
        </div>
        <div class="page-actions">
            <a href="{{ route('licencas.edit', $licenca) }}" class="button button-secondary">Editar</a>
        </div>
    </div>

    <div class="panel">
        <div class="panel-header"><h2>Status</h2></div>
        <span class="status-pill @if($licenca->status === 'active') is-active @else is-inactive @endif">
            {{ $licenca->status }}
        </span>
        <dl class="details-list" style="margin-top:14px">
            <div><dt>Início</dt><dd>{{ $licenca->starts_at->format('d/m/Y') }}</dd></div>
            <div><dt>Expiração</dt><dd>{{ optional($licenca->expires_at)->format('d/m/Y') ?? 'sem expiração' }}</dd></div>
            <div><dt>Máx. servidores</dt><dd>{{ $licenca->max_servidores }}</dd></div>
        </dl>
    </div>
@endsection
