@extends('layouts.app')
@section('title', 'DNS Recursivo')

@section('content')
@include('dns-recursivo.styles')
<div class="recursive-dns">
    <div class="page-heading">
        <div class="dns-heading">
            <div class="dns-kicker">UNBOUND</div>
            <h1>DNS Recursivo</h1>
            <p>{{ $servers->total() }} resolvedores cadastrados</p>
        </div>
        <a class="button dns-primary" href="{{ route('dns-recursivo.create') }}">Novo servidor</a>
    </div>

    <div class="dns-section dns-scroll">
        <table class="data-table" style="width:100%">
            <thead><tr><th>Servidor</th><th>Acesso SSH</th><th>Cliente RPZ</th><th>Último diagnóstico</th></tr></thead>
            <tbody>
            @forelse ($servers as $server)
                <tr>
                    <td><a href="{{ route('dns-recursivo.edit', $server) }}">{{ $server->name }}</a><br><code>{{ $server->hostname }}</code></td>
                    <td><code>{{ $server->sshAddress() }}</code> · {{ $server->ssh_port }}</td>
                    <td>{{ $server->empresa?->nome ?? '—' }}</td>
                    <td>{{ $server->diagnosed_at?->format('d/m/Y H:i') ?? 'Pendente' }}</td>
                </tr>
            @empty
                <tr><td colspan="4">Cadastre um servidor para iniciar o diagnóstico.</td></tr>
            @endforelse
            </tbody>
        </table>
        {{ $servers->links() }}
    </div>
</div>
@endsection
