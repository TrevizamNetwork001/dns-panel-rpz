@extends('layouts.app')
@section('title', 'Novo alvo — RBL Checker')
@section('content')
<div class="page-heading"><h1>Novo alvo RBL</h1><a href="{{ route('rbl.index') }}">Voltar</a></div>
<div class="panel form-panel">
<p>Somente IPv4 individual é consultado nesta fase. CIDR, IPv6, domínio e hostname ficam registrados com consultas skipped.</p>
<form method="POST" action="{{ route('rbl.targets.store') }}">
@csrf
<div class="form-grid">
@foreach (['name' => 'Nome', 'value' => 'Valor'] as $field => $label)
<div class="field-group"><label for="{{ $field }}">{{ $label }}</label><input class="form-control" id="{{ $field }}" name="{{ $field }}" value="{{ old($field) }}" maxlength="253" required></div>
@endforeach
<div class="field-group"><label for="type">Tipo</label><select class="form-control" id="type" name="type">@foreach(['ip', 'cidr', 'domain', 'hostname'] as $type)<option @selected(old('type') === $type)>{{ $type }}</option>@endforeach</select></div>
<div class="field-group"><label for="category">Categoria</label><select class="form-control" id="category" name="category"><option value="">Sem categoria</option>@foreach(['cgnat', 'mail', 'infra', 'dedicated', 'other'] as $category)<option @selected(old('category') === $category)>{{ $category }}</option>@endforeach</select></div>
<div class="field-group"><label for="description">Descrição</label><textarea class="form-control" id="description" name="description" maxlength="2000">{{ old('description') }}</textarea></div>
<div class="field-group"><label for="enabled">Monitoramento</label><select class="form-control" id="enabled" name="enabled"><option value="1" @selected(old('enabled', '1') === '1')>Ativo</option><option value="0" @selected(old('enabled') === '0')>Desativado</option></select></div>
</div><button class="button button-primary" type="submit">Cadastrar alvo</button>
</form></div>
@endsection
