@php
    $navigation = ['inicio' => 'Início', 'solucoes' => 'Soluções', 'recursos' => 'Recursos', 'seguranca' => 'Segurança', 'documentacao' => 'Documentação'];
    $modules = [
        ['name' => 'DNS Autoritativo', 'tone' => 'violet', 'icon' => 'server', 'status' => 'Planejado', 'diagram' => 'Zonas, registros e delegações.', 'description' => 'Gerenciamento centralizado de zonas, registros e delegações DNS.'],
        ['name' => 'DNS Recursivo', 'tone' => 'blue', 'icon' => 'refresh', 'status' => 'Integração com Unbound', 'diagram' => 'Resolução, cache e controle operacional.', 'description' => 'Resolução no Unbound do provedor, com cache no resolver e acompanhamento das consultas ao feed. A gestão nativa está planejada.'],
        ['name' => 'Políticas RPZ', 'tone' => 'green', 'icon' => 'shield', 'status' => 'Disponível', 'diagram' => 'Proteção e controle de respostas DNS.', 'description' => 'Controle das respostas DNS com listas de bloqueio, permissões, exceções e políticas personalizadas.'],
    ];
    $benefits = [
        ['activity', 'Alta Performance', 'Feeds versionados para distribuição eficiente de políticas.'],
        ['shield', 'Segurança DNS', 'Políticas de resposta DNS e controle de acesso ao feed por IP.'],
        ['search', 'Visibilidade Operacional', 'Histórico de consultas ao feed e mudanças auditáveis.'],
        ['network', 'Gestão Unificada', 'Administre listas, exceções e servidores RPZ no mesmo painel.'],
        ['server', 'Escalabilidade', 'Organize os feeds por empresa e servidor conforme a operação cresce.'],
        ['settings', 'Automação', 'Sincronização de listas externas e importações com revisão.'],
    ];
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="DNS Center para provedores e empresas. Gerencie políticas de resposta DNS, distribua feeds RPZ e acompanhe a integração com Unbound.">
    <meta name="theme-color" content="#071a36">
    <title>DNS Center | Plataforma Unificada de DNS</title>
    <link rel="stylesheet" href="{{ asset('assets/landing.css') }}">
</head>
<body>
    <a class="skip-link" href="#conteudo">Pular para o conteúdo</a>
    <header class="site-header">
        <div class="container header-row">
            <a class="brand" href="#inicio" aria-label="DNS Center, início">
                @include('landing.mark')
                <span><strong>DNS</strong> Center</span>
            </a>
            <span class="header-tagline">Plataforma Completa de DNS</span>
            <nav class="desktop-nav" aria-label="Navegação principal">
                @foreach ($navigation as $anchor => $label)
                    <a href="#{{ $anchor }}">{{ $label }}</a>
                @endforeach
            </nav>
            <a class="button header-access" href="{{ route('login') }}">Acessar Plataforma</a>
            <details class="mobile-nav">
                <summary aria-label="Menu de navegação"><x-ui-icon name="list" size="22" /><span>Menu</span></summary>
                <nav aria-label="Navegação no celular">
                    @foreach ($navigation as $anchor => $label)
                        <a href="#{{ $anchor }}">{{ $label }}</a>
                    @endforeach
                    <a class="button primary" href="{{ route('login') }}">Acessar Plataforma</a>
                </nav>
            </details>
        </div>
    </header>

    <main id="conteudo" tabindex="-1">
        <section class="hero" id="inicio" aria-labelledby="hero-title">
            <div class="container hero-grid">
                <div class="hero-copy">
                    <p class="eyebrow"><x-ui-icon name="shield" size="17" /> Plataforma Unificada de DNS</p>
                    <h1 id="hero-title">DNS Autoritativo, Recursivo e Políticas RPZ em uma <span>única plataforma.</span></h1>
                    <p class="hero-description">Centralize o gerenciamento de zonas DNS, a resolução recursiva e as políticas de segurança RPZ em uma experiência integrada. Mais controle, visibilidade e eficiência para sua infraestrutura DNS.</p>
                    <div class="button-row">
                        <a class="button primary" href="#recursos">Conhecer a Plataforma <x-ui-icon name="chevron" size="17" /></a>
                        <a class="button secondary" href="{{ route('login') }}">Acessar Plataforma</a>
                    </div>
                    <p class="hero-scope">Para ISPs, operadoras e equipes de infraestrutura.</p>
                </div>
                <div class="hero-visual" aria-label="Visão da plataforma DNS Center">
                    <img src="{{ asset('assets/rpz-network.svg') }}?v=dns-center" alt="" width="720" height="490" fetchpriority="high">
                    @foreach ($modules as $module)
                        <div class="network-card network-card-{{ $module['tone'] }}">
                            <span class="module-icon tone-{{ $module['tone'] }}"><x-ui-icon :name="$module['icon']" size="25" /></span>
                            <div><strong>{{ $module['name'] }}</strong><small class="network-description">{{ $module['diagram'] }}</small><small class="network-status">{{ $module['status'] }}</small></div>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="container overview" id="solucoes" aria-labelledby="solutions-title">
            @include('landing.preview', ['modules' => $modules])
            <div class="solutions">
                <h2 id="solutions-title">Uma solução de DNS completa para sua operação.</h2>
                @foreach ($modules as $module)
                    <article class="solution">
                        <span class="module-icon tone-{{ $module['tone'] }}"><x-ui-icon :name="$module['icon']" size="27" /></span>
                        <div>
                            <div class="solution-heading"><h3>{{ $module['name'] }}</h3><span class="module-status tone-{{ $module['tone'] }}">{{ $module['status'] }}</span></div>
                            <p>{{ $module['description'] }}</p>
                        </div>
                    </article>
                @endforeach
            </div>
        </section>

        <section class="feature-section" id="recursos" aria-labelledby="features-title">
            <div class="container">
                <h2 id="features-title">Mais controle sobre sua operação DNS</h2>
                <div class="features">
                    @foreach ($benefits as [$icon, $label, $description])
                        <article class="feature">
                            <span class="module-icon tone-blue"><x-ui-icon :name="$icon" size="24" /></span>
                            <div><h3>{{ $label }}</h3><p>{{ $description }}</p></div>
                        </article>
                    @endforeach
                </div>
            </div>
        </section>

        <div class="container guidance">
            <section class="guide" id="seguranca" aria-labelledby="security-title">
                <span class="module-icon tone-green"><x-ui-icon name="lock" size="25" /></span>
                <h2 id="security-title">Segurança na resposta DNS</h2>
                <p>Defina as políticas por lista, controle os IPs autorizados e acompanhe quando seus servidores consultam o feed. As exceções permitem ajustar o escopo do bloqueio à sua operação.</p>
            </section>
            <section class="guide" id="documentacao" aria-labelledby="docs-title">
                <span class="module-icon tone-blue"><x-ui-icon name="list" size="25" /></span>
                <h2 id="docs-title">Como começar com o RPZ</h2>
                <ol>
                    <li>Cadastre o servidor e configure os IPs autorizados.</li>
                    <li>Vincule as listas de políticas ao servidor.</li>
                    <li>Abra a configuração técnica no painel e siga as instruções para seu resolver.</li>
                </ol>
            </section>
        </div>

        <section class="container final-section" aria-labelledby="final-title">
            <div class="final-cta">
                <div>
                    <h2 id="final-title">Sua infraestrutura DNS merece uma plataforma completa.</h2>
                    <p>Centralize a gestão, simplifique a operação e evolua a segurança da sua rede com o DNS Center.</p>
                </div>
                <a class="button primary" href="{{ route('login') }}">Acessar Plataforma <x-ui-icon name="chevron" size="17" /></a>
            </div>
        </section>
    </main>

    <footer class="site-footer">
        <div class="container footer-row">
            <div>
                <a class="brand" href="#inicio">@include('landing.mark') <span><strong>DNS</strong> Center</span></a>
                <p>Um produto desenvolvido por Trevizam Network.</p>
            </div>
            <nav aria-label="Navegação no rodapé">
                @foreach ($navigation as $anchor => $label)
                    <a href="#{{ $anchor }}">{{ $label }}</a>
                @endforeach
                <a href="{{ route('login') }}">Acessar Plataforma</a>
            </nav>
            @include('landing.product-footer')
        </div>
    </footer>
    <script src="{{ asset('assets/landing.js') }}" defer></script>
</body>
</html>
