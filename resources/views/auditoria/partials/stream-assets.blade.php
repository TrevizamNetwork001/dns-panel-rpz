<style>
    .admin-audit .audit-stream { list-style: none; margin: 0; padding: 0; }
    .admin-audit .audit-event {
        --audit-marker: var(--text-muted);
        position: relative; display: grid; grid-template-columns: 154px minmax(0, 1fr) minmax(0, 230px);
        gap: 16px; align-items: start; padding: 14px 0 14px 14px; border-bottom: 1px solid var(--border-soft);
        font-size: 12px; overflow-wrap: anywhere;
    }
    .admin-audit .audit-event::before { content: ''; position: absolute; left: 0; top: 15px; bottom: 15px; width: 2px; background: var(--audit-marker); }
    .admin-audit .audit-event:last-child { border-bottom: 0; }
    .admin-audit .audit-severity-info { --audit-marker: var(--cyan); }
    .admin-audit .audit-severity-warning { --audit-marker: var(--amber); }
    .admin-audit .audit-severity-danger { --audit-marker: var(--danger); }
    .admin-audit .audit-date, .admin-audit .audit-ip { font-family: ui-monospace, SFMono-Regular, Consolas, monospace; font-size: 11px; line-height: 1.6; color: var(--text-muted); }
    .admin-audit .audit-ip { text-align: right; }
    .admin-audit .audit-action { display: block; font-size: 13px; font-weight: 600; line-height: 1.5; color: var(--text); }
    .admin-audit .audit-meta { margin-top: 3px; line-height: 1.5; color: var(--text-soft); }
    .admin-audit .audit-event-routine .audit-action { color: var(--text-soft); font-weight: 400; }
    .admin-audit .audit-sr-only { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip-path: inset(50%); white-space: nowrap; border: 0; }
    .admin-audit [hidden] { display: none !important; }
    @media (max-width: 900px) {
        .admin-audit .audit-event { grid-template-columns: minmax(0, 1fr); gap: 5px; }
        .admin-audit .audit-ip { text-align: left; }
    }
    @media (max-width: 520px) {
        .app-content:has(.admin-audit) .app-topbar { gap: 8px; }
        .app-content:has(.admin-audit) .topbar-actions { gap: 8px; }
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
