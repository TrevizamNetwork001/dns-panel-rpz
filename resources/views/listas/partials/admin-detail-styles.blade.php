<style>
    .source-detail, .source-history { max-width:1400px; margin:0 auto; }
    .source-breadcrumb { display:flex; flex-wrap:wrap; align-items:center; gap:9px; margin-bottom:20px; color:var(--text-muted); font-size:12px; }
    .source-breadcrumb a { color:var(--cyan); }
    .source-breadcrumb span { overflow-wrap:anywhere; }
    .source-detail-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:16px; align-items:start; }
    .page-content.admin-refined .source-detail-grid > .panel { margin:0; }
    .source-detail .source-wide { grid-column:1/-1; }
    .page-content.admin-refined .source-detail .details-list > div { grid-template-columns:minmax(100px,.65fr) minmax(0,1fr); gap:16px; padding:13px 0; }
    .page-content.admin-refined .source-detail .details-list dd { text-align:right; overflow-wrap:anywhere; }
    .page-content.admin-refined .source-detail .source-number { font-size:20px; color:var(--text); font-variant-numeric:tabular-nums; }
    .source-shortcuts { display:grid; gap:10px; }
    .source-shortcuts > a { display:flex; align-items:center; gap:12px; padding:15px; border:1px solid var(--border-soft); border-radius:10px; background:var(--surface-soft); }
    .source-shortcuts > a:hover { border-color:var(--cyan); }
    .source-shortcut-icon { display:grid; place-items:center; width:34px; height:34px; flex-shrink:0; color:var(--cyan); background:var(--surface); border-radius:9px; }
    .source-shortcuts > a > span:nth-child(2) { flex:1; min-width:0; }
    .source-shortcuts strong { display:block; font-size:13px; font-weight:500; color:var(--text); }
    .source-shortcuts small { display:block; font-size:12px; line-height:1.6; margin-top:5px; color:var(--text-muted); }
    .source-shortcuts > a > svg { color:var(--text-muted); flex-shrink:0; }
    .source-sync-fields { display:grid; grid-template-columns:minmax(180px,.45fr) minmax(0,1fr); gap:20px; }
    .source-sync-fields > div { min-width:0; }
    .source-sync-fields span { display:block; margin-bottom:7px; color:var(--text-muted); font-size:12px; }
    .source-sync-fields strong { display:block; font-size:14px; font-weight:500; color:var(--text); }
    .source-sync-fields time { display:block; margin-top:5px; font-size:12px; color:var(--text-muted); }
    .source-sync-fields code { display:block; padding:11px 13px; border:1px solid var(--border-soft); border-radius:8px; background:var(--surface-soft); user-select:all; font:13px/1.7 ui-monospace,SFMono-Regular,Consolas,monospace; color:var(--text-soft); overflow-wrap:anywhere; }
    .source-actions { display:flex; flex-wrap:wrap; gap:8px; margin-top:16px; }
    .source-actions form { margin:0; }
    .source-help { font-size:12px; line-height:1.8; color:var(--text-muted); margin:12px 0; }
    .source-period-filter { display:grid; gap:6px; }
    .source-period-filter label { font-size:12px; color:var(--text-soft); }
    .source-period-filter > div { display:flex; flex-wrap:wrap; gap:8px; }
    .page-content.admin-refined .source-period-filter select { width:175px; font-size:13px; height:38px; min-height:38px; }
    .page-content.admin-refined .source-history .metrics-grid { grid-template-columns:repeat(3,minmax(0,1fr)); }
    .page-content.admin-refined .source-history .metric-card { display:grid; grid-template-columns:minmax(0,1fr) auto; gap:5px 12px; align-content:center; min-height:118px; }
    .source-history .metric-card-header { grid-column:2; grid-row:1/3; align-self:center; }
    .page-content.admin-refined .source-history .metric-value { grid-column:1; margin:0; font-size:30px; }
    .source-history .metric-label { grid-column:1; }
    .source-mobile-hint { display:none; }
    .source-chart-scroll { overflow-x:auto; scrollbar-width:none; }
    .source-chart-scroll::-webkit-scrollbar { display:none; }
    .source-history-columns { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:16px; margin-top:16px; align-items:start; }
    .page-content.admin-refined .source-history-columns > .panel { margin:0; }
    .source-history-events { display:grid; gap:8px; max-height:460px; overflow-y:auto; scrollbar-width:none; list-style:none; padding:2px; margin:0; }
    .source-history-events::-webkit-scrollbar { display:none; }
    .source-history-events li { display:flex; align-items:flex-start; gap:12px; padding:13px; border:1px solid var(--border-soft); border-radius:9px; background:var(--surface-soft); }
    .source-history-events li > div { min-width:0; }
    .source-history-events strong { display:block; font-size:13px; font-weight:500; overflow-wrap:anywhere; color:var(--text); }
    .source-history-events time { display:block; margin-top:6px; color:var(--text-muted); font-size:11px; line-height:1.6; font-variant-numeric:tabular-nums; }
    .source-event-icon { display:grid; place-items:center; width:27px; height:27px; flex-shrink:0; border-radius:7px; color:var(--green); background:color-mix(in srgb,var(--green) 8%,transparent); }
    .is-removed .source-event-icon { color:var(--amber); background:color-mix(in srgb,var(--amber) 8%,transparent); }
    .page-content.admin-refined .source-history .empty-state { min-height:130px; }
    @media(max-width:1050px) { .source-detail-grid, .source-history-columns { grid-template-columns:minmax(0,1fr); } }
    @media(max-width:600px) {
        .source-sync-fields { grid-template-columns:minmax(0,1fr); gap:16px; }
        .source-mobile-hint { display:block; margin:10px 0; font-size:11px; color:var(--text-muted); }
        .source-chart-scroll svg { min-width:700px; }
        .page-content.admin-refined .source-history .metrics-grid { grid-template-columns:minmax(0,1fr); gap:10px; }
        .page-content.admin-refined .source-history .metric-card { min-height:95px; }
        .page-content.admin-refined .source-history .metric-value { font-size:26px; }
        .source-period-filter { width:100%; }
        .source-period-filter > div { flex-wrap:nowrap; }
        .page-content.admin-refined .source-period-filter select { width:auto; flex:1; }
    }
</style>
