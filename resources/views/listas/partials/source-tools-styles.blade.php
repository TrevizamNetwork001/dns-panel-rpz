<style>
    .source-tools { max-width:1400px; margin:0 auto; }
    .source-preview-grid { display:grid; grid-template-columns:minmax(260px,320px) minmax(0,1fr); align-items:start; gap:16px; }
    .source-preview-content, .source-exclusion-sidebar { display:grid; gap:16px; min-width:0; }
    .page-content.admin-refined .source-tools .panel { margin:0; padding:22px; }
    .page-content.admin-refined .source-tools .panel-header h2 { font-size:15px; }
    .page-content.admin-refined .source-tools .panel-header p { font-size:12px; margin:5px 0 0; }
    .source-tools .source-tool-caption { color:var(--text-muted); font-size:11px; line-height:1.6; }
    .page-content.admin-refined .source-tools label .source-tool-caption { color:var(--text-muted); font-weight:400; }
    .source-preview-total { display:grid; gap:6px; margin:6px 0 18px; }
    .source-preview-total strong { font-size:32px; font-weight:600; letter-spacing:-.035em; font-variant-numeric:tabular-nums; }
    .source-preview-total span { color:var(--text-soft); font-size:12px; }
    .page-content.admin-refined .source-tools .details-list > div { grid-template-columns:minmax(0,1fr) minmax(0,1fr); gap:12px; padding:10px 0; }
    .page-content.admin-refined .source-tools .details-list dt, .page-content.admin-refined .source-tools .details-list dd { font-size:12px; }
    .source-tools .details-list dd { font-variant-numeric:tabular-nums; }
    .source-tool-details { border-top:1px solid var(--border-soft); margin-top:14px; padding-top:14px; }
    .source-tool-details summary, .source-legacy-import > summary { cursor:pointer; color:var(--text-soft); font-size:12px; line-height:1.7; }
    .source-validation { display:grid; gap:8px; list-style:none; margin:18px 0 0; padding:14px 0 0; border-top:1px solid var(--border-soft); }
    .source-validation li { display:flex; align-items:flex-start; gap:7px; color:var(--text-soft); font-size:11px; line-height:1.7; }
    .source-validation svg { color:var(--cyan); flex-shrink:0; margin-top:2px; }
    .source-domain-search form { display:grid; gap:8px; }
    .source-domain-search label { color:var(--text-soft); font-size:12px; }
    .source-domain-search form > div { display:flex; flex-wrap:wrap; gap:8px; }
    .page-content.admin-refined .source-domain-search input { flex:1; width:auto; min-width:150px; font-size:14px; }
    .source-search-result { margin-top:16px; padding:14px; background:var(--surface-soft); border:1px solid var(--border-soft); border-radius:9px; overflow-wrap:anywhere; }
    .source-search-result strong { font-size:13px; color:var(--text); }
    .source-search-result p { font-size:12px; color:var(--text-soft); line-height:1.7; margin:8px 0 0; }
    .source-search-result code { display:block; margin-top:10px; color:var(--cyan); font-size:12px; overflow-wrap:anywhere; }
    .source-readonly-badge { display:inline-flex; align-items:center; padding:4px 8px; border:1px solid currentColor; border-radius:5px; color:#67d8ef; font-size:11px; line-height:1.5; }
    html[data-theme="light"] .source-readonly-badge { color:#08758b; }
    .source-format { display:flex; flex-wrap:wrap; gap:7px; margin:0 0 12px; }
    .source-format span { font-size:11px; padding:4px 8px; border:1px solid currentColor; border-radius:5px; background:transparent; }
    .source-format span:nth-child(1) { color:#67d8ef; }
    .source-format span:nth-child(2) { color:#c4b5fd; }
    .source-format span:nth-child(3) { color:#fcd34d; }
    html[data-theme="light"] .source-format span:nth-child(1) { color:#08758b; }
    html[data-theme="light"] .source-format span:nth-child(2) { color:#6d28d9; }
    html[data-theme="light"] .source-format span:nth-child(3) { color:#946000; }
    .page-content.admin-refined .source-tools .rpz-code { min-height:320px; height:clamp(320px,60vh,620px); padding:18px; border:1px solid var(--border); border-radius:9px; background:var(--surface-soft); color:var(--text-soft); font:12px/1.8 ui-monospace,SFMono-Regular,Consolas,monospace; scrollbar-width:none; }
    .source-tools .rpz-code::-webkit-scrollbar { display:none; }
    .source-small-count { margin-left:7px; padding:3px 7px; border-radius:5px; background:var(--cyan-soft); color:var(--cyan); font-size:11px; }
    .source-import-list, .source-exclusion-list { display:grid; gap:12px; padding:0; margin:0; list-style:none; }
    .source-import-card, .source-exclusion-card { min-width:0; padding:18px; border:1px solid var(--border-soft); border-radius:10px; background:var(--surface-soft); }
    .source-import-top { display:flex; align-items:center; flex-wrap:wrap; gap:12px; }
    .source-import-icon { display:grid; place-items:center; width:36px; height:36px; flex-shrink:0; border-radius:9px; background:var(--surface); color:var(--cyan); }
    .source-import-title { flex:1; min-width:130px; }
    .source-import-title strong { display:block; color:var(--text); font-size:14px; font-weight:500; line-height:1.6; overflow-wrap:anywhere; }
    .source-import-title time { display:block; color:var(--text-muted); font-size:11px; line-height:1.6; margin-top:3px; }
    .source-import-counts { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:12px; padding:14px 0 0; margin:14px 0 0; border-top:1px solid var(--border-soft); }
    .source-import-counts dt { color:var(--text-muted); font-size:11px; }
    .source-import-counts dd { margin:6px 0 0; font-size:16px; color:var(--text); font-variant-numeric:tabular-nums; }
    .source-import-actions { display:flex; justify-content:flex-end; margin-top:12px; }
    .source-exclusions-grid { display:grid; grid-template-columns:minmax(260px,330px) minmax(0,1fr); align-items:start; gap:16px; }
    .source-exclusion-form { display:grid; gap:14px; }
    .source-exclusion-form .field-group { margin:0; }
    .page-content.admin-refined .source-tools .source-exclusion-form .form-control { font-size:14px; }
    .page-content.admin-refined .source-tools .source-short-select { width:auto; max-width:100%; min-width:150px; align-self:flex-start; height:38px; min-height:38px; }
    .source-exclusion-form .source-help, .source-exclusion-form .source-actions { margin:0; }
    .source-field-error { color:var(--danger); font-size:12px; }
    .source-filter-tabs { display:flex; flex-wrap:wrap; gap:8px; margin-bottom:18px; }
    .source-filter-tab { display:inline-flex; align-items:center; padding:7px 10px; border:1px solid transparent; border-radius:7px; color:var(--text-muted); font-size:12px; }
    .source-filter-tab.is-selected { color:var(--cyan); background:var(--cyan-soft); }
    .source-filter-tab:hover { color:var(--text); border-color:var(--border); }
    .source-exclusion-top { display:flex; flex-wrap:wrap; justify-content:flex-start; align-items:center; gap:10px; margin-bottom:10px; }
    .source-exclusion-value { display:block; padding:13px 14px; border:1px solid var(--border-soft); border-radius:8px; background:var(--surface); overflow-wrap:anywhere; color:var(--text); font:500 13px/1.7 ui-monospace,SFMono-Regular,Consolas,monospace; }
    .source-exclusion-notes { margin:6px 0 0; color:var(--text-muted); font-size:12px; line-height:1.7; overflow-wrap:anywhere; }
    .source-exclusion-controls { display:grid; grid-template-columns:auto auto auto; justify-content:start; align-items:start; gap:8px; margin-top:16px; padding-top:14px; border-top:1px solid var(--border-soft); }
    .source-exclusion-controls form { margin:0; }
    .source-text-action, .source-exclusion-edit > summary { padding:4px 0; border:0; background:none; font:inherit; font-size:12px; color:var(--text-soft); cursor:pointer; line-height:1.6; }
    .source-text-action.is-danger { color:var(--danger); }
    .source-text-action:hover, .source-exclusion-edit > summary:hover { color:var(--cyan); }
    .source-exclusion-card { --ex-edit:#67d8ef; --ex-warning:#fcd34d; --ex-success:#6ee7b7; --ex-danger:#fda4af; }
    html[data-theme="light"] .source-exclusion-card { --ex-edit:#08758b; --ex-warning:#946000; --ex-success:#167047; --ex-danger:#b4233d; }
    .source-exclusion-type,.source-exclusion-top .status-pill { display:inline-flex; align-items:center; padding:4px 8px; font-size:11px; line-height:1.5; border:1px solid currentColor; border-radius:5px; background:transparent; }
    .source-exclusion-type { color:var(--ex-edit); }
    .source-exclusion-top .status-pill.is-active { color:var(--ex-success); }
    .source-exclusion-top .status-pill.is-muted { color:var(--text-muted); }
    .source-exclusion-controls .source-text-action,.source-exclusion-controls .source-exclusion-edit > summary { display:inline-flex; align-items:center; justify-content:center; min-height:32px; padding:5px 10px; border:1px solid var(--border); border-radius:6px; color:var(--ex-edit); font-size:12px; line-height:1.5; background:var(--surface); }
    .source-exclusion-controls .source-exclusion-edit > summary { list-style:none; }
    .source-exclusion-controls .source-exclusion-edit > summary::-webkit-details-marker { display:none; }
    .source-exclusion-controls .source-text-action.is-toggle-off { color:var(--ex-warning); }
    .source-exclusion-controls .source-text-action.is-toggle-on { color:var(--ex-success); }
    .source-exclusion-controls .source-text-action.is-danger { color:var(--ex-danger); }
    .source-exclusion-controls .source-text-action:hover,.source-exclusion-controls .source-exclusion-edit > summary:hover { border-color:currentColor; }
    .source-exclusion-edit[open] { grid-column:1/-1; width:100%; }
    .source-exclusion-edit .source-exclusion-form { padding-top:14px; }
    @media(max-width:1100px) { .source-preview-grid, .source-exclusions-grid { grid-template-columns:minmax(0,1fr); } }
    @media(max-width:600px) {
        .page-content.admin-refined .source-tools .panel { padding:16px; }
        .source-import-card, .source-exclusion-card { padding:14px; }
        .source-import-counts { grid-template-columns:repeat(2,minmax(0,1fr)); gap:14px; }
        .source-import-top { display:grid; grid-template-columns:36px minmax(0,1fr); }
        .source-import-title { min-width:0; }
        .source-import-top > .status-pill { grid-column:2; justify-self:start; margin-left:0; }
        .source-import-actions { justify-content:flex-start; }
        .source-domain-search form > div { align-items:stretch; }
        .page-content.admin-refined .source-domain-search input { flex-basis:100%; }
    }
    .anatel-central { display:grid; gap:18px; }
    .anatel-central .page-heading { margin-bottom:0; }
    .anatel-central-grid { display:grid; grid-template-columns:minmax(0,1.2fr) minmax(0,1fr); gap:18px; align-items:start; }
    .anatel-file-field { padding:16px; border:1px dashed var(--border); border-radius:9px; min-width:0; }
    .anatel-file-field label { display:block; margin-bottom:10px; }
    .page-content.admin-refined .anatel-file-field input { width:100%; min-width:0; font-size:12px; }
    .page-content.admin-refined .anatel-file-field input[type="file"] { padding:0; border:0; border-radius:7px; background:transparent; color:var(--text-muted); cursor:pointer; line-height:1.7; }
    .anatel-file-field input::file-selector-button { margin-right:12px; padding:9px 13px; border:1px solid var(--cyan); border-radius:7px; background:var(--cyan-soft); color:var(--cyan); font-family:inherit; font-size:12px; font-weight:500; line-height:1.5; cursor:pointer; }
    .anatel-file-field input::file-selector-button:hover { background:color-mix(in srgb,var(--cyan) 20%,var(--surface)); }
    .anatel-file-field input:focus-visible { outline:2px solid var(--cyan); outline-offset:4px; }
    .anatel-file-field .source-help { margin-top:10px; }
    .anatel-pending-list { display:grid; gap:12px; }
    .anatel-pending-item { display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:14px; padding:14px; border:1px solid var(--border-soft); border-radius:9px; }
    .anatel-pending-item strong { font-size:13px; overflow-wrap:anywhere; }
    .anatel-pending-item p,.anatel-clear p { font-size:12px; color:var(--text-muted); margin:7px 0 0; }
    .anatel-clear { display:flex; align-items:flex-start; gap:14px; padding:18px; border:1px solid var(--border-soft); border-radius:10px; background:var(--surface-soft); }
    .anatel-clear-icon { display:grid; place-items:center; flex:0 0 40px; height:40px; border:1px solid currentColor; border-radius:50%; color:#6ee7b7; background:transparent; }
    html[data-theme="light"] .anatel-clear-icon { color:#167047; }
    .anatel-clear h3 { margin:0; font-size:15px; font-weight:500; color:var(--text); line-height:1.6; }
    .anatel-clear p { line-height:1.8; max-width:38ch; }
    .anatel-import-bottom { display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:14px; margin-top:14px; padding-top:14px; border-top:1px solid var(--border-soft); }
    .anatel-import-numbers,.anatel-import-actions { display:flex; flex-wrap:wrap; align-items:center; gap:16px; }
    .anatel-import-numbers { font-size:12px; color:var(--text-muted); }
    .anatel-import-numbers strong { font-size:14px; font-weight:500; color:var(--text); font-variant-numeric:tabular-nums; }
    .anatel-import-actions form { margin:0; }
    .anatel-progress-track { height:6px; border-radius:6px; overflow:hidden; background:var(--surface-soft); }
    #progress-bar { height:100%; width:0; background:var(--cyan); }
    #progress-label { font-size:13px; color:var(--text-muted); }
    #progress-result { font-size:13px; line-height:1.8; overflow-wrap:anywhere; }
    @media(max-width:900px) { .anatel-central-grid { grid-template-columns:minmax(0,1fr); } }
    .anatel-review { display:grid; gap:18px; }
    .anatel-review .page-heading,.anatel-review .source-breadcrumb { margin-bottom:0; }
    .anatel-review .page-heading h1 { overflow-wrap:anywhere; }
    .anatel-review-counts { display:grid; grid-template-columns:repeat(6,minmax(0,1fr)); gap:16px; padding-top:18px; margin:18px 0 0; border-top:1px solid var(--border-soft); }
    .anatel-review-counts dt { font-size:11px; color:var(--text-muted); }
    .anatel-review-counts dd { font-size:22px; color:var(--text); margin:7px 0 0; font-variant-numeric:tabular-nums; }
    .anatel-review-decision { display:grid; gap:14px; padding-top:18px; margin-top:18px; border-top:1px solid var(--border-soft); }
    .anatel-review-decision p { margin:0; color:var(--text-soft); font-size:12px; line-height:1.8; }
    .anatel-review-actions { display:flex; flex-wrap:wrap; gap:8px; align-items:center; }
    .anatel-review-actions form,.anatel-review-options form { margin:0; }
    .anatel-review-options { margin-top:16px; }
    .anatel-review-options summary { width:fit-content; cursor:pointer; font-size:12px; color:var(--text-muted); }
    .anatel-review-options form { margin-top:10px; }
    .anatel-domain-list { list-style:none; padding:0; margin:0; border:1px solid var(--border-soft); border-radius:9px; overflow:hidden; }
    .anatel-domain-list li { display:flex; align-items:center; justify-content:space-between; gap:14px; padding:13px 14px; border-bottom:1px solid var(--border-soft); }
    .anatel-domain-list li:last-child { border:0; }
    .anatel-domain-list code { min-width:0; overflow-wrap:anywhere; font:12px/1.8 ui-monospace,SFMono-Regular,Consolas,monospace; color:var(--text); }
    .anatel-domain-list li > div { display:flex; flex-wrap:wrap; align-items:center; justify-content:flex-end; gap:8px; flex-shrink:0; }
    .anatel-result-badge { display:inline-flex; padding:3px 7px; border:1px solid currentColor; border-radius:5px; font-size:11px; color:var(--text-muted); }
    .anatel-result-new { color:#6ee7b7; }
    .anatel-result-reactivated { color:#c4b5fd; }
    .anatel-result-excluded { color:#fcd34d; }
    html[data-theme="light"] .anatel-result-new { color:#167047; }
    html[data-theme="light"] .anatel-result-reactivated { color:#6d28d9; }
    html[data-theme="light"] .anatel-result-excluded { color:#946000; }
    .anatel-domain-empty { font-size:12px; color:var(--text-muted); }
    .anatel-progress-item { margin-top:14px; padding:14px; border:1px solid var(--border-soft); border-radius:9px; background:var(--surface-soft); }
    .anatel-progress-item strong { font-size:13px; font-weight:500; }
    .anatel-progress-item p { margin:8px 0; font-size:12px; color:var(--text-muted); }
    .anatel-progress-item .status-pill { margin-left:8px; }
    @media(max-width:900px) { .anatel-review-counts { grid-template-columns:repeat(3,minmax(0,1fr)); } }
    @media(max-width:600px) {
        .anatel-review-counts { grid-template-columns:repeat(2,minmax(0,1fr)); }
        .anatel-domain-list li { align-items:flex-start; flex-direction:column; gap:8px; }
        .anatel-domain-list li > div { justify-content:flex-start; }
        .anatel-review .source-import-top > .button { grid-column:2; justify-self:start; }
    }
</style>
