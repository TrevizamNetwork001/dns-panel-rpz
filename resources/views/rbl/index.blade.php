@extends('layouts.app')
@section('title', 'RBL Checker')
@section('content')
<div class="rbl-dashboard">
<div class="page-heading"><div><div class="page-eyebrow">Monitoramento</div><h1>RBL Checker</h1><p>Reputação DNSBL com histórico. Verificações manuais e monitoramento agendado a cada 6 horas.</p></div><a class="button button-primary" href="{{ route('rbl.targets.create') }}">Novo alvo</a></div>
@include('rbl.navigation')
<div class="metrics-grid">
@foreach(['Alvos monitorados' => $total, 'Listados agora' => $listed, 'Eventos abertos' => $open, 'Eventos resolvidos nas últimas 24h' => $resolved24h, 'Última execução' => null, 'Checks com erro nas últimas 24h' => $errors24h] as $label => $value)
<div class="metric-card">
    <div class="metric-label">{{ $label }}</div>
    @if($label === 'Última execução')
        <div class="rbl-last-run">
            <span class="status-pill {{ match($lastRun?->status) { 'completed' => 'is-active', 'failed' => 'is-inactive', 'running' => 'is-info', default => 'is-muted' } }}">{{ $lastRun?->status ?? 'Nunca' }}</span>
            @if($lastRun)
                <time datetime="{{ $lastRun->started_at->toIso8601String() }}">{{ $lastRun->started_at->format('d/m/Y H:i:s') }}</time>
            @else
                <span>Nenhuma execução registrada</span>
            @endif
        </div>
    @else
        <div class="metric-value">{{ $value }}</div>
    @endif
</div>
@endforeach
</div>
@if($lastRun)<div class="panel"><h2>Última execução agendada / Artisan</h2><p>{{ $lastRun->status }} · {{ $lastRun->targets_checked }} alvos · {{ $lastRun->checks_created }} checks · {{ $lastRun->duration_ms ?? '—' }} ms · Fim: {{ $lastRun->finished_at?->format('d/m/Y H:i:s') ?? 'Em andamento' }}</p>@if($lastRun->error_message)<p>{{ $lastRun->error_message }}</p>@endif</div>@endif
<div class="panel"><h2>Últimos eventos abertos</h2>@include('rbl.event-table', ['events' => $openEvents])</div>
<div class="panel"><h2>Últimos eventos resolvidos</h2>@include('rbl.event-table', ['events' => $resolvedEvents])</div>
<div class="panel"><h2>Últimos checks com erro ou timeout</h2><div class="table-responsive"><table class="data-table"><thead><tr><th>Alvo</th><th>Valor</th><th>RBL</th><th>Status</th><th>Data</th><th>Erro</th></tr></thead><tbody>@forelse($errorChecks as $check)<tr><td>{{ $check->target?->name }}</td><td>{{ $check->checked_value }}</td><td>{{ $check->list?->name }}</td><td>{{ $check->status }}</td><td>{{ $check->checked_at?->format('d/m/Y H:i:s') }}</td><td>{{ $check->error_message ?? '—' }}</td></tr>@empty<tr><td colspan="6">Nenhum erro encontrado.</td></tr>@endforelse</tbody></table></div></div>
<div class="panel"><h2>Alvos nunca verificados</h2><p>Até 10 alvos ativos.</p>@forelse($uncheckedTargets as $target)<p><a href="{{ route('rbl.targets.show', $target) }}">{{ $target->name }} — {{ $target->value }}</a></p>@empty<p>Todos os alvos ativos já foram verificados.</p>@endforelse</div>
<div class="panel"><h2>Alvos</h2><p>O status reflete a última execução. Eventos permanecem abertos quando uma consulta falha ou é ignorada.</p><div class="table-responsive"><table class="data-table"><thead><tr><th>Nome</th><th>Tipo</th><th>Valor</th><th>Categoria</th><th>Status atual</th><th>Última verificação</th><th>Ações</th></tr></thead><tbody>
@forelse($targets as $target)
<tr><td>{{ $target->name }}</td><td>{{ $target->type }}</td><td>{{ $target->value }}</td><td>{{ $target->category ?? '—' }}</td><td>{{ $target->last_status ?? 'unchecked' }}{{ $target->enabled ? '' : ' (desativado)' }}</td><td>{{ $target->last_checked_at?->format('d/m/Y H:i:s') ?? 'Nunca' }}</td><td>
@if($target->enabled)<form method="POST" action="{{ route('rbl.targets.check', $target) }}">@csrf<button class="button button-primary" type="submit">Verificar agora</button></form>@endif
<a class="rbl-history-link" href="{{ route('rbl.targets.show', $target) }}">Ver histórico</a></td></tr>
@empty<tr><td colspan="7">Nenhum alvo cadastrado.</td></tr>@endforelse
</tbody></table></div>{{ $targets->links() }}</div>
<div class="panel"><h2>Listas RBL</h2><p>Ative somente listas cujo uso foi autorizado pelo provedor. Limites: 1 verificação por alvo/minuto, 10 consultas e 20 segundos de DNS por alvo.</p><div class="table-responsive"><table class="data-table"><thead><tr><th>Nome / descrição</th><th>Zona DNS</th><th>Tipo</th><th>Timeout</th><th>Estado</th><th>Ação</th></tr></thead><tbody>
@forelse($lists->sortByDesc('enabled') as $list)<tr><td><strong>{{ $list->name }}</strong><p class="rbl-list-description">{{ $list->description }}</p></td><td>{{ $list->dns_zone }}</td><td>{{ $list->type }}</td><td>{{ $list->timeout_seconds }}s</td><td><span class="status-pill {{ $list->enabled ? 'is-active' : 'is-muted' }}">{{ $list->enabled ? 'Ativa' : 'Desativada' }}</span></td><td><form method="POST" action="{{ route('rbl.lists.toggle', $list) }}">@csrf @method('PATCH')<button class="button {{ $list->enabled ? 'button-secondary' : 'rbl-button-positive' }}" type="submit">{{ $list->enabled ? 'Desativar' : 'Ativar' }}</button></form></td></tr>
@empty<tr><td colspan="6">Nenhuma lista cadastrada. Execute o RblListSeeder ou cadastre abaixo.</td></tr>@endforelse
</tbody></table></div></div>
<details class="panel form-panel rbl-list-registration" @if($errors->hasAny(['name', 'dns_zone', 'type', 'timeout_seconds', 'description'])) open @endif>
<summary>Cadastrar nova lista</summary>
<h2>Cadastrar lista RBL</h2><form method="POST" action="{{ route('rbl.lists.store') }}">@csrf<div class="form-grid">
@foreach(['name' => 'Nome', 'dns_zone' => 'Zona DNS'] as $field => $label)<div class="field-group"><label for="list_{{ $field }}">{{ $label }}</label><input class="form-control" id="list_{{ $field }}" name="{{ $field }}" value="{{ old($field) }}" required></div>@endforeach
<div class="field-group"><label for="list_type">Tipo</label><select class="form-control" id="list_type" name="type"><option value="ip" @selected(old('type') === 'ip')>IP</option><option value="domain" @selected(old('type') === 'domain')>Domínio (fase futura)</option></select></div>
<div class="field-group"><label for="timeout_seconds">Timeout (segundos)</label><input class="form-control" id="timeout_seconds" name="timeout_seconds" type="number" min="1" max="5" value="{{ old('timeout_seconds', 5) }}" required></div>
<div class="field-group"><label for="list_description">Descrição</label><textarea class="form-control" id="list_description" name="description" maxlength="2000">{{ old('description') }}</textarea></div>
</div><button class="button button-primary" type="submit">Cadastrar lista</button></form></details>
</div>
@endsection
