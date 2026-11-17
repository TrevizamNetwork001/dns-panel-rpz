<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Acesso não autorizado | Trevizam DNS</title>
    <style>
        :root {
            color-scheme: dark;
            font-family: system-ui, -apple-system, "Segoe UI", sans-serif;
            color: #f1f6fc;
            background: #08111e;
        }

        * { box-sizing: border-box; }

        body {
            display: grid;
            place-items: center;
            min-height: 100vh;
            min-height: 100svh;
            margin: 0;
            padding: 1rem;
            background: radial-gradient(ellipse at top, #162d46 0, transparent 65%);
        }

        main {
            width: min(100%, 36rem);
            overflow: hidden;
            background: #101e2e;
            border: 1px solid #2b4158;
            border-radius: 1.25rem;
            box-shadow: 0 1.5rem 4rem rgb(0 0 0 / 28%);
        }

        .header {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: .75rem 1rem;
            padding: 1rem clamp(1rem, 5vw, 2.5rem);
            border-bottom: 1px solid #2b4158;
        }

        .brand {
            margin: 0;
            color: #c1d0e0;
            font-size: .875rem;
        }

        .brand strong {
            display: block;
            color: #f1f6fc;
            font-size: 1rem;
            letter-spacing: .06em;
        }

        .status {
            padding: .3rem .65rem;
            border: 1px solid #745638;
            border-radius: .4rem;
            color: #ffd398;
            background: #30251f;
            font: 600 .8125rem/1.25 ui-monospace, monospace;
        }

        .content { padding: clamp(1rem, 5vw, 2.5rem); }

        h1 {
            margin: 0 0 .75rem;
            font-size: clamp(1.5rem, 5vw, 2.25rem);
            line-height: 1.15;
            letter-spacing: -.025em;
        }

        p { line-height: 1.6; }

        .message {
            margin: 0;
            color: #c1d0e0;
        }

        .guidance {
            margin: 1.5rem 0 0;
            padding: .875rem 1rem;
            border-inline-start: 3px solid #72c4f4;
            border-radius: .25rem;
            background: #172b41;
            color: #d7e6f6;
            font-size: .9375rem;
        }
    </style>
</head>
<body>
    <main aria-labelledby="page-title">
        <header class="header">
            <p class="brand">
                <strong>Trevizam DNS</strong>
                <span>Proteção DNS centralizada</span>
            </p>
            <span class="status">HTTP 403</span>
        </header>

        <section class="content" aria-labelledby="page-title">
            <h1 id="page-title">Acesso não autorizado</h1>
            <p class="message">Este endpoint RPZ é restrito a origens previamente autorizadas.</p>
            <p class="guidance">Se você administra o servidor DNS, solicite ao responsável pelo painel a liberação do IP de origem usado para acessar o feed.</p>
        </section>
    </main>
</body>
</html>
