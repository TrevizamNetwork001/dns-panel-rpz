@extends('layouts.app')

@section('title', 'Configurações')

@section('content')
    <div class="page-heading">
        <div>
            <div class="page-eyebrow">Sistema</div>
            <h1>Configurações</h1>
            <p>Integrações e ajustes gerais do painel.</p>
        </div>
    </div>

    <div class="panel details-card-wide">
        <div class="panel-header">
            <h2>Notificação de cadastro via Telegram</h2>
            <span class="status-pill @if($telegram['ativo']) is-active @else is-inactive @endif">
                {{ $telegram['ativo'] ? 'Ativa' : 'Pausada' }}
            </span>
        </div>
        <p style="color:var(--text-muted);font-size:11px;margin:0 0 14px">
            Quando alguém se cadastra pelo formulário público (<code>/cadastro</code>), o painel manda uma mensagem pra um grupo/tópico do Telegram com nome da empresa, responsável e e-mail — pra você saber na hora sem precisar abrir o painel. Se a mensagem falhar, o cadastro do cliente não é afetado.
        </p>

        <form action="{{ route('configuracoes.telegram.update') }}" method="POST" style="display:flex;flex-direction:column;gap:14px;max-width:480px">
            @csrf
            @method('PUT')

            <label style="display:flex;align-items:center;gap:8px;font-size:13px">
                <input type="checkbox" name="ativo" value="1" @checked($telegram['ativo'])>
                Notificação ativa
            </label>

            <div class="field-group" style="margin:0">
                <label for="bot_token">Token do bot</label>
                <input type="password" class="form-control" id="bot_token" name="bot_token" placeholder="{{ $telegram['bot_token'] ? '•••••••••••• (já configurado — deixe em branco pra manter)' : 'Cole o token do BotFather aqui' }}" autocomplete="off">
            </div>

            <div class="field-group" style="margin:0">
                <label for="chat_id">Chat ID do grupo</label>
                <input type="text" class="form-control" id="chat_id" name="chat_id" value="{{ $telegram['chat_id'] }}" placeholder="-1001234567890">
            </div>

            <div class="field-group" style="margin:0">
                <label for="thread_id">ID do tópico (opcional)</label>
                <input type="text" class="form-control" id="thread_id" name="thread_id" value="{{ $telegram['thread_id'] }}" placeholder="Deixe em branco se o grupo não usa tópicos">
            </div>

            <div style="display:flex;gap:10px">
                <button type="submit" class="button button-primary">Salvar</button>
            </div>
        </form>

        <form action="{{ route('configuracoes.telegram.test') }}" method="POST" style="margin-top:14px">
            @csrf
            <button type="submit" class="button button-secondary">Enviar mensagem de teste</button>
        </form>

        <p style="color:var(--text-muted);font-size:10px;margin-top:12px">
            Não sabe o Chat ID? Adicione o bot ao grupo, mande qualquer mensagem nele e acesse <code>https://api.telegram.org/bot&lt;TOKEN&gt;/getUpdates</code> no navegador — o <code>chat.id</code> aparece na resposta (negativo, pra grupos/supergrupos). Se o grupo usa tópicos (fórum), o <code>message_thread_id</code> da mesma resposta é o ID do tópico.
        </p>
    </div>
@endsection
