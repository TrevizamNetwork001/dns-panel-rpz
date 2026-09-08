@extends('layouts.app')

@section('title', 'Configurações')

@section('content')
    @include('configuracoes.partials.styles')
    <div class="admin-settings">
        <div class="page-heading page-heading-compact">
            <div>
                <h1>Configurações</h1>
                <p>Integrações e ajustes gerais do painel.</p>
            </div>
        </div>

        <nav class="settings-tabs" role="tablist" aria-label="Configurações ADMIN">
            @foreach (['geral' => 'Geral', 'notificacoes' => 'Notificações', 'seguranca' => 'Segurança', 'integracoes' => 'Integrações'] as $tab => $label)
                <button type="button" id="settings-tab-{{ $tab }}" class="settings-tab {{ $tab === 'geral' ? 'is-active' : '' }}" role="tab" aria-selected="{{ $tab === 'geral' ? 'true' : 'false' }}" aria-controls="settings-panel-{{ $tab }}" tabindex="{{ $tab === 'geral' ? '0' : '-1' }}" data-settings-tab="{{ $tab }}">{{ $label }}</button>
            @endforeach
        </nav>

        <div id="settings-panel-geral" role="tabpanel" aria-labelledby="settings-tab-geral" data-settings-panel="geral">
            <section class="settings-section">
                <div class="settings-heading"><h2>Instalação</h2><span class="settings-status is-enabled">Operacional</span></div>
                <dl class="settings-inventory">
                    <div><dt>Nome da aplicação</dt><dd>{{ $geral['nome'] }}</dd></div>
                    <div><dt>Endereço público</dt><dd class="settings-mono"><a class="inline-link" href="{{ $geral['url'] }}" target="_blank" rel="noopener">{{ $geral['url'] }}</a></dd></div>
                    <div><dt>Fuso horário</dt><dd class="settings-mono">{{ $geral['timezone'] }}</dd></div>
                    <div><dt>Ambiente</dt><dd class="settings-mono">{{ $geral['ambiente'] }}</dd></div>
                </dl>
                <p class="settings-note">Esses valores são definidos no <code>.env</code> do servidor para evitar alterações acidentais em produção.</p>
            </section>
            <section class="settings-section">
                <div class="settings-heading"><h2>Administração</h2></div>
                <ul class="settings-links">
                    <li><a href="{{ route('usuarios.index') }}"><strong>Usuários</strong><span>Gerencie contas e vínculos</span></a></li>
                    <li><a href="{{ route('auditoria.index') }}"><strong>Auditoria</strong><span>Consulte eventos e ações</span></a></li>
                    <li><a href="{{ route('seguranca.index') }}"><strong>Segurança</strong><span>Monitore autenticação e SSH</span></a></li>
                </ul>
            </section>
        </div>

        <div id="settings-panel-notificacoes" role="tabpanel" aria-labelledby="settings-tab-notificacoes" data-settings-panel="notificacoes" hidden>
            <section class="settings-section">
                <div class="settings-heading">
                    <div><h2>Telegram</h2><p>Notificações de novos cadastros de empresa</p></div>
                    <span class="settings-status {{ $telegram['ativo'] ? 'is-enabled' : '' }}">{{ $telegram['ativo'] ? 'Ativa' : 'Inativa' }}</span>
                </div>
                <p class="settings-copy">Novos cadastros enviados pelo formulário público (<code>/cadastro</code>) podem gerar uma notificação no grupo configurado. Falhas no Telegram não interrompem o cadastro.</p>
                <p class="settings-note">Inclui empresa, responsável e e-mail.</p>

                <form id="settings-telegram-save" class="settings-form" action="{{ route('configuracoes.telegram.update') }}" method="POST">
                    @csrf
                    @method('PUT')
                    <label class="settings-check">
                        <span><strong>Notificação ativa</strong><small>Notificações de novos cadastros no Telegram</small></span>
                        <input type="checkbox" name="ativo" value="1" @checked($telegram['ativo'])>
                    </label>
                    <div class="field-group">
                        <label for="bot_token">Token do bot</label>
                        <input type="password" class="form-control settings-mono" id="bot_token" name="bot_token" placeholder="{{ $telegram['bot_token'] ? '••••••••••••••••' : 'Cole o token do BotFather aqui' }}" autocomplete="off" aria-describedby="settings-token-help">
                        <small id="settings-token-help" class="settings-note">{{ $telegram['bot_token'] ? 'Já configurado · deixe em branco para manter' : 'Obtenha o token com o BotFather.' }}</small>
                    </div>
                    <div class="settings-fields">
                        <div class="field-group">
                            <label for="chat_id">Chat ID do grupo</label>
                            <input type="text" class="form-control settings-mono" id="chat_id" name="chat_id" value="{{ $telegram['chat_id'] }}" placeholder="-1001234567890">
                        </div>
                        <div class="field-group">
                            <label for="thread_id">ID do tópico (opcional)</label>
                            <input type="text" class="form-control settings-mono" id="thread_id" name="thread_id" value="{{ $telegram['thread_id'] }}" placeholder="Deixe em branco se não usa tópicos">
                        </div>
                    </div>
                </form>
                <div class="settings-actions">
                    <button type="submit" form="settings-telegram-save" class="button button-primary">Salvar alterações</button>
                    <form action="{{ route('configuracoes.telegram.test') }}" method="POST">
                        @csrf
                        <button type="submit" class="button button-secondary">Enviar teste</button>
                    </form>
                </div>
                <details class="settings-help">
                    <summary>Como encontrar Chat ID e tópico</summary>
                    <p>Adicione o bot ao grupo, envie uma mensagem e consulte <code>https://api.telegram.org/bot&lt;TOKEN&gt;/getUpdates</code>. Use <code>chat.id</code> e, em grupos com tópicos, <code>message_thread_id</code>.</p>
                </details>
            </section>
        </div>

        <div id="settings-panel-seguranca" role="tabpanel" aria-labelledby="settings-tab-seguranca" data-settings-panel="seguranca" hidden>
            <section class="settings-section">
                <div class="settings-heading"><h2>Proteções da aplicação</h2><span class="settings-status is-enabled">Ativas</span></div>
                <dl class="settings-inventory">
                    <div><dt>Login</dt><dd>Limite de tentativas e registro de falhas</dd></div>
                    <div><dt>Endpoints RPZ</dt><dd>Limite de 60 requisições por minuto e controle por IP</dd></div>
                    <div><dt>Permissões</dt><dd>Separação entre administradores e empresas clientes</dd></div>
                    <div><dt>Auditoria</dt><dd>Registro de acessos e alterações sensíveis</dd></div>
                </dl>
            </section>
            <section class="settings-section">
                <div class="settings-heading"><h2>Monitoramento</h2></div>
                <p class="settings-copy">Consulte falhas de login, IPs banidos, certificado, disco e disponibilidade do painel.</p>
                <a class="settings-shortcut" href="{{ route('seguranca.index') }}"><span aria-hidden="true">→</span> Abrir Segurança</a>
            </section>
        </div>

        <div id="settings-panel-integracoes" role="tabpanel" aria-labelledby="settings-tab-integracoes" data-settings-panel="integracoes" hidden>
            <dl class="settings-summary">
                <div><dt>Fontes gerenciadas</dt><dd>{{ $integracoes['total'] }}</dd><dd class="settings-summary-note">Externas e ANATEL</dd></div>
                <div><dt>Sincronizações ativas</dt><dd>{{ $integracoes['ativas'] }}</dd><dd class="settings-summary-note">Executadas automaticamente</dd></div>
                <div><dt>Fontes pausadas</dt><dd>{{ $integracoes['pausadas'] }}</dd><dd class="settings-summary-note">Sem atualização automática</dd></div>
            </dl>
            <section class="settings-section">
                <div class="settings-heading"><h2>Listas e feeds</h2></div>
                <dl class="settings-inventory">
                    <div><dt>Última sincronização registrada</dt><dd class="settings-mono">{{ $integracoes['ultima_sync']?->format('d/m/Y H:i') ?? 'ainda não executada' }}</dd></div>
                    <div><dt>Estado</dt><dd>{{ $integracoes['ativas'] }} {{ $integracoes['ativas'] === 1 ? 'sincronização ativa' : 'sincronizações ativas' }} · {{ $integracoes['pausadas'] }} {{ $integracoes['pausadas'] === 1 ? 'pausada' : 'pausadas' }}</dd></div>
                </dl>
                <p class="settings-note">URLs, formatos e pausas são administrados diretamente em cada fonte.</p>
                <a class="settings-shortcut" href="{{ route('listas.index') }}"><span aria-hidden="true">→</span> Gerenciar fontes</a>
            </section>
        </div>
    </div>
    @include('configuracoes.partials.tabs')
@endsection
