<style>
    .admin-audit { max-width: 1400px; margin: 0 auto; }
    .admin-audit .audit-heading { align-items: center; margin-bottom: 24px; }
    .admin-audit .audit-heading h1 { font-size: clamp(28px, 3vw, 36px); margin: 8px 0; }
    .admin-audit .audit-heading-badge { display: inline-flex; align-items: center; gap: 8px; padding: 9px 12px; border: 1px solid var(--border); border-radius: 20px; color: var(--text-soft); font-size: 11px; white-space: nowrap; }
    .admin-audit .metrics-grid { gap: 14px; margin-bottom: 22px; }
    .admin-audit .metric-card { position: relative; display: grid; grid-template-columns: 1fr auto; align-content: center; gap: 5px 12px; min-height: 118px; padding: 20px; border-radius: 12px; box-shadow: 0 4px 16px rgb(0 0 0 / 4%); }
    .admin-audit .metric-card-header { grid-column: 2; grid-row: 1 / 3; align-self: center; }
    .admin-audit .metric-value { grid-column: 1; margin: 0; font-size: 30px; line-height: 1.2; letter-spacing: -.04em; font-variant-numeric: tabular-nums; }
    .admin-audit .metric-label { grid-column: 1; font-size: 11px; color: var(--text-soft); line-height: 1.5; }
    .admin-audit .metric-icon { width: 40px; height: 40px; border-radius: 12px; }
    .admin-audit .panel { border-radius: 14px; padding: 22px; }
    .admin-audit .panel-header { align-items: center; gap: 14px; margin-bottom: 18px; }
    .admin-audit .panel-header h2 { display: flex; align-items: center; gap: 10px; font-size: 15px; margin: 0; }
    .admin-audit .audit-help { color: var(--text-muted); font-size: 12px; line-height: 1.6; margin: 5px 0 0; }
    .admin-audit .audit-section-label, .admin-audit .audit-order { color: var(--text-muted); font-size: 11px; }
    .admin-audit .audit-filter-form { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 16px; }
    .admin-audit .audit-filter-form .field-group { min-width: 0; margin: 0; gap: 7px; }
    .admin-audit .audit-filter-form label { font-size: 12px; color: var(--text-soft); }
    .admin-audit .audit-filter-form .form-control { width: 100%; min-width: 0; height: 40px; border-color: var(--border); border-radius: 8px; padding: 8px 11px; background: var(--surface-soft); color: var(--text); font: inherit; font-size: 12px; }
    .admin-audit .audit-filter-form select { text-overflow: ellipsis; }
    .admin-audit .audit-filter-footer { grid-column: 1 / -1; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px; padding-top: 15px; border-top: 1px solid var(--border-soft); }
    .admin-audit .audit-routine-toggle { display: inline-flex; align-items: center; gap: 9px; line-height: 1.5; cursor: pointer; }
    .admin-audit .audit-routine-toggle input[type=checkbox] { width: 16px; height: 16px; margin: 0; accent-color: var(--cyan); flex-shrink: 0; }
    .admin-audit .audit-filter-actions { display: flex; gap: 8px; }
    .admin-audit .audit-filter-actions .button { min-height: 38px; border-radius: 8px; padding: 8px 16px; font-size: 12px; }
    .admin-audit .audit-window-note { margin: 14px 0 0; color: var(--text-muted); font-size: 11px; line-height: 1.6; }
    .admin-audit .audit-count { padding: 3px 8px; border-radius: 6px; background: var(--cyan-soft); color: var(--cyan); font-size: 11px; font-weight: 600; }
    .admin-audit .audit-stream { list-style: none; margin: 0; padding: 0; display: grid; gap: 10px; }
    .admin-audit .audit-event { --audit-marker: var(--text-muted); position: relative; display: grid; grid-template-columns: 84px minmax(0, 1fr) 166px; gap: 18px; align-items: start; padding: 18px; border: 1px solid var(--border-soft); border-radius: 10px; background: var(--surface-soft); font-size: 12px; overflow-wrap: anywhere; }
    .admin-audit .audit-event::before { content: ''; position: absolute; left: -1px; top: 15px; bottom: 15px; width: 3px; border-radius: 3px; background: var(--audit-marker); }
    .admin-audit .audit-severity-info { --audit-marker: var(--cyan); }
    .admin-audit .audit-severity-warning { --audit-marker: var(--amber); }
    .admin-audit .audit-severity-danger { --audit-marker: var(--danger); }
    .admin-audit .audit-date { display: grid; gap: 4px; font-variant-numeric: tabular-nums; color: var(--text-muted); font-size: 11px; line-height: 1.5; }
    .admin-audit .audit-date strong { color: var(--text-soft); font-size: 13px; font-weight: 500; }
    .admin-audit .audit-action-row { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; }
    .admin-audit .audit-action { font-size: 13px; font-weight: 600; line-height: 1.6; color: var(--text); }
    .admin-audit .audit-severity-pill { display: inline-flex; align-items: center; gap: 5px; color: var(--audit-marker); border: 1px solid color-mix(in srgb, var(--audit-marker) 30%, transparent); background: color-mix(in srgb, var(--audit-marker) 8%, transparent); border-radius: 5px; padding: 1px 6px; font-size: 10px; white-space: nowrap; }
    .admin-audit .audit-severity-pill::before { content: ''; width: 4px; height: 4px; border-radius: 50%; background: currentColor; }
    .admin-audit .audit-meta { display: flex; flex-wrap: wrap; gap: 4px 14px; margin-top: 7px; line-height: 1.7; color: var(--text-soft); font-size: 11px; }
    .admin-audit .audit-meta strong { font-weight: 500; }
    .admin-audit .audit-category { color: var(--text-muted); }
    .admin-audit .audit-ip { display: flex; align-items: flex-start; justify-content: flex-end; gap: 6px; font-family: ui-monospace, SFMono-Regular, Consolas, monospace; color: var(--text-muted); font-size: 10px; line-height: 1.7; }
    .admin-audit .audit-ip svg { flex-shrink: 0; margin-top: 2px; }
    .admin-audit .audit-description { white-space:pre-wrap; overflow-wrap:anywhere; margin: 8px 0 0; line-height: 1.7; color: var(--text-soft); font-size: 12px; }
    .admin-audit .audit-event-routine .audit-action { font-weight: 500; }
    .admin-audit .empty-state strong { font-size: 14px; }
    .admin-audit .empty-state span { font-size: 12px; }
    .admin-audit .audit-sr-only { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip-path: inset(50%); white-space: nowrap; border: 0; }
    .admin-audit [hidden] { display: none !important; }
    @media (max-width: 1200px) {
        .admin-audit .audit-event { grid-template-columns: 78px minmax(0, 1fr); gap: 10px 16px; }
        .admin-audit .audit-ip { grid-column: 2; justify-content: flex-start; }
    }
    @media (max-width: 900px) {
        .admin-audit .audit-filter-form { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .admin-audit .audit-heading-badge { display: none; }
    }
    @media (max-width: 520px) {
        .admin-audit .audit-heading { margin-bottom: 20px; }
        .admin-audit .metrics-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; }
        .admin-audit .metric-card { padding: 14px; min-height: 115px; gap: 5px; }
        .admin-audit .metric-icon { width: 28px; height: 28px; border-radius: 8px; }
        .admin-audit .metric-value { font-size: 25px; }
        .admin-audit .metric-label { font-size: 10px; }
        .admin-audit .panel { padding: 16px; }
        .admin-audit .audit-filter-form { gap: 14px 10px; }
        .admin-audit .audit-filter-form > .field-group:first-child, .admin-audit .audit-filter-form > .field-group:nth-child(3) { grid-column: 1 / -1; }
        .admin-audit .audit-filter-form > .field-group:nth-child(2) { grid-column: 1 / -1; }
        .admin-audit .audit-filter-form > .field-group:nth-child(6) { grid-column: 1 / -1; }
        .admin-audit .audit-filter-actions { width: 100%; }
        .admin-audit .audit-filter-actions .button { flex: 1; justify-content: center; }
        .admin-audit .audit-event { grid-template-columns: minmax(0, 1fr); padding: 14px; gap: 10px; }
        .admin-audit .audit-date { display: flex; align-items: baseline; gap: 8px; }
        .admin-audit .audit-ip { grid-column: 1; }
        .admin-audit .audit-order { display: none; }
        .app-content:has(.admin-audit) .app-topbar, .app-content:has(.admin-audit) .topbar-actions { gap: 8px; }
    }
</style>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const search = document.getElementById('audit-search');
        const events = Array.from(document.querySelectorAll('.admin-audit [data-audit-search]'));
        const empty = document.getElementById('audit-search-empty');
        if (!search || !empty) return;

        search.addEventListener('input', () => {
            const query = search.value.trim().toLowerCase();
            let visible = 0;
            events.forEach(event => {
                event.hidden = !event.dataset.auditSearch.toLowerCase().includes(query);
                if (!event.hidden) visible++;
            });
            empty.hidden = visible !== 0;
        });
    });
</script>
