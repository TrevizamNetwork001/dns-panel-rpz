<style>
    .page-content.admin-refined .admin-security { max-width: 1400px; margin: 0 auto; }
    .page-content.admin-refined .admin-security .security-heading { margin-bottom: 14px; }
    .admin-security .security-navigation { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 22px; }
    .admin-security .security-navigation a { display: inline-flex; align-items: center; gap: 7px; padding: 8px 12px; border: 1px solid var(--border-soft); border-radius: 8px; color: var(--text-soft); background: var(--surface); font-size: 12px; }
    .admin-security .security-navigation a:hover { color: var(--cyan); border-color: var(--cyan); }
    .page-content.admin-refined .admin-security .metric-card { display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 6px 12px; min-height: 124px; align-content: center; padding: 20px; }
    .page-content.admin-refined .admin-security .metric-card-header { grid-column: 2; grid-row: 1 / 3; align-self: center; }
    .page-content.admin-refined .admin-security .metric-value { grid-column: 1; margin: 0; font-size: 30px; }
    .page-content.admin-refined .admin-security .metric-label { grid-column: 1; font-size: 11px; line-height: 1.6; }
    .admin-security .security-source { display: flex; align-items: center; gap: 12px; padding: 14px 16px; border: 1px solid var(--border-soft); border-radius: 10px; background: var(--surface); margin: 0 0 12px; }
    .admin-security .security-source > svg { flex-shrink: 0; color: var(--amber); }
    .admin-security .security-source.is-confirmed > svg { color: var(--green); }
    .admin-security .security-source strong { display: block; font-size: 12px; color: var(--text); font-weight: 500; line-height: 1.6; }
    .admin-security .security-source span { display: block; font-size: 11px; color: var(--text-muted); line-height: 1.6; }
    .admin-security .security-note { margin: 0 0 22px; border: 1px solid var(--border-soft); border-radius: 10px; background: var(--surface); }
    .admin-security .security-note summary { display: flex; justify-content: space-between; align-items: center; gap: 12px; padding: 13px 16px; cursor: pointer; font-size: 12px; color: var(--text-soft); list-style: none; }
    .admin-security .security-note summary::-webkit-details-marker { display: none; }
    .admin-security .security-note summary > span { display: inline-flex; align-items: center; gap: 9px; }
    .admin-security .security-note summary > svg { transition: transform .15s; }
    .admin-security .security-note[open] summary > svg { transform: rotate(90deg); }
    .admin-security .security-note-content { display: flex; align-items: flex-start; gap: 12px; padding: 0 16px 16px; font-size: 12px; color: var(--text-soft); line-height: 1.8; }
    .admin-security .security-note-content > svg { flex: 0 0 16px; margin-top: 3px; color: var(--cyan); }
    .admin-security .security-note-content strong { color: var(--text); font-weight: 500; }
    .admin-security .security-note-content p { margin: 6px 0 0; }
    .admin-security .security-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; align-items: start; }
    .page-content.admin-refined .admin-security .security-grid > .panel { margin: 0; padding: 20px; }
    .admin-security .security-history { grid-column: 1 / -1; }
    .page-content.admin-refined .admin-security .panel-header { flex-wrap: wrap; gap: 12px; padding-bottom: 16px; margin-bottom: 16px; align-items: center; }
    .admin-security .security-section-title { display: flex; align-items: center; gap: 10px; min-width: 0; }
    .admin-security .security-section-icon { display: grid; place-items: center; flex: 0 0 36px; height: 36px; border: 1px solid var(--border-soft); border-radius: 10px; color: var(--cyan); background: var(--cyan-soft); }
    .admin-security .security-section-icon.is-danger { color: var(--danger); background: color-mix(in srgb, var(--danger) 8%, transparent); }
    .admin-security .security-section-icon.is-warning { color: var(--amber); background: color-mix(in srgb, var(--amber) 8%, transparent); }
    .page-content.admin-refined .admin-security .security-section-title h2 { margin: 0; font-size: 14px; line-height: 1.5; scroll-margin-top: 95px; }
    .page-content.admin-refined .admin-security .security-section-title p { margin: 4px 0 0; font-size: 11px; color: var(--text-muted); }
    .admin-security .security-status { color: var(--text-muted); font-size: 10px; padding: 4px 8px; background: var(--surface-soft); border: 1px solid var(--border-soft); border-radius: 6px; }
    .admin-security .security-recorded-note, .admin-security .security-origin-note { display: block; margin: 0 0 14px; font-size: 11px; line-height: 1.7; color: var(--text-muted); }
    .admin-security .security-recorded-note { color: var(--amber); }
    .admin-security .security-empty { min-height: 190px; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 8px; padding: 24px; text-align: center; }
    .admin-security .security-empty-icon { display: grid; place-items: center; width: 50px; height: 50px; margin-bottom: 6px; border: 1px solid var(--border-soft); border-radius: 14px; color: var(--text-muted); background: var(--surface-soft); }
    .admin-security .security-empty strong { font-size: 13px; font-weight: 500; color: var(--text-soft); }
    .admin-security .security-empty > span:last-child { color: var(--text-muted); font-size: 11px; line-height: 1.7; }
    .page-content.admin-refined .admin-security .security-stream { display: grid; gap: 9px; list-style: none; margin: 0; padding: 2px 5px 2px 2px; max-height: 480px; overflow-y: auto; scrollbar-width: thin; scrollbar-color: var(--border) transparent; }
    .page-content.admin-refined .admin-security .security-history .security-stream { max-height: 560px; }
    .page-content.admin-refined .admin-security .security-event { --security-marker: var(--text-muted); position: relative; padding: 15px 16px; border: 1px solid var(--border-soft); border-radius: 9px; background: var(--surface-soft); overflow-wrap: anywhere; }
    .page-content.admin-refined .admin-security .security-event::before { content: ''; position: absolute; top: 14px; bottom: 14px; left: -1px; width: 3px; border-radius: 3px; background: var(--security-marker); }
    .page-content.admin-refined .admin-security .security-event-danger { --security-marker: var(--danger); }
    .page-content.admin-refined .admin-security .security-event-warning { --security-marker: var(--amber); }
    .page-content.admin-refined .admin-security .security-event-success { --security-marker: var(--green); }
    .admin-security .security-mono { font-family: ui-monospace, SFMono-Regular, Consolas, monospace; font-variant-numeric: tabular-nums; }
    .admin-security .security-date { display: block; color: var(--text-muted); font-size: 10px; line-height: 1.7; margin-bottom: 7px; }
    .admin-security .security-action { display: block; color: var(--text); font-size: 12px; line-height: 1.6; font-weight: 500; }
    .admin-security .security-meta { margin-top: 6px; color: var(--text-soft); font-size: 11px; line-height: 1.7; }
    .admin-security .security-event-top { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 8px; }
    .admin-security .security-badge { display: inline-flex; align-items: center; gap: 5px; padding: 2px 7px; border: 1px solid color-mix(in srgb, var(--security-marker) 25%, transparent); border-radius: 5px; color: var(--security-marker); background: color-mix(in srgb, var(--security-marker) 7%, transparent); font-size: 10px; line-height: 1.5; }
    .admin-security .security-badge::before { content: ''; width: 4px; height: 4px; border-radius: 50%; background: currentColor; }
    .page-content.admin-refined .admin-security .security-history .security-event { display: grid; grid-template-columns: 155px minmax(0, 1fr); align-items: center; gap: 18px; }
    .admin-security .security-history .security-date { margin: 0; }
    @media (max-width: 1100px) {
        .admin-security .security-grid { grid-template-columns: minmax(0, 1fr); }
    }
    @media (max-width: 600px) {
        .page-content.admin-refined .admin-security .security-history .security-event { grid-template-columns: minmax(0, 1fr); gap: 8px; }
        .page-content.admin-refined .admin-security .metric-card { padding: 15px; gap: 7px; }
        .page-content.admin-refined .admin-security .metric-value { font-size: 25px; }
        .page-content.admin-refined .admin-security .metric-icon { width: 28px; height: 28px; }
        .page-content.admin-refined .admin-security .metric-label { font-size: 10px; }
        .admin-security .security-navigation { gap: 6px; }
        .admin-security .security-navigation a { padding: 8px; font-size: 11px; }
        .page-content.admin-refined .admin-security .security-grid > .panel { padding: 16px; }
        .admin-security .security-source { align-items: flex-start; }
    }
</style>
