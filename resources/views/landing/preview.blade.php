<figure class="dashboard-preview" aria-labelledby="preview-caption">
    <div class="preview-shell">
        <div class="preview-sidebar" aria-hidden="true">
            <div class="brand">@include('landing.mark') <span><strong>DNS</strong> Center</span></div>
            @foreach ([['activity', 'Dashboard'], ['server', 'DNS Autoritativo'], ['refresh', 'DNS Recursivo'], ['shield', 'Políticas RPZ'], ['list', 'Relatórios'], ['settings', 'Configurações']] as [$icon, $label])
                <span class="preview-nav {{ $loop->first ? 'active' : '' }}"><x-ui-icon :name="$icon" size="14" /> {{ $label }}</span>
            @endforeach
        </div>
        <div class="preview-content">
            <div class="preview-heading"><strong>Dashboard</strong><span id="preview-caption">Prévia ilustrativa</span></div>
            <div class="preview-modules">
                @foreach ($modules as $module)
                    <div>
                        <span class="module-icon tone-{{ $module['tone'] }}"><x-ui-icon :name="$module['icon']" size="16" /></span>
                        <strong>{{ $module['name'] }}</strong>
                    </div>
                @endforeach
            </div>
            <div class="preview-chart">
                <strong>Consultas DNS</strong>
                <svg viewBox="0 0 460 104" preserveAspectRatio="none" role="img" aria-label="Curva ilustrativa de consultas DNS, sem dados reais">
                    <defs><linearGradient id="chart-fill" x2="0" y2="1" gradientUnits="objectBoundingBox"><stop stop-color="#348aff" stop-opacity=".32"/><stop offset="1" stop-color="#348aff" stop-opacity="0"/></linearGradient></defs>
                    <path d="M0 12h460M0 36h460M0 60h460M0 84h460" stroke="#e3ebfa"/>
                    <path d="M0 77C29 77 31 78 49 60S79 64 100 58s32-32 53-29 27 22 50 16 31-35 50-33 27 34 48 32 30-25 52-25 27 20 47 14 31-26 48-24l12 9v86H0Z" fill="url(#chart-fill)"/>
                    <path d="M0 77C29 77 31 78 49 60S79 64 100 58s32-32 53-29 27 22 50 16 31-35 50-33 27 34 48 32 30-25 52-25 27 20 47 14 31-26 48-24l12 9" fill="none" stroke="#1267ee" stroke-width="2.5" vector-effect="non-scaling-stroke"/>
                </svg>
                <div class="preview-indicators"><span><x-ui-icon name="activity" size="13" /> Operação DNS</span><span><x-ui-icon name="shield" size="13" /> Proteção RPZ</span></div>
            </div>
        </div>
    </div>
</figure>
