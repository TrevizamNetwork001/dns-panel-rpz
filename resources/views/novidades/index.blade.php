@extends('layouts.app')

@section('title', 'Novidades da versão')

@section('content')
    <div class="page-heading page-heading-compact">
        <div>
            <h1>Novidades da versão</h1>
            <p>Atualizações publicadas no Trevizam DNS.</p>
        </div>
    </div>

    <section class="panel" aria-labelledby="novidades-novembro">
        <div class="panel-header">
            <h2 id="novidades-novembro">Novembro de 2026</h2>
        </div>
        <ul style="padding-inline-start: 1.25rem; line-height: 1.7">
            <li><strong>Listas vinculadas.</strong> O painel <em>Ver listas</em> distingue os tipos de fonte por ícone, cor e rótulo.</li>
            <li><strong>Acesso ao feed.</strong> A tela de acesso restrito mostra o status da solicitação e orienta como pedir a liberação do IP de origem.</li>
            @if (auth()->user()->isAdmin())
                <li><strong>Central Anatel.</strong> A caixa de e-mail é consultada diariamente. Os anexos novos geram prévias que aguardam aprovação.</li>
                <li><strong>Contatos da empresa.</strong> A confirmação de aprovação pode ser enviada ao contato principal e aos adicionais.</li>
                <li><strong>Auditoria.</strong> Eventos de geração do RPZ são descritos em português, e verificações de rotina ficam disponíveis pelo filtro.</li>
            @endif
        </ul>
    </section>
@endsection
