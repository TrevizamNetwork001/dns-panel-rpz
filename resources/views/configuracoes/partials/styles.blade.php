<style>
    .admin-settings { max-width:1400px; margin:0 auto; display:grid; grid-template-columns:190px minmax(0,1fr); gap:20px; align-items:start; }
    .admin-settings [hidden] { display:none!important; }
    .admin-settings > .page-heading { grid-column:1/-1; margin-bottom:0; }
    .admin-settings .settings-tabs { display:grid; gap:5px; padding:10px; border:1px solid var(--border-soft); border-radius:12px; background:var(--surface); min-width:0; }
    .admin-settings .settings-tab { display:flex; align-items:center; gap:9px; text-align:left; padding:11px 12px; border:1px solid transparent; border-radius:7px; background:transparent; color:var(--text-muted); font:inherit; font-size:13px; cursor:pointer; }
    .admin-settings .settings-tab:hover { background:var(--surface-soft); color:var(--text); }
    .admin-settings .settings-tab.is-active { color:var(--cyan); background:var(--cyan-soft); border-color:color-mix(in srgb,var(--cyan) 25%,transparent); }
    .admin-settings :focus-visible { outline:2px solid var(--cyan); outline-offset:3px; }
    .admin-settings [data-settings-panel] { grid-column:2; grid-row:2; min-width:0; display:grid; gap:18px; }
    .admin-settings .settings-section { padding:22px; border:1px solid var(--border-soft); border-radius:12px; background:var(--surface); min-width:0; }
    .admin-settings .settings-heading { display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:12px; padding-bottom:16px; margin-bottom:18px; border-bottom:1px solid var(--border-soft); }
    .admin-settings h2 { font-size:15px; margin:0; font-weight:500; }
    .admin-settings .settings-heading p { font-size:12px; color:var(--text-muted); margin:6px 0 0; line-height:1.7; }
    .admin-settings .settings-status { display:inline-flex; align-items:center; padding:4px 8px; border:1px solid currentColor; border-radius:5px; color:var(--text-muted); font-size:11px; }
    .admin-settings .settings-status.is-enabled { color:#6ee7b7; }
    html[data-theme="light"] .admin-settings .settings-status.is-enabled { color:#167047; }
    .admin-settings .settings-inventory { margin:0; }
    .admin-settings .settings-inventory > div { display:grid; grid-template-columns:minmax(150px,.7fr) minmax(0,1fr); gap:16px; padding:13px 0; border-bottom:1px solid var(--border-soft); font-size:13px; line-height:1.8; }
    .admin-settings .settings-inventory > div:first-child { padding-top:0; }
    .admin-settings .settings-inventory > div:last-child { border:0; }
    .admin-settings dt { color:var(--text-muted); }
    .admin-settings dd { margin:0; color:var(--text); overflow-wrap:anywhere; }
    .admin-settings .settings-mono,.admin-settings code { font-family:ui-monospace,SFMono-Regular,Consolas,monospace; font-size:12px; overflow-wrap:anywhere; }
    .admin-settings .settings-note { display:block; color:var(--text-muted); font-size:12px; line-height:1.8; margin:10px 0 0; }
    .admin-settings .settings-copy { color:var(--text-soft); font-size:13px; line-height:1.9; margin:0; max-width:78ch; }
    .admin-settings .settings-steps { display:grid; gap:10px; padding-left:20px; margin:14px 0; color:var(--text-soft); font-size:13px; line-height:1.8; }
    .admin-settings .settings-callout { padding:13px 15px; margin:14px 0 0; border:1px solid var(--border-soft); border-radius:8px; background:var(--surface-soft); color:var(--text-soft); font-size:12px; line-height:1.8; }
    .admin-settings .settings-links { list-style:none; display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:10px; margin:0; padding:0; }
    .admin-settings .settings-links a { display:block; padding:15px; border:1px solid var(--border-soft); border-radius:9px; background:var(--surface-soft); height:100%; }
    .admin-settings .settings-links strong { display:block; font-size:13px; font-weight:500; }
    .admin-settings .settings-links span { display:block; font-size:12px; line-height:1.8; color:var(--text-muted); margin-top:6px; }
    .admin-settings .settings-links a:hover,.admin-settings .settings-shortcut:hover { border-color:var(--cyan); color:var(--cyan); }
    .admin-settings .settings-form { display:grid; gap:16px; margin-top:18px; }
    .admin-settings .settings-form .field-group { min-width:0; margin:0; }
    .admin-settings .settings-form .form-control { width:100%; min-height:42px; padding:10px 12px; border-radius:8px; font-size:14px; }
    .admin-settings .settings-form label { font-size:13px; }
    .admin-settings .settings-check { display:flex; justify-content:space-between; align-items:center; gap:16px; padding:14px; border:1px solid var(--border-soft); border-radius:9px; background:var(--surface-soft); cursor:pointer; }
    .admin-settings .settings-check strong { display:block; font-size:13px; font-weight:500; }
    .admin-settings .settings-check small { display:block; color:var(--text-muted); font-size:12px; line-height:1.7; margin-top:5px; }
    .admin-settings .settings-check input { width:18px; height:18px; flex-shrink:0; accent-color:var(--cyan); }
    .admin-settings .settings-fields { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:16px; }
    .admin-settings .settings-actions { display:flex; flex-wrap:wrap; gap:8px; align-items:center; margin:20px 0 0; padding-top:18px; border-top:1px solid var(--border-soft); }
    .admin-settings .settings-actions form { margin:0; }
    .admin-settings .settings-actions .button { box-shadow:none; }
    .admin-settings .settings-help { margin-top:18px; padding:14px; border:1px solid var(--border-soft); border-radius:8px; color:var(--text-soft); font-size:12px; line-height:1.8; background:var(--surface-soft); }
    .admin-settings .settings-help summary { cursor:pointer; color:var(--text); }
    .admin-settings .settings-help h3 { font-size:13px; font-weight:500; margin:0 0 12px; }
    .admin-settings .settings-help p { margin:10px 0 0; overflow-wrap:anywhere; }
    .admin-settings .settings-shortcut { display:inline-flex; align-items:center; gap:8px; padding:9px 12px; margin-top:14px; border:1px solid var(--border); border-radius:7px; color:var(--cyan); font-size:12px; }
    .admin-settings .settings-summary { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:12px; margin:0; }
    .admin-settings .settings-summary > div { display:flex; flex-direction:column; gap:8px; padding:18px; border:1px solid var(--border-soft); border-radius:10px; background:var(--surface); }
    .admin-settings .settings-summary dt { font-size:12px; }
    .admin-settings .settings-summary dd:not(.settings-summary-note) { order:-1; font-size:28px; font-variant-numeric:tabular-nums; }
    .admin-settings .settings-summary-note { color:var(--text-muted); font-size:11px; line-height:1.7; }
    @media(max-width:1050px) { .admin-settings { grid-template-columns:minmax(0,1fr); } .admin-settings .settings-tabs { display:flex; flex-wrap:wrap; gap:5px; } .admin-settings [data-settings-panel] { grid-column:1; grid-row:3; } }
    @media(max-width:650px) { .admin-settings .settings-section { padding:16px; } .admin-settings .settings-fields,.admin-settings .settings-links,.admin-settings .settings-summary { grid-template-columns:minmax(0,1fr); } .admin-settings .settings-inventory > div { grid-template-columns:minmax(0,1fr); gap:4px; } .admin-settings .settings-tab { padding:9px; font-size:12px; } .admin-settings .settings-tabs { padding:7px; } }
</style>
