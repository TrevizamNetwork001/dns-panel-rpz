@extends('layouts.app')
@section('title', 'Exceções · '.$empresa->nome)
@section('content')
<div class="page-heading"><div><h1>Exceções por empresa</h1><p>{{ $empresa->nome }}</p></div><a href="{{ route('empresas.show', $empresa) }}" class="button button-secondary">Voltar à empresa</a></div>
<div class="panel form-panel">
    <div class="panel-header"><div><h2>Liberar domínio</h2><p>A liberação inclui o domínio informado e seus subdomínios, somente nesta empresa. As fontes compartilhadas e as outras empresas continuam com seus bloqueios.</p></div></div>
    <form method="POST" action="{{ route('empresas.exceptions.store', $empresa) }}">@csrf
        <div class="form-grid"><div class="field-group"><label for="dominio">Domínio</label><input id="dominio" name="dominio" class="form-control" value="{{ old('dominio') }}" placeholder="servico.exemplo.com.br" maxlength="253" required><small>Informar www.exemplo.com.br libera somente esse ramo, preservando o bloqueio de exemplo.com.br.</small></div>
        <div class="field-group"><label for="motivo">Motivo (opcional)</label><input id="motivo" name="motivo" class="form-control" value="{{ old('motivo') }}" maxlength="255"></div></div>
        <button class="button button-primary" type="submit">Adicionar exceção</button>
    </form>
</div>
<div class="panel"><div class="panel-header"><h2>Exceções cadastradas</h2></div><div class="table-responsive"><table class="data-table">
    <thead><tr><th>Domínio e subdomínios</th><th>Motivo</th><th></th></tr></thead><tbody>
    @forelse($exceptions as $exception)<tr><td>{{ $exception->dominio }}</td><td>{{ $exception->motivo }}</td><td><form method="POST" action="{{ route('empresas.exceptions.destroy', [$empresa, $exception]) }}">@csrf @method('DELETE')<button type="submit" class="button button-secondary" onclick="return confirm('Remover esta exceção e voltar a aplicar os bloqueios das fontes?')">Remover</button></form></td></tr>
    @empty<tr><td colspan="3">Nenhuma exceção cadastrada.</td></tr>@endforelse
    </tbody></table></div>{{ $exceptions->links() }}</div>
@endsection
