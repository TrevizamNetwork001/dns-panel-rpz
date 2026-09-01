@extends('layouts.app')

@section('title', 'Configurações')

@section('content')
    <div class="page-heading page-heading-compact">
        <div>
            <h1>Configurações</h1>
            <p>Integrações e ajustes gerais do painel.</p>
        </div>
    </div>

    <div class="panel" style="margin-bottom:14px">
        <div style="display:flex;gap:8px;flex-wrap:wrap" role="tablist">
            <button type="button" class="button button-secondary is-settings-tab is-active" data-settings-tab="geral">Geral</button>
            <button type="button" class="button button-secondary is-settings-tab" data-settings-tab="notificacoes">Notificações</button>
            <button type="button" class="button button-secondary is-settings-tab" data-settings-tab="seguranca">Segurança</button>
            <button type="button" class="button button-secondary is-settings-tab" data-settings-tab="integracoes">Integrações</button>
        </div>
    </div>

    <div data-settings-panel="geral">
        <div class="details-grid">
            <div class="panel details-card-wide">
                <div class="panel-header"><h2>Instalação</h2><span class="status-pill is-active">Operacional</span></div>
                <dl class="details-list">
                    <div><dt>Nome da aplicação</dt><dd>{{ $geral['nome'] }}</dd></div>
                    <div><dt>Endereço público</dt><dd><a class="inline-link" href="{{ $geral['url'] }}" target="_blank" rel="noopener">{{ $geral['url'] }}</a></dd></div>
                    <div><dt>Fuso horário</dt><dd>{{ $geral['timezone'] }}</dd></div>
                    <div><dt>Ambiente</dt><dd>{{ $geral['ambiente'] }}</dd></div>
                </dl>
                <p style="color:var(--text-muted);font-size:11px;margin:14px 0 0">Esses dados estruturais são definidos no arquivo <code>.env</code> do servidor para evitar alterações acidentais em produção.</p>
            </div>
            <div class="panel">
                <div class="panel-header"><h2>Administração</h2></div>
                <div style="display:flex;gap:8px;flex-wrap:wrap">
                    <a class="button button-secondary" href="{{ route('usuarios.index') }}">Usuários</a>
                    <a class="button button-secondary" href="{{ route('auditoria.index') }}">Auditoria</a>
                    <a class="button button-secondary" href="{{ route('seguranca.index') }}">Saúde e segurança</a>
                </div>
            </div>
        </div>
    </div>

    <div data-settings-panel="notificacoes" hidden>
        <div class="panel details-card-wide">
            <div class="panel-header">
                <h2>Telegram — cadastro de empresa</h2>
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

            <details style="margin-top:12px;color:var(--text-muted);font-size:10px">
                <summary class="inline-link" style="cursor:pointer">Como encontrar Chat ID e tópico</summary>
                <p>Adicione o bot ao grupo, envie uma mensagem e consulte <code>https://api.telegram.org/bot&lt;TOKEN&gt;/getUpdates</code>. Use <code>chat.id</code> e, em grupos com tópicos, <code>message_thread_id</code>.</p>
            </details>
        </div>
    </div>

    <div data-settings-panel="seguranca" hidden>
        <div class="details-grid">
            <div class="panel details-card-wide">
                <div class="panel-header"><h2>Proteções da aplicação</h2><span class="status-pill is-active">Ativas</span></div>
                <dl class="details-list">
                    <div><dt>Login</dt><dd>Limite de tentativas e registro de falhas</dd></div>
                    <div><dt>Endpoints RPZ</dt><dd>Limite de 60 requisições por minuto e controle por IP</dd></div>
                    <div><dt>Permissões</dt><dd>Separação entre administradores e empresas clientes</dd></div>
                    <div><dt>Auditoria</dt><dd>Registro de acessos e alterações sensíveis</dd></div>
                </dl>
            </div>
            <div class="panel">
                <div class="panel-header"><h2>Monitoramento</h2></div>
                <p style="color:var(--text-muted);font-size:11px;margin:0 0 14px">Consulte falhas de login, IPs banidos, certificado, disco e disponibilidade do painel.</p>
                <a class="button button-primary" href="{{ route('seguranca.index') }}">Abrir saúde e segurança</a>
            </div>
        </div>
    </div>

    <div data-settings-panel="integracoes" hidden>
        <div class="metrics-grid">
            <div class="metric-card"><div class="metric-value">{{ $integracoes['total'] }}</div><div class="metric-label">Fontes gerenciadas</div><div class="metric-footer"><span>Externas e ANATEL</span></div></div>
            <div class="metric-card"><div class="metric-value">{{ $integracoes['ativas'] }}</div><div class="metric-label">Sincronizações ativas</div><div class="metric-footer"><span>Executadas automaticamente</span></div></div>
            <div class="metric-card"><div class="metric-value">{{ $integracoes['pausadas'] }}</div><div class="metric-label">Fontes pausadas</div><div class="metric-footer"><span>Sem atualização automática</span></div></div>
        </div>
        <div class="panel" style="margin-top:14px">
            <div class="panel-header"><h2>Listas e feeds</h2></div>
            <p style="color:var(--text-muted);font-size:11px;margin:0 0 14px">Última sincronização registrada: <strong>{{ $integracoes['ultima_sync']?->format('d/m/Y H:i') ?? 'ainda não executada' }}</strong>. URLs, formatos e pausas são administrados diretamente em cada lista.</p>
            <a class="button button-primary" href="{{ route('listas.index') }}">Gerenciar fontes</a>
        </div>
    </div>

    <script>
        (function () {
            var tabs = document.querySelectorAll('[data-settings-tab]');
            var panels = document.querySelectorAll('[data-settings-panel]');

            function activate(name) {
                tabs.forEach(function (tab) {
                    tab.classList.toggle('is-active', tab.dataset.settingsTab === name);
                });
                panels.forEach(function (panel) {
                    panel.hidden = panel.dataset.settingsPanel !== name;
                });
            }

            tabs.forEach(function (tab) {
                tab.addEventListener('click', function () {
                    activate(tab.dataset.settingsTab);
                });
            });

            var requestedTab = new URLSearchParams(window.location.search).get('aba');
            var initialTab = Array.from(tabs).some(function (tab) {
                return tab.dataset.settingsTab === requestedTab;
            }) ? requestedTab : 'geral';

            activate(initialTab);

            tabs.forEach(function (tab) {
                tab.addEventListener('click', function () {
                    var url = new URL(window.location.href);
                    url.searchParams.set('aba', tab.dataset.settingsTab);
                    window.history.replaceState({}, '', url);
                });
            });
        })();
    </script>
@endsection
