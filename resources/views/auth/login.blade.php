<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#071a36">
    <title>Entrar | DNS Center</title>
    <link rel="stylesheet" href="{{ asset('assets/landing.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/login.css') }}?v={{ filemtime(public_path('assets/login.css')) }}">
</head>
<body class="platform-login">
    <main class="login-main">
        <section class="login-card" aria-labelledby="login-heading">
            <div class="login-brand">
                @include('landing.mark')
                <span><strong>DNS</strong> Center</span>
            </div>
            <h1 id="login-heading">Entrar na plataforma</h1>
            <p class="login-intro">Acesse com o e-mail e a senha da sua conta.</p>

            @if ($errors->any())
                <div class="login-feedback login-error" role="alert">
                    <x-ui-icon name="alert" size="18" />
                    <ul>
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if (session('status'))
                <div class="login-feedback login-success" role="status">
                    <x-ui-icon name="check" size="18" />
                    <p>{{ session('status') }}</p>
                </div>
            @endif

            <form action="{{ route('login.attempt') }}" method="POST" class="login-form">
                @csrf
                <div class="login-field">
                    <label for="email">E-mail</label>
                    <input type="email" id="email" name="email" value="{{ old('email') }}" autocomplete="username" required>
                </div>
                <div class="login-field">
                    <label for="password">Senha</label>
                    <input type="password" id="password" name="password" autocomplete="current-password" required>
                </div>
                <label class="login-remember">
                    <input type="checkbox" name="remember" value="1" @checked(old('remember'))>
                    Manter conectado
                </label>
                <button type="submit" class="button primary">Entrar <x-ui-icon name="chevron" size="17" /></button>
            </form>
            <a class="login-back" href="{{ route('dashboard') }}">
                <x-ui-icon name="chevron" size="15" />
                Voltar ao início
            </a>
        </section>
    </main>
</body>
</html>
