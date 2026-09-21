<style>
    .endpoint-directory { max-width: 1500px; margin: 0 auto; }
    .endpoint-directory .page-heading { align-items: center; margin-bottom: 26px; gap: 18px; }
    .endpoint-directory .page-eyebrow { color: var(--cyan); font-size: 11px; letter-spacing: .14em; }
    .endpoint-directory h1 { font-size: 32px; letter-spacing: -.035em; margin: 8px 0; }
    .endpoint-directory .page-heading p { font-size: 13px; color: var(--text-soft); }
    .endpoint-directory .page-heading > .button { min-height: 44px; font-size: 12px; flex-shrink: 0; }
    .endpoint-directory-summary { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 16px; margin-bottom: 24px; }
    .endpoint-directory-summary > div { display: flex; align-items: center; gap: 16px; padding: 20px; background: var(--surface); border: 1px solid var(--border); border-radius: 12px; }
    .endpoint-directory-summary > div > div { display: grid; gap: 6px; }
    .endpoint-directory-summary > div > div > span { color: var(--text-soft); font-size: 12px; }
    .endpoint-directory-summary strong { font-size: 27px; line-height: 1.1; font-weight: 650; font-variant-numeric: tabular-nums; }
    .endpoint-summary-icon { display: flex; align-items: center; justify-content: center; width: 44px; height: 44px; border: 1px solid var(--border); border-radius: 12px; background: var(--cyan-soft); color: var(--cyan); flex-shrink: 0; }
    .endpoint-summary-icon.is-green { color: var(--green); background: rgba(53, 211, 154, .08); }
    .endpoint-summary-icon.is-muted { color: var(--text-muted); background: var(--surface-soft); }
    .endpoint-directory .endpoint-directory-panel { padding: 0; border-radius: 13px; box-shadow: none; }
    .endpoint-directory .panel-header { padding: 21px 22px; margin: 0; border-bottom: 1px solid var(--border-soft); align-items: center; }
    .endpoint-directory-title { display: flex; align-items: center; gap: 12px; }
    .endpoint-directory-title svg { color: var(--cyan); }
    .endpoint-directory-title h2 { margin: 0; font-size: 15px; }
    .endpoint-directory .panel-header > span { color: var(--text-muted); font-size: 11px; }
    .endpoint-directory-table { table-layout: fixed; }
    .endpoint-directory-table th { padding: 15px 14px; font-size: 10px; font-weight: 550; letter-spacing: .07em; background: var(--surface-soft); }
    .endpoint-directory-table th:first-child { width: 24%; padding-left: 22px; }
    .endpoint-directory-table th:nth-child(2) { width: 19%; }
    .endpoint-directory-table th:nth-child(3) { width: 12%; }
    .endpoint-directory-table th:nth-child(4) { width: 15%; }
    .endpoint-directory-table th:nth-child(5) { width: 14%; }
    .endpoint-directory-table th:last-child { width: 190px; padding-right: 22px; }
    .endpoint-directory-table td { padding: 22px 14px; font-size: 12px; overflow-wrap: anywhere; }
    .endpoint-directory-table td:first-child { padding-left: 22px; }
    .endpoint-directory-table tbody tr:hover { background: var(--surface-soft); }
    .endpoint-server-identity { display: flex; align-items: center; gap: 13px; }
    .endpoint-server-identity > div { min-width: 0; }
    .endpoint-server-glyph { display: flex; align-items: center; justify-content: center; width: 42px; height: 42px; flex-shrink: 0; border: 1px solid var(--border); border-radius: 10px; color: var(--cyan); background: var(--cyan-soft); }
    .endpoint-server-identity .table-primary-link { font-size: 13px; font-weight: 650; }
    .endpoint-server-identity small, .endpoint-directory-consultation small { display: block; font-size: 10px; color: var(--text-muted); margin-top: 6px; }
    .endpoint-directory-company { display: flex; align-items: center; gap: 9px; color: var(--text-soft); line-height: 1.6; }
    .endpoint-directory-company svg { color: var(--text-muted); flex-shrink: 0; }
    .endpoint-directory-company:hover { color: var(--cyan); }
    .endpoint-list-count { display: inline-flex; gap: 7px; align-items: center; padding: 6px 10px; border: 1px solid var(--border); border-radius: 18px; color: var(--cyan); background: var(--cyan-soft); font-size: 11px; }
    .endpoint-list-count svg { flex-shrink: 0; }
    .endpoint-list-count.is-empty { color: var(--text-muted); background: var(--surface-soft); }
    .endpoint-directory-consultation { display: flex; align-items: flex-start; gap: 8px; font-variant-numeric: tabular-nums; }
    .endpoint-directory-consultation > svg { flex-shrink: 0; color: var(--cyan); }
    .endpoint-directory-status { display: flex; align-items: flex-start; flex-direction: column; gap: 6px; }
    .endpoint-directory-status .status-pill { display: inline-flex; align-items: center; gap: 4px; font-size: 11px; font-weight: 550; padding: 5px 9px; }
    .endpoint-directory-status small { color: var(--text-muted); font-size: 10px; padding-left: 5px; }
    .endpoint-pending-ip { display: flex; align-items: center; gap: 4px; color: var(--amber); font-size: 10px; }
    .endpoint-pending-ip svg { flex-shrink: 0; }
    .endpoint-directory-actions { display: flex; justify-content: flex-end; align-items: center; gap: 6px; }
    .endpoint-directory-actions .button { min-height: 33px; padding: 0 8px; font-size: 10px; font-weight: 550; color: var(--cyan); white-space: nowrap; }
    .endpoint-directory-actions .endpoint-edit-action { padding: 0; width: 32px; color: var(--text-soft); flex-shrink: 0; }
    .endpoint-directory-actions .actions-menu-toggle { width: 27px; min-width: 27px; font-size: 0; }
    .endpoint-directory-actions .actions-menu-toggle::before { content: ""; width: 3px; height: 3px; border-radius: 50%; background: currentColor; box-shadow: 0 -5px currentColor, 0 5px currentColor; }
    .endpoint-directory-footer { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 16px; padding: 18px 22px; border-top: 1px solid var(--border-soft); color: var(--text-muted); font-size: 11px; }
    .endpoint-directory-footer .pagination-simple { margin: 0; padding: 0; border: 0; font-size: 11px; }
    html:not([data-theme="light"]) .endpoint-directory { --surface: #061b29; --surface-soft: #0b2130; --border: #183849; --border-soft: #133040; --text-soft: #adc5d9; --text-muted: #88a5ba; --cyan: #00cbee; --green: #00dba0; }
    @media (max-width: 1250px) and (min-width: 901px) {
        .endpoint-directory-table-wrap { overflow-x: auto; }
        .endpoint-directory-table { min-width: 980px; }
    }
    @media (max-width: 900px) {
        .endpoint-directory-summary { gap: 10px; }
        .endpoint-directory-summary > div { padding: 14px; gap: 10px; }
        .endpoint-directory-summary .endpoint-summary-icon { width: 35px; height: 35px; }
        .endpoint-directory-summary > div > div > span { font-size: 10px; }
        .endpoint-directory .panel-header { flex-wrap: wrap; gap: 8px; }
        .endpoint-directory-table, .endpoint-directory-table tbody { display: block; width: 100%; min-width: 0; }
        .endpoint-directory-table thead { display: none; }
        .endpoint-directory-table tr { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 18px; padding: 20px; border-bottom: 1px solid var(--border-soft); }
        .endpoint-directory-table td, .endpoint-directory-table td:first-child { display: block; border: 0; padding: 0; min-width: 0; }
        .endpoint-directory-table td[data-label]::before { content: attr(data-label); display: block; color: var(--text-muted); font-size: 10px; margin-bottom: 8px; }
        .endpoint-directory-server, .endpoint-directory-actions-cell { grid-column: 1 / -1; }
        .endpoint-directory-actions { justify-content: flex-start; }
        .endpoint-directory-actions .endpoint-details-action { min-height: 36px; padding: 0 12px; }
    }
    @media (max-width: 540px) {
        .endpoint-directory .page-heading { align-items: flex-start; flex-direction: column; }
        .endpoint-directory h1 { font-size: 28px; }
        .endpoint-directory-summary > div { flex-direction: column; align-items: flex-start; padding: 12px; }
        .endpoint-directory-summary strong { font-size: 23px; }
        .endpoint-directory .panel-header { padding: 18px; }
        .endpoint-directory-table tr { padding: 18px; }
    }
</style>
