@extends('layouts.app')

@section('title', 'Segurança do Unbound')

@section('content')
    <style>
        .unbound-seguranca-breadcrumb { display: flex; align-items: center; gap: 12px; color: var(--text-soft); font-size: 12px; margin-bottom: 28px; }
        .unbound-seguranca-breadcrumb a { color: var(--cyan); }
    </style>
    <nav class="unbound-seguranca-breadcrumb" aria-label="Navegação"><a href="{{ route('servidores.index') }}">{{ auth()->user()->isAdmin() ? 'Endpoints RPZ' : 'Servidores' }}</a><x-ui-icon name="chevron" size="14" /><span>Segurança do Unbound</span></nav>

    <div class="page-heading page-heading-compact">
        <div>
            <div class="page-eyebrow">Vulnerabilidades conhecidas</div>
            <h1>Segurança do Unbound</h1>
            <p>Falhas de segurança já corrigidas pela NLnet Labs em versões anteriores à 1.26.1.</p>
        </div>
    </div>

    <div class="panel details-card-wide" style="margin-bottom:16px">
        <div class="alert-error" role="alert" style="margin:0">
            Se o seu servidor está com o selo <strong>"Unbound desatualizado"</strong>, ele não está só correndo risco de parar de atualizar a lista de bloqueios sozinho — está rodando uma versão com <strong>falha de segurança crítica conhecida e já divulgada publicamente</strong>. Atualizar o pacote Unbound corrige tudo listado nesta página de uma vez.
        </div>
    </div>

    <div class="panel details-card-wide" style="margin-bottom:16px">
        <div class="panel-header"><h2>As mais graves</h2></div>
        <dl class="details-list">
            <div>
                <dt style="color:var(--danger);font-weight:700">CVE-2026-81642 — crítica</dt>
                <dd>Um servidor DNS malicioso, ao ser consultado pelo seu Unbound, pode enviar uma resposta especialmente formatada que <strong>trava o processo ou executa código arbitrário</strong> nele. Afeta todas as versões até a 1.26.0. Corrigido na 1.26.1.</dd>
            </div>
            <div>
                <dt style="color:var(--danger);font-weight:700">CVE-2026-33278 — crítica</dt>
                <dd>Falha equivalente durante a validação DNSSEC, também podendo levar à execução de código. Afeta as versões 1.19.1 a 1.25.0. Corrigido na 1.25.1 (e continua corrigido na 1.26.1).</dd>
            </div>
            <div>
                <dt>CVE-2025-11411</dt>
                <dd>Permite envenenamento de cache (o resolver aceita respostas de domínio que não deveria, possibilitando sequestro de domínio). Corrigido na 1.24.1/1.24.2.</dd>
            </div>
        </dl>
    </div>

    <div class="panel details-card-wide" style="margin-bottom:16px">
        <div class="panel-header"><h2>Volume de correções por versão</h2></div>
        <p style="color:var(--text-muted);font-size:12px;margin:0 0 12px">A NLnet Labs costuma agrupar várias falhas reportadas num período em um único lançamento — por isso alguns números abaixo são altos.</p>
        <div class="table-responsive">
            <table class="data-table">
                <thead><tr><th>Versão</th><th>Data</th><th>Falhas corrigidas</th></tr></thead>
                <tbody>
                    <tr><td class="table-mono">1.26.1</td><td>16/09</td><td>9 (inclui as 2 críticas acima)</td></tr>
                    <tr><td class="table-mono">1.25.2</td><td>22/07</td><td>20</td></tr>
                    <tr><td class="table-mono">1.25.1</td><td>20/05</td><td>11 (inclui 1 crítica)</td></tr>
                    <tr><td class="table-mono">1.24.1 / 1.24.2</td><td>22/10 e 26/11</td><td>1 (envenenamento de cache)</td></tr>
                    <tr><td class="table-mono">1.23.1</td><td>16/07</td><td>1 ("Rebirthday Attack", CVE-2025-5994)</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <div class="panel details-card-wide">
        <div class="panel-header"><h2>Correção</h2></div>
        <p>É sempre a mesma, independente de qual dessas versões o servidor está rodando: <strong>atualizar o pacote Unbound para 1.26.1 ou mais recente</strong>. Por serem correções cumulativas, a 1.26.1 já inclui todas as anteriores.</p>
        <pre class="endpoint-config-snippet">apt update && apt upgrade unbound
unbound-control status | head -1   # confirma a versão nova</pre>
        <p style="color:var(--text-muted);font-size:11px;margin-top:10px">Fonte oficial, com o detalhe técnico completo de cada falha: <a href="https://nlnetlabs.nl/projects/unbound/security-advisories/" target="_blank" rel="noopener noreferrer" class="inline-link">nlnetlabs.nl/projects/unbound/security-advisories</a></p>
    </div>
@endsection
