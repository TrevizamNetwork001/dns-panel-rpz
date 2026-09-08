<style>
    .admin-endpoint-detail > .panel { min-width: 0; }
    .admin-endpoint-detail .endpoint-detail-statuses { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: 16px; }
    .admin-endpoint-detail .endpoint-detail-statuses strong { font-size: 12px; font-weight: 600; }
    .admin-endpoint-detail .endpoint-detail-mono,
    .admin-endpoint-detail code,
    .admin-endpoint-detail time { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; overflow-wrap: anywhere; }
    .admin-endpoint-detail .endpoint-detail-note { display: block; margin-top: 6px; color: var(--text-muted); font-size: 10px; }
    .admin-endpoint-detail .endpoint-ip-form input { min-width: 0; }
    .admin-endpoint-detail .endpoint-event-stream { list-style: none; padding: 0; margin: 12px 0 0; }
    .admin-endpoint-detail .endpoint-event { display: grid; grid-template-columns: 154px 102px minmax(0, 1fr) minmax(0, 220px); align-items: center; gap: 12px; min-height: 48px; padding: 8px 10px; border-bottom: 1px solid var(--border-soft); border-left: 2px solid var(--text-muted); font-size: 11px; }
    .admin-endpoint-detail .endpoint-event:hover { background: var(--surface-soft); }
    .admin-endpoint-detail .endpoint-event time { color: var(--text-muted); white-space: nowrap; font-size: 10px; }
    .admin-endpoint-detail .endpoint-event-message { overflow-wrap: anywhere; }
    .admin-endpoint-detail .endpoint-event-ip { color: var(--text-muted); font-size: 10px; }
    .admin-endpoint-detail .endpoint-event-sync { border-left-color: var(--cyan); }
    .admin-endpoint-detail .endpoint-event-add { border-left-color: var(--green); }
    .admin-endpoint-detail .endpoint-event-remove { border-left-color: var(--amber); }
    .admin-endpoint-detail .endpoint-event-sync .endpoint-event-type { color: var(--cyan); }
    .admin-endpoint-detail .endpoint-event-add .endpoint-event-type { color: var(--green); }
    .admin-endpoint-detail .endpoint-event-remove .endpoint-event-type { color: var(--amber); }
    @media (max-width: 1100px) {
        .admin-endpoint-detail .endpoint-detail-statuses { grid-template-columns: repeat(3, minmax(0, 1fr)); }
        .admin-endpoint-detail .endpoint-event { grid-template-columns: 145px 95px minmax(0, 1fr); gap: 6px 10px; }
        .admin-endpoint-detail .endpoint-event-ip { grid-column: 3; }
    }
    @media (max-width: 600px) {
        .admin-endpoint-detail .endpoint-detail-statuses { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .admin-endpoint-detail .endpoint-event { grid-template-columns: minmax(0, 1fr); gap: 4px; }
        .admin-endpoint-detail .endpoint-event-ip { grid-column: auto; }
        .admin-endpoint-detail .endpoint-ip-form { flex-wrap: wrap; }
        .admin-endpoint-detail .endpoint-ip-form input { flex: 1 1 100%; }
        .admin-endpoint-detail .endpoint-secret-row { flex-wrap: wrap; }
        .admin-endpoint-detail .endpoint-secret-row input { flex-basis: 65%; }
        .admin-endpoint-detail .panel-header { flex-wrap: wrap; gap: 8px; }
    }
</style>
