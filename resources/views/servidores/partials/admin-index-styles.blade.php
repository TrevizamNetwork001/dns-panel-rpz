<style>
    .admin-endpoints-compact { max-width: 1400px; margin: 0 auto; }
    .admin-endpoints-compact .page-heading { align-items: center; margin-bottom: 22px; }
    .admin-endpoints-compact .page-heading h1 { font-size: 28px; letter-spacing: -.03em; }
    .admin-endpoints-compact .page-heading p { color: var(--text-soft); font-size: 12px; }
    .admin-endpoints-compact .page-heading .button { min-height: 40px; }
    .admin-endpoints-compact .admin-endpoints-page { padding: 0; overflow: hidden; }
    .admin-endpoints-compact .admin-compact-table { table-layout: fixed; }
    .admin-endpoints-compact .admin-compact-table th { padding: 13px 12px; }
    .admin-endpoints-compact .admin-compact-table th:first-child { width: 25%; }
    .admin-endpoints-compact .admin-compact-table th:nth-child(2) { width: 17%; }
    .admin-endpoints-compact .admin-compact-table th:nth-child(3) { width: 12%; }
    .admin-endpoints-compact .admin-compact-table th:nth-child(4) { width: 15%; }
    .admin-endpoints-compact .admin-compact-table th:nth-child(5) { width: 15%; }
    .admin-endpoints-compact .admin-compact-table th:last-child { width: 16%; }
    .admin-endpoints-compact .admin-compact-table td { padding: 16px 12px; }
    .admin-endpoints-compact .admin-compact-table td:first-child { padding-left: 18px; }
    .admin-endpoints-compact .admin-compact-table tbody tr:hover { background: var(--surface-soft); }
    .admin-server-name { display: inline-flex; align-items: center; gap: 8px; }
    .admin-server-name svg { color: var(--cyan); flex-shrink: 0; }
    .admin-server-company { display: block; margin: 5px 0 0 26px; color: var(--text-muted); font-size: 10px; overflow-wrap: anywhere; }
    .admin-list-empty { color: var(--danger); }
    .admin-endpoints-compact .endpoint-status-stack { display: flex; align-items: flex-start; flex-direction: column; gap: 4px; }
    .admin-endpoints-compact .endpoint-status-stack small { color: var(--text-muted); font-size: 9px; white-space: nowrap; }
    .admin-endpoints-compact .status-pill { font-size: 9px; }
    .admin-compact-actions { display: flex; align-items: center; justify-content: flex-end; gap: 5px; }
    .admin-compact-actions .button { min-height: 31px; padding: 0 9px; font-size: 10px; color: var(--cyan); }
    .admin-compact-actions .admin-compact-edit { width: 31px; padding: 0; color: var(--text-soft); }
    .admin-compact-actions .actions-menu-toggle { width: 27px; height: 31px; }
    @media (max-width: 1000px) { .admin-endpoints-compact .table-responsive { overflow-x: auto; } .admin-endpoints-compact .admin-compact-table { min-width: 900px; } }
    @media (max-width: 600px) {
        .admin-endpoints-compact .page-heading { align-items: flex-start; flex-direction: column; }
        .admin-endpoints-compact .admin-compact-table { min-width: 0; }
        .admin-endpoints-compact .admin-compact-table thead { display: none; }
        .admin-endpoints-compact .admin-compact-table, .admin-endpoints-compact .admin-compact-table tbody, .admin-endpoints-compact .admin-compact-table tr { display: block; width: 100%; }
        .admin-endpoints-compact .admin-compact-table tr { padding: 16px; border-top: 1px solid var(--border-soft); }
        .admin-endpoints-compact .admin-compact-table td, .admin-endpoints-compact .admin-compact-table td:first-child { display: block; width: 100%; padding: 7px 0; border: 0; }
        .admin-endpoints-compact .admin-compact-table td:not(:first-child)::before { content: attr(data-label); display: block; margin-bottom: 5px; color: var(--text-muted); font-size: 9px; text-transform: uppercase; }
        .admin-compact-actions { justify-content: flex-start; margin-top: 8px; }
    }
</style>
