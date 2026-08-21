@extends('layouts.app')

@section('title', 'Tokens de API')

@section('content')
    <div class="page-heading">
        <div>
            <div class="page-eyebrow">Conta</div>
            <h1>Tokens de API</h1>
            <p>Use um token pessoal pra autenticar chamadas à API do painel (<code>Authorization: Bearer &lt;token&gt;</code>).</p>
        </div>
    </div>

    @if (session('novo_token'))
        <div class="panel" style="margin-bottom:14px">
            <div class="alert-error" style="margin:0;background:rgba(33,199,232,0.08);border-color:var(--cyan, #21c7e8);color:var(--text)">
                <strong>Copie agora — esse token não será mostrado de novo:</strong>
                <pre style="background:#080d17;border:1px solid var(--border);border-radius:10px;padding:12px;margin-top:10px;font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;font-size:12px;color:var(--text);overflow-x:auto;word-break:break-all;white-space:pre-wrap">{{ session('novo_token') }}</pre>
            </div>
        </div>
    @endif

    <div class="panel" style="margin-bottom:14px">
        <div class="panel-header"><h2>Criar novo token</h2></div>
        <form action="{{ route('profile.tokens.store') }}" method="POST" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap">
            @csrf
            <div class="field-group" style="margin:0;min-width:240px">
                <label for="name">Nome (pra você lembrar pra que serve)</label>
                <input class="form-control" type="text" id="name" name="name" placeholder="ex: script de monitoramento" required>
            </div>
            <button type="submit" class="button button-primary">Gerar token</button>
        </form>
    </div>

    <div class="panel">
        <div class="panel-header"><h2>Tokens ativos</h2></div>
        @if ($tokens->isEmpty())
            <div class="empty-state"><span>Nenhum token criado ainda.</span></div>
        @else
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr><th>Nome</th><th>Criado em</th><th>Último uso</th><th class="table-actions-column"></th></tr>
                    </thead>
                    <tbody>
                        @foreach ($tokens as $token)
                            <tr>
                                <td>{{ $token->name }}</td>
                                <td class="table-mono">{{ $token->created_at->format('d/m/Y H:i') }}</td>
                                <td class="table-mono">{{ optional($token->last_used_at)->format('d/m/Y H:i') ?? 'nunca usado' }}</td>
                                <td>
                                    <form action="{{ route('profile.tokens.destroy', $token->id) }}" method="POST">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="table-action-link" style="background:none;border:0" onclick="return confirm('Revogar este token? Qualquer integração usando ele para de funcionar.')">Revogar</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <div class="panel" style="margin-top:14px">
        <div class="panel-header"><h2>Como usar</h2></div>
        <pre style="background:#080d17;border:1px solid var(--border);border-radius:10px;padding:14px;font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;font-size:12px;color:var(--text);overflow-x:auto">curl {{ url('/api/v1/servidores') }} \
  -H "Authorization: Bearer SEU_TOKEN_AQUI"</pre>
        <p style="color:var(--text-muted);font-size:11px;margin-top:8px">Cliente vê só os recursos da própria empresa. Admin vê tudo. Limite de 60 requisições por minuto.</p>
    </div>
@endsection
