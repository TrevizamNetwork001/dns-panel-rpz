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

            <p class="sidebar-section-label">Visão geral</p>
            <nav class="sidebar-nav">
                <a href="{{ route('dashboard') }}" class="sidebar-link @if(request()->routeIs('dashboard')) is-active @endif">Dashboard</a>
            </nav>

            <p class="sidebar-section-label">Gestão</p>
            <nav class="sidebar-nav" id="sidebar-navigation">
                @if (auth()->user()->isAdmin())
                <a href="{{ route('empresas.index') }}" class="sidebar-link @if(request()->routeIs('empresas.*')) is-active @endif">Empresas</a>
                @elseif (auth()->user()->empresa_id)
                <a href="{{ route('empresas.show', auth()->user()->empresa_id) }}" class="sidebar-link @if(request()->routeIs('empresas.*')) is-active @endif">Minha empresa</a>
                @endif
                <a href="{{ route('servidores.index') }}" class="sidebar-link @if(request()->routeIs('servidores.*')) is-active @endif">Servidores</a>
                <a href="{{ route('listas.index') }}" class="sidebar-link @if(request()->routeIs('listas.*')) is-active @endif">Listas</a>
                @if (auth()->user()->isAdmin())
                <a href="{{ route('licencas.index') }}" class="sidebar-link @if(request()->routeIs('licencas.*')) is-active @endif">Licenças</a>
                @endif
                <a href="{{ route('sugestoes.index') }}" class="sidebar-link @if(request()->routeIs('sugestoes.*')) is-active @endif">Sugestões</a>
                <a href="{{ route('consulta.index') }}" class="sidebar-link @if(request()->routeIs('consulta.*')) is-active @endif">Consulta</a>
            </nav>

            @if (auth()->user()->isAdmin())
            <p class="sidebar-section-label">Segurança</p>
            <nav class="sidebar-nav">
                <a href="{{ route('auditoria.index') }}" class="sidebar-link @if(request()->routeIs('auditoria.*')) is-active @endif">Auditoria</a>
            </nav>
            @endif

            <div class="sidebar-footer">
                <div class="environment-status">
                    <span class="status-dot"></span>
                    <div>
                        <strong>DNS Panel RPZ</strong>
                        <span>ainda em revisão</span>
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
                        <button type="button" class="account-menu-toggle user-menu" id="account-menu-toggle" aria-haspopup="true" aria-expanded="false">
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
                        <div class="account-menu-dropdown" id="account-menu-dropdown" hidden>
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

            var accountToggle = document.getElementById('account-menu-toggle');
            var accountDropdown = document.getElementById('account-menu-dropdown');
            if (accountToggle && accountDropdown) {
                accountToggle.addEventListener('click', function (event) {
                    event.stopPropagation();
                    var isOpen = accountToggle.getAttribute('aria-expanded') === 'true';
                    accountToggle.setAttribute('aria-expanded', String(!isOpen));
                    accountDropdown.hidden = isOpen;
                });

                document.addEventListener('click', function (event) {
                    if (!accountDropdown.hidden && !accountDropdown.contains(event.target) && event.target !== accountToggle) {
                        accountDropdown.hidden = true;
                        accountToggle.setAttribute('aria-expanded', 'false');
                    }
                });

                document.addEventListener('keydown', function (event) {
                    if (event.key === 'Escape' && !accountDropdown.hidden) {
                        accountDropdown.hidden = true;
                        accountToggle.setAttribute('aria-expanded', 'false');
                    }
                });
            }
        })();
    </script>
</body>
</html>
