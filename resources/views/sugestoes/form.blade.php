@extends('layouts.app')

@section('title', 'Sugerir domínio')

@section('content')
    <div class="page-heading">
        <div>
            <div class="page-eyebrow">Sugestões</div>
            <h1>Sugerir domínio para bloqueio</h1>
            <p>Sua sugestão fica pendente até o administrador revisar.</p>
        </div>
    </div>

    <div class="panel form-panel">
        <form action="{{ route('sugestoes.store') }}" method="POST">
            @csrf

            <div class="field-group">
                <label for="dominio">Domínio</label>
                <input class="form-control" type="text" id="dominio" name="dominio" placeholder="exemplo-malicioso.com" required>
            </div>

            <div class="field-group">
                <label for="motivo">Motivo (opcional)</label>
                <textarea class="form-control" id="motivo" name="motivo" rows="3" placeholder="Por que esse domínio deveria ser bloqueado?"></textarea>
            </div>

            <div class="form-actions">
                <a href="{{ route('sugestoes.index') }}" class="button button-secondary">Cancelar</a>
                <button type="submit" class="button button-primary">Enviar sugestão</button>
            </div>
        </form>
    </div>
@endsection
