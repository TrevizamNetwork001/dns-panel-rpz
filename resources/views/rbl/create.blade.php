@extends('layouts.app')
@section('title', 'Novo alvo — RBL Checker')
@section('content')
<div class="page-heading"><h1>{{ $target->exists ? 'Editar alvo RBL' : 'Novo alvo RBL' }}</h1><a href="{{ route('rbl.index') }}">Voltar</a></div>
<div class="panel form-panel">
<p>IPv4 individual e CIDR IPv4 de até 8 endereços são suportados. IPv6, domínio e hostname continuam skipped. Ao editar, tipo e valor permanecem fixos para preservar o histórico.</p>
<form method="POST" action="{{ $target->exists ? route('rbl.targets.update', $target) : route('rbl.targets.store') }}">
@csrf
@if($target->exists) @method('PATCH') @endif
<div class="form-grid">
@include('rbl.target-group-select')
@foreach (['name' => 'Nome', 'value' => 'Valor'] as $field => $label)
<div class="field-group"><label for="{{ $field }}">{{ $label }}</label><input class="form-control" id="{{ $field }}" name="{{ $field }}" value="{{ old($field, $target->$field) }}" maxlength="253" @readonly($target->exists && $field === 'value') required></div>
@endforeach
<div class="field-group"><label for="type">Tipo</label><select class="form-control" id="type" name="type" @disabled($target->exists)>@foreach(['ip', 'cidr', 'domain', 'hostname'] as $type)<option @selected(old('type', $target->type) === $type)>{{ $type }}</option>@endforeach</select></div>
<div class="field-group"><label for="category">Categoria</label><select class="form-control" id="category" name="category"><option value="">Sem categoria</option>@foreach(['cgnat', 'mail', 'infra', 'dedicated', 'other'] as $category)<option @selected(old('category', $target->category) === $category)>{{ $category }}</option>@endforeach</select></div>
<div class="field-group"><label for="description">Descrição</label><textarea class="form-control" id="description" name="description" maxlength="2000">{{ old('description', $target->description) }}</textarea></div>
<div class="field-group"><label for="enabled">Monitoramento</label><select class="form-control" id="enabled" name="enabled"><option value="1" @selected((string) old('enabled', (int) $target->enabled) === '1')>Ativo</option><option value="0" @selected((string) old('enabled', (int) $target->enabled) === '0')>Desativado</option></select></div>
</div><button class="button button-primary" type="submit">Salvar alvo</button>
</form></div>
@endsection
