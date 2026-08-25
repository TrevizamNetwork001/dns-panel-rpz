<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'DNS Panel RPZ')</title>
    <script>
        (function () {
            var stored = localStorage.getItem('dns-panel-theme');
            if (stored === 'light') {
                document.documentElement.setAttribute('data-theme', 'light');
            } else {
                document.documentElement.setAttribute('data-theme', 'dark');
            }
        })();
    </script>
    <link rel="stylesheet" href="{{ asset('assets/app.css') }}?v={{ filemtime(public_path('assets/app.css')) }}">
</head>
<body>
    <div class="app-shell">
        <aside class="sidebar">
            <div class="sidebar-brand">
                <span class="brand-mark">RPZ</span>
                <div>
                    <div class="brand-name">DNS PANEL</div>
                    <div class="brand-description">RPZ Distribution</div>
                </div>
            </div>

            <div id="sidebar-navigation">
                <p class="sidebar-section-label">Visão geral</p>
                <nav class="sidebar-nav">
                    <a href="{{ route('dashboard') }}" class="sidebar-link @if(request()->routeIs('dashboard')) is-active @endif">Dashboard</a>
                </nav>

                <p class="sidebar-section-label">Operação</p>
                <nav class="sidebar-nav">
                    <a href="{{ route('dominios.index') }}" class="sidebar-link @if(request()->routeIs('dominios.*') || request()->routeIs('consulta.*')) is-active @endif">Domínios</a>
                    <a href="{{ route('listas.index') }}" class="sidebar-link @if(request()->routeIs('listas.*')) is-active @endif">Fontes</a>
                    <span class="sidebar-link is-disabled" title="Em breve">Exceções <span class="sidebar-badge-soon">em breve</span></span>
                    <a href="{{ route('servidores.index') }}" class="sidebar-link @if(request()->routeIs('servidores.*')) is-active @endif">Endpoints RPZ</a>
                </nav>

                <p class="sidebar-section-label">Gestão</p>
                <nav class="sidebar-nav">
                    @if (auth()->user()->isAdmin())
                    <a href="{{ route('empresas.index') }}" class="sidebar-link @if(request()->routeIs('empresas.*')) is-active @endif">Empresas</a>
                    @elseif (auth()->user()->empresa_id)
                    <a href="{{ route('empresas.show', auth()->user()->empresa_id) }}" class="sidebar-link @if(request()->routeIs('empresas.*')) is-active @endif">Minha empresa</a>
                    @endif
                    @if (auth()->user()->isAdmin())
                    <a href="{{ route('licencas.index') }}" class="sidebar-link @if(request()->routeIs('licencas.*')) is-active @endif">Licenças</a>
                    <a href="{{ route('usuarios.index') }}" class="sidebar-link @if(request()->routeIs('usuarios.*')) is-active @endif">Usuários</a>
                    @endif
                    <a href="{{ route('sugestoes.index') }}" class="sidebar-link @if(request()->routeIs('sugestoes.*')) is-active @endif">Sugestões</a>
                </nav>

                @if (auth()->user()->isAdmin())
                <p class="sidebar-section-label">Sistema</p>
                <nav class="sidebar-nav">
                    <a href="{{ route('auditoria.index') }}" class="sidebar-link @if(request()->routeIs('auditoria.*')) is-active @endif">Auditoria</a>
                    <a href="{{ route('seguranca.index') }}" class="sidebar-link @if(request()->routeIs('seguranca.*')) is-active @endif">Segurança</a>
                    <a href="{{ route('configuracoes.index') }}" class="sidebar-link @if(request()->routeIs('configuracoes.*')) is-active @endif">Configurações</a>
                </nav>
                @endif
            </div>

            <div class="sidebar-footer">
                <div class="environment-status">
                    <span class="status-dot"></span>
                    <div>
                        <strong>RPZ Manager</strong>
                        <span>Painel administrativo</span>
                    </div>
                </div>
            </div>
        </aside>

        <div class="app-content">
            <header class="app-topbar">
                <button type="button" class="mobile-menu-toggle" id="mobile-menu-toggle" aria-label="Abrir menu">&#9776;</button>
                <div class="topbar-actions">
                    <button type="button" class="topbar-icon-button theme-toggle" id="theme-toggle" aria-label="Alternar tema">
                        <span class="theme-icon theme-icon-sun">&#9788;</span>
                        <span class="theme-icon theme-icon-moon">&#9789;</span>
                    </button>

                    <div class="account-menu">
                        <button type="button" class="account-menu-toggle user-menu" id="account-menu-toggle" data-actions-menu-toggle aria-controls="account-menu-dropdown" aria-haspopup="true" aria-expanded="false">
                            <span class="user-avatar @if(auth()->user()->avatar) has-symbol @endif">
                                @if (auth()->user()->avatarSymbol())
                                    <span class="user-avatar-symbol">{{ auth()->user()->avatarSymbol() }}</span>
                                @else
                                    <span class="user-avatar-initials">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</span>
                                @endif
                            </span>
                            <span class="user-details">
                                <strong>{{ auth()->user()->name }}</strong>
                                <span>{{ auth()->user()->isAdmin() ? 'Administrador' : 'Cliente' }}</span>
                            </span>
                            <span class="account-menu-chevron">&#9662;</span>
                        </button>
                        <div class="account-menu-dropdown" id="account-menu-dropdown" data-actions-menu-dropdown hidden>
                            <div class="account-menu-header">
                                <strong>{{ auth()->user()->name }}</strong>
                                <span>{{ auth()->user()->email }}</span>
                            </div>
                            <a href="{{ route('profile.show') }}" class="account-menu-item">Meu perfil</a>
                            <a href="{{ route('profile.password') }}" class="account-menu-item">Alterar senha</a>
                            <div class="account-menu-divider"></div>
                            <form action="{{ route('logout') }}" method="POST">
                                @csrf
                                <button type="submit" class="account-menu-item account-menu-logout" style="width:100%">Sair</button>
                            </form>
                        </div>
                    </div>
                </div>
            </header>

            <main class="page-content">
                @if (session('status'))
                    <div class="alert-success">{{ session('status') }}</div>
                @endif

                @if ($errors->any())
                    <div class="alert-error">
                        <ul style="margin:0;padding-left:18px">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @yield('content')
            </main>
        </div>
    </div>

    <script>
        (function () {
            var root = document.documentElement;
            var themeToggle = document.getElementById('theme-toggle');
            if (themeToggle) {
                themeToggle.addEventListener('click', function () {
                    var isLight = root.getAttribute('data-theme') === 'light';
                    if (isLight) {
                        root.setAttribute('data-theme', 'dark');
                        localStorage.setItem('dns-panel-theme', 'dark');
                    } else {
                        root.setAttribute('data-theme', 'light');
                        localStorage.setItem('dns-panel-theme', 'light');
                    }
                });
            }

            var menuToggle = document.getElementById('mobile-menu-toggle');
            var nav = document.getElementById('sidebar-navigation');
            if (menuToggle && nav) {
                menuToggle.addEventListener('click', function () {
                    nav.classList.toggle('is-open');
                });
            }

            // O menu da conta usa o mesmo mecanismo generico de "[data-actions-menu-toggle]"
            // definido mais abaixo (compartilhado com os menus "..." de acao das tabelas).

            var successAlert = document.querySelector('.alert-success');
            if (successAlert) {
                window.setTimeout(function () {
                    successAlert.style.transition = 'opacity 0.4s ease';
                    successAlert.style.opacity = '0';
                    window.setTimeout(function () {
                        successAlert.remove();
                    }, 400);
                }, 4000);
            }

            function closeAllActionsMenus(except) {
                document.querySelectorAll('[data-actions-menu-dropdown]:not([hidden])').forEach(function (panel) {
                    if (panel === except) {
                        return;
                    }
                    panel.hidden = true;
                    var toggle = document.querySelector('[data-actions-menu-toggle][aria-controls="' + panel.id + '"]');
                    if (toggle) {
                        toggle.setAttribute('aria-expanded', 'false');
                    }
                });
            }

            // Usa position:fixed calculado via JS (nao absolute) porque varias tabelas
            // ficam dentro de um wrapper com overflow-x:auto (".table-responsive"), que
            // por spec do CSS tambem clipa o eixo vertical -- um dropdown absolute dentro
            // dessa tabela ficaria cortado. Fixed escapa desse clipping (nao ha ancestral
            // com transform/filter nesta pagina) e ainda assim fecha com o resto do menu.
            function positionActionsMenu(toggle, panel) {
                panel.style.visibility = 'hidden';
                panel.hidden = false;

                var rect = toggle.getBoundingClientRect();
                var panelRect = panel.getBoundingClientRect();
                var margin = 8;

                var left = rect.right - panelRect.width;
                left = Math.max(margin, Math.min(left, window.innerWidth - panelRect.width - margin));

                var top = rect.bottom + 6;
                if (top + panelRect.height > window.innerHeight - margin) {
                    top = rect.top - panelRect.height - 6;
                }
                top = Math.max(margin, top);

                panel.style.top = top + 'px';
                panel.style.left = left + 'px';
                panel.style.visibility = '';
                panel.hidden = true;
            }

            document.addEventListener('click', function (event) {
                var toggle = event.target.closest('[data-actions-menu-toggle]');
                if (toggle) {
                    event.stopPropagation();
                    var panel = document.getElementById(toggle.getAttribute('aria-controls'));
                    if (!panel) {
                        return;
                    }
                    var isOpen = toggle.getAttribute('aria-expanded') === 'true';
                    closeAllActionsMenus(isOpen ? null : panel);
                    if (!isOpen) {
                        positionActionsMenu(toggle, panel);
                    }
                    toggle.setAttribute('aria-expanded', String(!isOpen));
                    panel.hidden = isOpen;
                    return;
                }

                if (!event.target.closest('[data-actions-menu-dropdown]')) {
                    closeAllActionsMenus(null);
                }
            });

            window.addEventListener('scroll', function () {
                closeAllActionsMenus(null);
            }, true);
            window.addEventListener('resize', function () {
                closeAllActionsMenus(null);
            });

            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape') {
                    closeAllActionsMenus(null);
                }
            });
        })();
    </script>
</body>
</html>
