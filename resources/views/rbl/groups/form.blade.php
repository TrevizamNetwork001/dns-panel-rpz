@extends('layouts.app')
@section('title', 'Grupo RBL')
@section('content')
<div class="page-heading"><h1>{{ $group->exists ? 'Editar grupo' : 'Novo grupo' }}</h1></div>
@include('rbl.navigation')
<div class="panel form-panel"><form method="POST" action="{{ $group->exists ? route('rbl.groups.update',$group) : route('rbl.groups.store') }}">@csrf @if($group->exists) @method('PATCH') @endif
<div class="form-grid">
@foreach(['name'=>'Nome','slug'=>'Slug (opcional)','description'=>'Descrição'] as $field=>$label)<div class="field-group"><label for="{{ $field }}">{{ $label }}</label><input class="form-control" id="{{ $field }}" name="{{ $field }}" value="{{ old($field,$group->$field) }}" maxlength="{{ $field === 'description' ? 2000 : 255 }}" @required($field==='name')></div>@endforeach
<div class="field-group"><label for="category">Categoria</label><select class="form-control" id="category" name="category"><option value="">Sem categoria</option>@foreach(['cgnat','mail','infra','dedicated','other'] as $category)<option @selected(old('category',$group->category)===$category)>{{ $category }}</option>@endforeach</select></div>
<div class="field-group"><label for="enabled">Status</label><select class="form-control" id="enabled" name="enabled"><option value="1" @selected(old('enabled',$group->enabled))>Ativo</option><option value="0" @selected(!old('enabled',$group->enabled))>Desativado</option></select></div>
</div><p>Desativar o grupo suspende as verificações de seus alvos.</p><button class="button button-primary">Salvar grupo</button></form></div>
@endsection
