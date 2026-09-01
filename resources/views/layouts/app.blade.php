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
                    <a href="{{ route('dashboard') }}" class="sidebar-link @if(request()->routeIs('dashboard')) is-active @endif"><svg class="sidebar-nav-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg>Dashboard</a>
                </nav>

                <p class="sidebar-section-label">Operação</p>
                <nav class="sidebar-nav">
                    <a href="{{ route('dominios.index') }}" class="sidebar-link @if(request()->routeIs('dominios.*') || request()->routeIs('consulta.*')) is-active @endif"><svg class="sidebar-nav-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M3 12h18"/><path d="M12 3a15 15 0 0 1 0 18a15 15 0 0 1 0-18Z"/></svg>Domínios</a>
                    <a href="{{ route('listas.index') }}" class="sidebar-link @if(request()->routeIs('listas.*')) is-active @endif"><svg class="sidebar-nav-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 6h11"/><path d="M9 12h11"/><path d="M9 18h11"/><path d="M4 6h.01"/><path d="M4 12h.01"/><path d="M4 18h.01"/></svg>{{ auth()->user()->isAdmin() ? 'Fontes' : 'Listas' }}</a>
                    @if (auth()->user()->isAdmin())
                    <span class="sidebar-link is-disabled" title="Em breve"><svg class="sidebar-nav-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="m5.5 5.5 13 13"/></svg>Exceções <span class="sidebar-badge-soon">em breve</span></span>
                    @endif
                    <a href="{{ route('servidores.index') }}" class="sidebar-link @if(request()->routeIs('servidores.*')) is-active @endif"><svg class="sidebar-nav-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="7" rx="1.5"/><rect x="3" y="13" width="18" height="7" rx="1.5"/><path d="M7 7.5h.01"/><path d="M7 16.5h.01"/></svg>{{ auth()->user()->isAdmin() ? 'Endpoints RPZ' : 'Servidores' }}</a>
                </nav>

                <p class="sidebar-section-label">Gestão</p>
                <nav class="sidebar-nav">
                    @if (auth()->user()->isAdmin())
                    <a href="{{ route('empresas.index') }}" class="sidebar-link @if(request()->routeIs('empresas.*')) is-active @endif"><svg class="sidebar-nav-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18"/><path d="M6 21V5a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1v16"/><path d="M18 21V9a1 1 0 0 0-1-1h-3"/><path d="M9 7h1"/><path d="M9 11h1"/><path d="M9 15h1"/></svg>Empresas</a>
                    @elseif (auth()->user()->empresa_id)
                    <a href="{{ route('empresas.show', auth()->user()->empresa_id) }}" class="sidebar-link @if(request()->routeIs('empresas.*')) is-active @endif"><svg class="sidebar-nav-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18"/><path d="M6 21V5a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1v16"/><path d="M18 21V9a1 1 0 0 0-1-1h-3"/><path d="M9 7h1"/><path d="M9 11h1"/><path d="M9 15h1"/></svg>Minha empresa</a>
                    @endif
                    @if (auth()->user()->isAdmin())
                    <a href="{{ route('licencas.index') }}" class="sidebar-link @if(request()->routeIs('licencas.*')) is-active @endif"><svg class="sidebar-nav-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="8" cy="15" r="4"/><path d="m10.8 12.2 8.5-8.5"/><path d="m16.5 6.5 2 2"/><path d="m14 9 2 2"/></svg>Licenças</a>
                    <a href="{{ route('usuarios.index') }}" class="sidebar-link @if(request()->routeIs('usuarios.*')) is-active @endif"><svg class="sidebar-nav-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.5-6 8-6s8 2 8 6"/></svg>Usuários</a>
                    @endif
                    <a href="{{ route('sugestoes.index') }}" class="sidebar-link @if(request()->routeIs('sugestoes.*')) is-active @endif"><svg class="sidebar-nav-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8a6 6 0 0 0-12 0c0 4-1.5 5.5-2 6.5h16c-.5-1-2-2.5-2-6.5Z"/><path d="M10 19a2 2 0 0 0 4 0"/></svg>Sugestões</a>
                </nav>

                @if (auth()->user()->isAdmin())
                <p class="sidebar-section-label">Sistema</p>
                <nav class="sidebar-nav">
                    <a href="{{ route('auditoria.index') }}" class="sidebar-link @if(request()->routeIs('auditoria.*')) is-active @endif"><svg class="sidebar-nav-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8Z"/><path d="M14 3v5h5"/><path d="M9 13h6"/><path d="M9 17h6"/></svg>Auditoria</a>
                    <a href="{{ route('seguranca.index') }}" class="sidebar-link @if(request()->routeIs('seguranca.*')) is-active @endif"><svg class="sidebar-nav-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3 4 6.5V11c0 4.8 3.2 8.9 8 10 4.8-1.1 8-5.2 8-10V6.5Z"/></svg>Segurança</a>
                    <a href="{{ route('configuracoes.index') }}" class="sidebar-link @if(request()->routeIs('configuracoes.*')) is-active @endif"><svg class="sidebar-nav-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .34 1.87l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.7 1.7 0 0 0-1.87-.34 1.7 1.7 0 0 0-1.04 1.56V21a2 2 0 1 1-4 0v-.09A1.7 1.7 0 0 0 9 19.37a1.7 1.7 0 0 0-1.87.34l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.7 1.7 0 0 0 4.63 15a1.7 1.7 0 0 0-1.56-1.04H3a2 2 0 1 1 0-4h.09A1.7 1.7 0 0 0 4.63 9a1.7 1.7 0 0 0-.34-1.87l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.7 1.7 0 0 0 9 4.63a1.7 1.7 0 0 0 1.04-1.56V3a2 2 0 1 1 4 0v.09A1.7 1.7 0 0 0 15 4.63a1.7 1.7 0 0 0 1.87-.34l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.7 1.7 0 0 0 19.37 9a1.7 1.7 0 0 0 1.56 1.04H21a2 2 0 1 1 0 4h-.09A1.7 1.7 0 0 0 19.4 15Z"/></svg>Configurações</a>
                </nav>
                @endif
            </div>

            @php
                $appVersion = json_decode(file_get_contents(base_path('composer.json')))->version ?? null;

                // Ultimo resultado real do "php artisan health:check" (roda por timer,
                // ver RUNBOOK.md) -- cacheado 5 min pra nao consultar a auditoria em
                // toda carga de pagina (este rodape aparece em todas elas).
                $ultimoHealthAction = \Illuminate\Support\Facades\Cache::remember('sidebar-health-status', 300, function () {
                    return \App\Models\AuditLog::where('action', 'like', 'health.%')->orderByDesc('id')->value('action');
                });
                $ultimoHealth = $ultimoHealthAction !== null;
                $sistemasOk = $ultimoHealthAction === 'health.ok';
            @endphp
            <div class="sidebar-footer">
                <div class="environment-status">
                    <span class="status-dot @if($ultimoHealth && ! $sistemasOk) is-warning @endif"></span>
                    <div>
                        <strong>DNS Panel RPZ</strong>
                        @if ($ultimoHealth)
                            <span>{{ $sistemasOk ? 'Todos os sistemas operacionais' : 'Alerta de saúde detectado' }}</span>
                        @else
                            <span>Aguardando primeira checagem</span>
                        @endif
                    </div>
                </div>
                @if ($appVersion)
                    <div class="sidebar-version">
                        <span>v{{ $appVersion }}</span>
                        @if (auth()->user()->isAdmin() && ! request()->routeIs('servidores.*'))
                            <span class="sidebar-version-badge">Estável</span>
                        @endif
                    </div>
                @endif
            </div>
        </aside>

        <div class="app-content">
            <header class="app-topbar">
                <button type="button" class="mobile-menu-toggle" id="mobile-menu-toggle" aria-label="Abrir menu">&#9776;</button>
                <div class="topbar-actions">
                    <button type="button" class="theme-switch @if(request()->routeIs('dashboard') && auth()->user()->isAdmin()) is-dashboard-theme-switch @endif" id="theme-toggle" role="switch" aria-label="Alternar entre tema claro e escuro">
                        <svg class="theme-switch-icon theme-switch-sun" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"/><path d="M12 3v1.5M12 19.5V21M4.9 4.9l1.1 1.1M18 18l1.1 1.1M3 12h1.5M19.5 12H21M4.9 19.1 6 18M18 6l1.1-1.1"/></svg>
                        @if(request()->routeIs('dashboard') && auth()->user()->isAdmin())
                            <svg class="theme-switch-icon theme-switch-moon" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 15.2A8.5 8.5 0 0 1 8.8 4a8.5 8.5 0 1 0 11.2 11.2Z"/></svg>
                        @endif
                        <span class="theme-switch-thumb"></span>
                    </button>

                    <details class="account-menu">
                        <summary class="account-menu-toggle user-menu" aria-label="Abrir menu da conta">
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
                        </summary>
                        <div class="account-menu-dropdown">
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
                    </details>
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
