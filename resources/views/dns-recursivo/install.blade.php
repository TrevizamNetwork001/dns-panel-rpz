@extends('layouts.app')
@section('title', 'Instalar Unbound')

@section('content')
@include('dns-recursivo.styles')
<div class="recursive-dns">
    <div class="page-heading">
        <div class="dns-heading">
            <div class="dns-kicker">DNS RECURSIVO</div>
            <h1>Revisar instalação do Unbound</h1>
            <p>{{ $server->name }} · {{ $server->sshAddress() }}</p>
        </div>
        <a class="button" href="{{ route('dns-recursivo.edit', $server) }}">Voltar</a>
    </div>
    <section class="dns-section">
        <h2>Configuração proposta</h2>
        <p>O painel instala o pacote Unbound e testa a resolução em 127.0.0.1. Os endereços de escuta desta instalação são locais ao servidor.</p>
        <pre class="dns-scroll">{{ $configuration }}</pre>
        <p>A conta SSH precisa ser root ou ter sudo sem senha para a instalação.</p>
        <form method="post" action="{{ route('dns-recursivo.install.apply', $server) }}">
            @csrf
            <input type="hidden" name="plan" value="{{ $token }}">
            <button type="submit" class="button dns-primary">Confirmar instalação</button>
        </form>
    </section>
</div>
@endsection
