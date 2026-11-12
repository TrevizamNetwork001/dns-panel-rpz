@extends('layouts.app')

@section('title', 'Licença #' . $licenca->id)

@section('content')
@include('partials.operation-styles')
<div class="operation-page operation-license">
<nav class="operation-breadcrumb" aria-label="Navegação de licenças"><a href="{{ route('licencas.index') }}">Licenças</a><span aria-hidden="true">/</span><span>#{{ $licenca->id }}</span></nav>
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

    @php
        $statusLabel = match ($licenca->status) {
            'active' => 'Ativa',
            'inactive' => 'Inativa',
            'expired' => 'Expirada',
            default => ucfirst($licenca->status),
        };
    @endphp

    <div class="panel">
        <div class="panel-header"><h2>Vigência e capacidade</h2>
        <span class="status-pill @if($licenca->status === 'active') is-active @else is-inactive @endif">
            {{ $statusLabel }}
        </span></div>
        <dl class="details-list" style="margin-top:14px">
            <div><dt>Início</dt><dd>{{ $licenca->starts_at->format('d/m/Y') }}</dd></div>
            <div><dt>Validade</dt><dd>{{ optional($licenca->expires_at)->format('d/m/Y') ?? 'Sem vencimento' }}</dd></div>
            <div><dt>Máximo de Endpoints RPZ</dt><dd>{{ $licenca->max_servidores }}</dd></div>
        </dl>
    </div>
</div>
@endsection
