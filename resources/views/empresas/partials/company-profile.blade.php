<style>
    .company-profile .company-title, .company-profile .company-contact { display: flex; align-items: center; flex-wrap: wrap; gap: 12px 18px; }
    .company-profile .page-heading { align-items: center; }
    .company-profile .panel { padding: 16px 20px; }
    .company-profile .page-heading > div { min-width: 0; }
    .company-profile h1, .company-profile dd, .company-profile .company-contact { overflow-wrap: anywhere; }
    .company-profile .panel-header { justify-content: flex-start; gap: 16px; padding-bottom: 12px; margin-bottom: 8px; border-bottom: 1px solid var(--border-soft); }
    .company-profile .panel-header svg { flex-shrink: 0; color: var(--cyan); }
    .company-profile .panel-header p { margin: 5px 0 0; color: var(--text-soft); font-size: 13px; }
    .company-profile .company-fields { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); margin: 0; }
    .company-profile .company-fields > div { min-width: 0; padding: 14px 16px; border-bottom: 1px solid var(--border-soft); }
    .company-profile .company-fields > div:nth-child(n+4) { border-bottom: 0; }
    .company-profile dt { margin-bottom: 8px; color: var(--text-soft); font-size: 12px; }
    .company-profile dd { margin: 0; font-size: 14px; }
    .company-profile .company-license { display: grid; grid-template-columns: 1fr 1fr 1fr 1.5fr; margin: 0; padding: 2px 0; }
    .company-profile .company-license > div { min-width: 0; padding: 8px 20px; border-right: 1px solid var(--border-soft); }
    .company-profile .company-license > div:first-child { padding-left: 0; }
    .company-profile .company-license > div:last-child { padding-right: 0; border: 0; }
    .company-profile .company-license + .company-license { margin-top: 20px; padding-top: 20px; border-top: 1px solid var(--border-soft); }
    .company-profile .company-license small { display: block; margin-top: 10px; color: var(--text-soft); }
    .company-profile .company-utilization { display: grid; grid-template-columns: minmax(0, 1fr) max-content; align-items: center; gap: 10px; margin-top: 10px; }
    .company-profile .company-utilization > span { white-space: nowrap; overflow-wrap: normal; text-align: right; font-variant-numeric: tabular-nums; }
    .company-profile .company-license .status-pill { padding: 7px 14px; font-size: 13px; font-weight: 700; }
    .company-profile progress { appearance: none; width: 100%; min-width: 0; height: 10px; border: 0; border-radius: 99px; overflow: hidden; background: var(--border-soft); accent-color: var(--green); }
    .company-profile progress::-webkit-progress-bar { background: var(--border-soft); }
    .company-profile progress::-webkit-progress-value { background: var(--green); border-radius: 99px; }
    .company-profile progress::-moz-progress-bar { background: var(--green); border-radius: 99px; }
    .company-profile .company-edit { flex-shrink: 0; text-align: right; padding-top: 12px; }
    .company-profile .company-edit small { display: block; margin-top: 4px; color: var(--text-muted); font-size: 10px; line-height: 1.4; }
    @media (max-width: 1100px) {
        .company-profile .company-license { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 20px 0; }
        .company-profile .company-license > div { padding: 8px 16px; }
        .company-profile .company-license > div:nth-child(odd) { padding-left: 0; }
        .company-profile .company-license > div:nth-child(even) { border: 0; }
    }
    @media (max-width: 640px) {
        .company-profile .page-heading { flex-direction: column; align-items: stretch; }
        .company-profile .company-edit { text-align: left; padding-top: 0; }
        .company-profile .company-fields, .company-profile .company-license { grid-template-columns: 1fr; }
        .company-profile .company-fields > div { padding: 10px 0; }
        .company-profile .company-license > div { padding: 0; border: 0; }
    }
</style>

<div class="company-profile">
    <div class="page-heading">
        <div>
            <div class="page-eyebrow">Minha empresa</div>
            <div class="company-title">
                <h1>{{ $empresa->nome }}</h1>
                <span class="status-pill {{ $empresa->status === 'active' ? 'is-active' : 'is-inactive' }}">{{ $empresa->status === 'active' ? 'Ativa' : 'Inativa' }}</span>
            </div>
            <p class="company-contact">
                <span><strong>Documento</strong> {{ $empresa->documentoFormatado() ?? '—' }}</span>
                <span><strong>E-mail de contato</strong> {{ $empresa->email_contato ?? '—' }}</span>
            </p>
        </div>
        <div class="company-edit">
            <button type="button" class="button button-secondary" disabled aria-describedby="company-edit-help">Editar empresa</button>
            <small id="company-edit-help">Edição disponível para administradores.</small>
        </div>
    </div>

    <section class="panel" aria-labelledby="company-info-title">
        <div class="panel-header">
            <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M3 21h18M5 21V3h9v18M14 10h5v11M8 7h3M8 11h3M8 15h3M9 21v-3"/></svg>
            <div><h2 id="company-info-title">Informações da empresa</h2><p>Dados cadastrais e contato da sua empresa</p></div>
        </div>
        <dl class="company-fields">
            <div><dt>Razão social</dt><dd>{{ $empresa->nome }}</dd></div>
            <div><dt>Nome fantasia</dt><dd>—</dd></div>
            <div><dt>CNPJ</dt><dd>{{ $empresa->documentoFormatado() ?? '—' }}</dd></div>
            <div><dt>E-mail de contato</dt><dd>{{ $empresa->email_contato ?? '—' }}</dd></div>
            <div><dt>Telefone</dt><dd>—</dd></div>
        </dl>
    </section>

    <section class="panel" aria-labelledby="company-license-title">
        <div class="panel-header">
            <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><circle cx="12" cy="8" r="6"/><path d="m8 13-3 9 4-2 2 2 1-8 1 8 2-2 4 2-3-9"/></svg>
            <div><h2 id="company-license-title">Licença</h2><p>Informações da sua licença de uso</p></div>
        </div>
        @forelse ($empresa->licencas as $licenca)
            <dl class="company-license">
                <div><dt>Status da licença</dt><dd><span class="status-pill {{ $licenca->isValid() ? 'is-active' : 'is-inactive' }}">{{ $licenca->effectiveStatusLabel() }}</span></dd></div>
                <div><dt>Início</dt><dd>{{ $licenca->starts_at->format('d/m/Y') }}</dd></div>
                <div><dt>Expiração</dt><dd>{{ optional($licenca->expires_at)->format('d/m/Y') ?? 'Sem vencimento' }}
                    @if ($licenca->expirationSummary())<small>{{ $licenca->expirationSummary() }}</small>@endif
                </dd></div>
                <div><dt>Uso de servidores</dt><dd>
                    @if (! $licenca->isValid())
                        {{ $licenca->unavailableSummary() }}
                    @elseif ($capacidadeLicencaAtiva > 0)
                        @if ($servidoresUtilizados > $capacidadeLicencaAtiva)
                            Uso acima do limite: {{ $servidoresUtilizados.' de '.$capacidadeLicencaAtiva }}
                        @else
                            {{ $servidoresUtilizados.' de '.$capacidadeLicencaAtiva.' '.($capacidadeLicencaAtiva === 1 ? 'servidor utilizado' : 'servidores utilizados') }}
                        @endif
                        <div class="company-utilization">
                            <progress max="{{ $capacidadeLicencaAtiva }}" value="{{ min($servidoresUtilizados, $capacidadeLicencaAtiva) }}" aria-label="Utilização de servidores">{{ $servidoresUtilizados.' de '.$capacidadeLicencaAtiva }}</progress>
                            <span>{{ round($servidoresUtilizados / $capacidadeLicencaAtiva * 100) }}%</span>
                        </div>
                    @else
                        Sem capacidade de servidores disponível.
                    @endif
                </dd></div>
            </dl>
        @empty
            <div class="empty-state"><span>Sem licença ativa.</span></div>
        @endforelse
    </section>
</div>
