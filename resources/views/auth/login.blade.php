<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Entrar — DNS Panel RPZ</title>
    <script>
        (function () {
            var stored = localStorage.getItem('dns-panel-theme');
            document.documentElement.setAttribute('data-theme', stored === 'light' ? 'light' : 'dark');
        })();
    </script>
    <link rel="stylesheet" href="{{ asset('assets/app.css') }}?v={{ filemtime(public_path('assets/app.css')) }}">
</head>
<body>
    <div class="login-page">
        <div class="login-card">
            <div class="brand">DNS PANEL</div>
            <h1>Entrar</h1>
            <p class="subtitle">Acesse com o e-mail e a senha da sua conta.</p>

            @if ($errors->any())
                <div class="alert-error">
                    @foreach ($errors->all() as $error)
                        {{ $error }}
                    @endforeach
                </div>
            @endif

            @if (session('status'))
                <div class="alert-success">{{ session('status') }}</div>
            @endif

            <form action="{{ route('login.attempt') }}" method="POST">
                @csrf
                <div class="form-group">
                    <label for="email">E-mail</label>
                    <input class="form-control" type="email" id="email" name="email" value="{{ old('email') }}" required autofocus>
                </div>
                <div class="form-group">
                    <label for="password">Senha</label>
                    <input class="form-control" type="password" id="password" name="password" required>
                </div>
                <label class="remember">
                    <input type="checkbox" name="remember" value="1"> Manter conectado
                </label>
                <button type="submit" class="primary-button">Entrar</button>
            </form>

            <p class="subtitle" style="margin-top:20px;margin-bottom:0">
                Ainda não tem conta? <a href="{{ route('register') }}" style="color:var(--cyan)">Cadastre sua empresa</a>
            </p>
        </div>
    </div>
</body>
</html>
