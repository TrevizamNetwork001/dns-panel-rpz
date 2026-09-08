<style>
    .admin-settings { max-width: 1100px; }
    .admin-settings [hidden] { display: none !important; }
    .admin-settings .settings-tabs { display: flex; gap: 24px; overflow-x: auto; white-space: nowrap; border-bottom: 1px solid var(--border); margin-bottom: 22px; padding: 4px 4px 0; }
    .admin-settings .settings-tab { flex: 0 0 auto; padding: 10px 0 12px; border: 0; border-bottom: 2px solid transparent; background: transparent; color: var(--text-muted); font: inherit; font-size: 13px; cursor: pointer; }
    .admin-settings .settings-tab:hover { color: var(--text); }
    .admin-settings .settings-tab.is-active { color: var(--text); font-weight: 600; border-bottom-color: var(--cyan); }
    .admin-settings .settings-tab:focus-visible { outline: 1px solid var(--cyan); outline-offset: -1px; }
    .admin-settings [data-settings-panel="notificacoes"] { width: 100%; max-width: 800px; }
    .admin-settings .settings-section { padding: 0 0 22px; }
    .admin-settings .settings-section + .settings-section { padding-top: 20px; border-top: 1px solid var(--border-soft); }
    .admin-settings .settings-heading { display: flex; align-items: baseline; justify-content: space-between; gap: 12px; margin-bottom: 12px; }
    .admin-settings h2 { font-size: 15px; margin: 0; font-weight: 600; }
    .admin-settings .settings-heading p { font-size: 12px; color: var(--text-soft); margin: 5px 0 0; }
    .admin-settings .settings-status { display: inline-flex; align-items: center; gap: 6px; color: var(--text-soft); font-size: 11px; white-space: nowrap; }
    .admin-settings .settings-status::before { content: ''; width: 5px; height: 5px; border-radius: 50%; background: var(--text-muted); }
    .admin-settings .settings-status.is-enabled::before { background: var(--green); }
    .admin-settings .settings-inventory { margin: 0; }
    .admin-settings .settings-inventory > div { display: grid; grid-template-columns: 220px minmax(0, 1fr); gap: 16px; padding: 11px 0; border-bottom: 1px solid var(--border-soft); font-size: 12px; line-height: 1.6; }
    .admin-settings dt { color: var(--text-soft); }
    .admin-settings dd { margin: 0; overflow-wrap: anywhere; }
    .admin-settings .settings-mono, .admin-settings code { font-family: ui-monospace, SFMono-Regular, Consolas, monospace; }
    .admin-settings .settings-note { display: block; color: var(--text-muted); font-size: 11px; line-height: 1.6; margin: 10px 0 0; }
    .admin-settings .settings-copy { color: var(--text-soft); font-size: 12px; line-height: 1.6; margin: 0; max-width: 780px; }
    .admin-settings .settings-links { list-style: none; display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 20px; margin: 0; padding: 0; }
    .admin-settings .settings-links a { display: block; padding: 8px 0; }
    .admin-settings .settings-links strong { display: block; font-size: 13px; font-weight: 600; }
    .admin-settings .settings-links span { display: block; font-size: 12px; color: var(--text-muted); margin-top: 5px; }
    .admin-settings .settings-links a:hover strong, .admin-settings .settings-shortcut:hover { color: var(--cyan); }
    .admin-settings .settings-form { width: 100%; max-width: 800px; display: grid; gap: 16px; margin-top: 18px; }
    .admin-settings .settings-form .field-group { min-width: 0; margin: 0; }
    .admin-settings .settings-form .form-control { padding: 10px 12px; border-radius: 8px; font-size: 13px; }
    .admin-settings .settings-form .form-control { padding: 10px 12px; border-radius: 8px; font-size: 13px; }
    .admin-settings .settings-check { display: flex; justify-content: space-between; align-items: center; gap: 16px; padding: 10px 0; border-top: 1px solid var(--border-soft); border-bottom: 1px solid var(--border-soft); cursor: pointer; }
    .admin-settings .settings-check strong { display: block; font-size: 12px; font-weight: 600; }
    .admin-settings .settings-check small { display: block; color: var(--text-muted); font-size: 11px; margin-top: 4px; }
    .admin-settings .settings-check input { width: 17px; height: 17px; flex-shrink: 0; accent-color: var(--cyan); }
    .admin-settings .settings-fields { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; }
    .admin-settings .settings-actions { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; margin: 20px 0; }
    .admin-settings .settings-actions form { margin: 0; }
    .admin-settings .settings-actions .button { box-shadow: none; }
    .admin-settings .settings-actions .button { box-shadow: none; }
    .admin-settings .settings-help { max-width: 800px; border-top: 1px solid var(--border-soft); padding-top: 14px; color: var(--text-soft); font-size: 12px; line-height: 1.7; }
    .admin-settings .settings-help summary { cursor: pointer; }
    .admin-settings .settings-help p { margin: 10px 0 0; overflow-wrap: anywhere; }
    .admin-settings .settings-shortcut { display: inline-block; color: var(--text-soft); margin-top: 14px; font-size: 12px; }
    .admin-settings .settings-summary { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 24px; border-bottom: 1px solid var(--border-soft); margin: 0 0 32px; padding: 0 0 18px; }
    .admin-settings .settings-summary > div { display: flex; flex-direction: column; gap: 5px; }
    .admin-settings .settings-summary dt { font-size: 12px; }
    .admin-settings .settings-summary dd:not(.settings-summary-note) { order: -1; font-size: 26px; font-family: ui-monospace, SFMono-Regular, Consolas, monospace; }
    .admin-settings .settings-summary-note { color: var(--text-muted); font-size: 11px; }
    @media (max-width: 800px) {
        .admin-settings .settings-fields { grid-template-columns: minmax(0, 1fr); }
    }
    @media (max-width: 600px) {
        .admin-settings .settings-inventory > div { grid-template-columns: minmax(0, 1fr); gap: 3px; }
        .admin-settings .settings-links { grid-template-columns: minmax(0, 1fr); gap: 4px; }
        .admin-settings .settings-summary { gap: 12px; }
    }
    @media (max-width: 520px) {
        .admin-settings .settings-summary { grid-template-columns: minmax(0, 1fr); }
        .admin-settings .settings-summary > div { display: grid; grid-template-columns: 36px minmax(0, 1fr); gap: 3px 12px; }
        .admin-settings .settings-summary dd:not(.settings-summary-note) { grid-column: 1; grid-row: 1 / 3; }
        .admin-settings .settings-summary dt, .admin-settings .settings-summary-note { grid-column: 2; }
        .admin-settings .settings-summary { grid-template-columns: minmax(0, 1fr); }
        .admin-settings .settings-summary > div { display: grid; grid-template-columns: 36px minmax(0, 1fr); gap: 3px 12px; }
        .admin-settings .settings-summary dd:not(.settings-summary-note) { grid-column: 1; grid-row: 1 / 3; }
        .admin-settings .settings-summary dt, .admin-settings .settings-summary-note { grid-column: 2; }
        .app-content:has(.admin-settings) .app-topbar, .app-content:has(.admin-settings) .topbar-actions { gap: 8px; }
        .admin-settings .settings-actions { align-items: stretch; flex-direction: column; }
        .admin-settings .settings-actions .button { width: 100%; }
    }
</style>
