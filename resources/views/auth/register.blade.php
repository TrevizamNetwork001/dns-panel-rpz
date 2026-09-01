<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Cadastro — DNS Panel RPZ</title>
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
        <div class="login-card" style="max-width:520px">
            <div class="brand">DNS PANEL</div>
            <h1>Criar conta</h1>
            <p class="subtitle">Cadastre sua empresa. A ativação da licença é feita pelo administrador após o cadastro.</p>

            @if ($errors->any())
                <div class="alert-error">
                    @foreach ($errors->all() as $error)
                        {{ $error }}<br>
                    @endforeach
                </div>
            @endif

            <form action="{{ route('register.store') }}" method="POST">
                @csrf
                <div aria-hidden="true" style="position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden">
                    <label for="website">Website</label>
                    <input id="website" name="website" type="text" tabindex="-1" autocomplete="off">
                </div>

                <div class="form-group">
                    <label for="empresa_nome">Nome da empresa</label>
                    <input class="form-control" type="text" id="empresa_nome" name="empresa_nome" value="{{ old('empresa_nome') }}" required>
                </div>
                <div class="form-group">
                    <label for="documento">Documento (CNPJ) — opcional</label>
                    <input class="form-control" type="text" id="documento" name="documento" value="{{ old('documento') }}">
                </div>
                <div class="form-group">
                    <label for="email_contato">E-mail de contato da empresa — opcional</label>
                    <input class="form-control" type="email" id="email_contato" name="email_contato" value="{{ old('email_contato') }}">
                </div>
                <div class="form-group">
                    <label for="responsavel_nome">Seu nome</label>
                    <input class="form-control" type="text" id="responsavel_nome" name="responsavel_nome" value="{{ old('responsavel_nome') }}" required>
                </div>
                <div class="form-group">
                    <label for="email">Seu e-mail (login)</label>
                    <input class="form-control" type="email" id="email" name="email" value="{{ old('email') }}" required>
                </div>
                <div class="form-group">
                    <label for="password">Senha</label>
                    <input class="form-control" type="password" id="password" name="password" required>
                </div>
                <div class="form-group">
                    <label for="password_confirmation">Confirmar senha</label>
                    <input class="form-control" type="password" id="password_confirmation" name="password_confirmation" required>
                </div>

                <button type="submit" class="primary-button">Cadastrar</button>
            </form>

            <p class="subtitle" style="margin-top:20px;margin-bottom:0">
                Já tem conta? <a href="{{ route('login') }}" style="color:var(--cyan)">Entrar</a>
            </p>
        </div>
    </div>
</body>
</html>
