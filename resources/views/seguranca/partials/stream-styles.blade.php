<style>
    .admin-security .security-note { display: flex; align-items: flex-start; gap: 10px; margin: 14px 0 20px; color: var(--text-soft); font-size: 12px; line-height: 1.6; }
    .admin-security .metric-label { color: var(--text-soft); }
    .admin-security .security-note svg { flex: 0 0 16px; margin-top: 2px; }
    .admin-security .security-note strong { color: var(--text); font-weight: 600; }
    .admin-security .security-note p { margin: 2px 0 0; }
    .admin-security .security-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; align-items: start; }
    .admin-security .security-grid > .panel { margin: 0; }
    .admin-security .security-history { grid-column: 1 / -1; }
    .admin-security .panel-header { flex-wrap: wrap; gap: 6px 16px; padding-bottom: 12px; margin-bottom: 12px; }
    .admin-security .security-status { color: var(--text-muted); font-size: 11px; }
    .admin-security .security-empty { margin: 0; padding: 8px 0; color: var(--text-soft); font-size: 12px; line-height: 1.6; }
    .admin-security .security-stream { list-style: none; padding: 0; margin: 0; }
    .admin-security .security-event { position: relative; padding: 12px 0 12px 14px; border-bottom: 1px solid var(--border-soft); overflow-wrap: anywhere; }
    .admin-security .security-event::before { content: ''; position: absolute; top: 13px; bottom: 13px; left: 0; width: 2px; background: var(--security-marker, var(--text-muted)); }
    .admin-security .security-event:last-child { border-bottom: 0; }
    .admin-security .security-event-danger { --security-marker: var(--danger); }
    .admin-security .security-event-warning { --security-marker: var(--amber); }
    .admin-security .security-event-success { --security-marker: var(--green); }
    .admin-security .security-mono { font-family: ui-monospace, SFMono-Regular, Consolas, monospace; }
    .admin-security .security-date { display: block; color: var(--text-muted); font-size: 11px; line-height: 1.6; margin-bottom: 3px; }
    .admin-security .security-action { display: block; color: var(--text); font-size: 13px; line-height: 1.5; font-weight: 600; }
    .admin-security .security-meta { margin-top: 3px; color: var(--text-soft); font-size: 12px; line-height: 1.6; }
    .admin-security .security-history .security-event { display: grid; grid-template-columns: 154px minmax(0, 1fr); gap: 16px; }
    @media (max-width: 1000px) {
        .admin-security .security-grid { grid-template-columns: minmax(0, 1fr); }
    }
    @media (max-width: 600px) {
        .admin-security .security-history .security-event { grid-template-columns: minmax(0, 1fr); gap: 0; }
    }
    @media (max-width: 520px) {
        .app-content:has(.admin-security) .app-topbar, .app-content:has(.admin-security) .topbar-actions { gap: 8px; }
    }
</style>
