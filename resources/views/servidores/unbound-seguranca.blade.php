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
            <p>Referência de segurança: Unbound 1.26.2, lançado em 21/10/2026. Revisado em 23/10/2026.</p>
        </div>
    </div>

    <div class="panel details-card-wide" style="margin-bottom:16px">
        <div class="alert-error" role="alert" style="margin:0">
            O selo <strong>"Unbound desatualizado"</strong> indica uma versão informada pelo DNS anterior à referência upstream 1.26.2. Revise as correções disponíveis: a exposição depende da configuração e dos patches da distribuição (backports), que não aparecem nesse número. Esse aviso não comprova falha de sincronização RPZ.
        </div>
    </div>

    <div class="panel details-card-wide" style="margin-bottom:16px">
        <div class="panel-header"><h2>Corrigidas na 1.26.2 — 21/10/2026</h2></div>
        <dl class="details-list">
            <div><dt>CVE-2026-97206 — alta</dt><dd>Migração de conexão DNS-over-QUIC pode derrubar o processo com múltiplas threads. Afeta 1.22.0 a 1.26.1 quando DoQ é utilizado.</dd></div>
            <div><dt>CVE-2026-96405 — média</dt><dd>Respostas com compressão DNS manipulada podem consumir CPU e degradar o resolvedor. Afeta versões até 1.26.1.</dd></div>
            <div><dt>CVE-2026-109125 — média</dt><dd>Possível envenenamento de cache com <code>unwanted-reply-threshold &gt; 0</code>; essa condição não existe na configuração padrão. Afeta versões até 1.26.1.</dd></div>
            <div><dt>CVE-2026-50106 — média</dt><dd>NXDOMAIN manipulado pode contornar a validação de uma zona com âncora DNSSEC própria sob um pai inseguro. Afeta versões até 1.26.1.</dd></div>
            <div><dt>CVE-2026-35501 — baixa</dt><dd>NSEC manipulado pode contornar essa âncora DNSSEC própria. Afeta 1.0.1 a 1.26.1.</dd></div>
        </dl>
        <p>Para quem usa nossas listas RPZ, a correção de consumo de CPU merece atenção. As demais dependem dos recursos habilitados no DNS do cliente. A atualização não promete reduzir o tempo de carregamento das listas.</p>
        <p><a href="https://community.nlnetlabs.nl/t/unbound-1-26-2-released/3495" target="_blank" rel="noopener noreferrer" class="inline-link">Anúncio oficial da 1.26.2</a></p>
    </div>

    <div class="panel details-card-wide" style="margin-bottom:16px">
        <div class="panel-header"><h2>As mais graves</h2></div>
        <dl class="details-list">
            <div>
                <dt style="color:var(--danger);font-weight:700">CVE-2026-81642 — crítica</dt>
                <dd>Um servidor DNS malicioso, ao ser consultado pelo seu Unbound, pode enviar uma resposta especialmente formatada que <strong>pode travar o processo ou permitir execução de código arbitrário</strong> nele. Afeta todas as versões até a 1.26.0. Corrigido na 1.26.1.</dd>
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
                    <tr><td class="table-mono">1.26.2</td><td>21/10/2026</td><td>5 (1 alta, 3 médias e 1 baixa)</td></tr>
                    <tr><td class="table-mono">1.26.1</td><td>16/09</td><td>9 (inclui a CVE-2026-81642)</td></tr>
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
        <p>Atualize para <strong>Unbound 1.26.2 ou mais recente</strong>, ou use um pacote da distribuição com os patches equivalentes confirmados no boletim do fornecedor. A versão upstream inclui as correções anteriores.</p>
        <p>Use o procedimento de atualização do sistema do cliente. Em Debian/Ubuntu, confira primeiro o candidato disponível; o repositório pode manter uma versão anterior com backports:</p>
        <pre class="endpoint-config-snippet">apt-cache policy unbound
unbound -V
unbound-checkconf
unbound-control status</pre>
        <p>Depois da atualização, confirme a versão em execução, consulte o domínio canário indicado na configuração do servidor e confira a atualização da zona nos logs. O painel detecta a versão pelo User-Agent da próxima busca RPZ.</p>
        <p style="color:var(--text-muted);font-size:11px;margin-top:10px">Fonte oficial, com o detalhe técnico completo de cada falha: <a href="https://nlnetlabs.nl/projects/unbound/security-advisories/" target="_blank" rel="noopener noreferrer" class="inline-link">nlnetlabs.nl/projects/unbound/security-advisories</a></p>
    </div>
@endsection
