<style>
    .client-lists { max-width: 1440px; margin: 0 auto; }
    .client-lists .page-heading { align-items: center; gap: 24px; margin-bottom: 24px; }
    .client-lists .page-heading h1 { font-size: 30px; letter-spacing: -.03em; }
    .client-lists-notice { display: flex; align-items: center; gap: 16px; max-width: 430px; padding: 14px 18px; border: 1px solid var(--border); border-radius: 12px; background: var(--surface); color: var(--text-soft); font-size: 12px; line-height: 1.6; }
    .client-lists-notice svg { color: var(--cyan); }
    .client-lists .client-lists-panel { padding: 18px; border-radius: 16px; }
    .client-lists-items { display: grid; gap: 8px; padding: 0; margin: 0; list-style: none; }
    .client-list-card { display: grid; grid-template-columns: 64px minmax(0, 1fr) 120px 160px; align-items: center; gap: 18px; min-height: 78px; padding: 14px 20px; border: 1px solid var(--border-soft); border-radius: 12px; background: var(--surface-soft); }
    .client-list-icon { display: flex; align-items: center; justify-content: center; }
    .client-list-icon svg { filter: drop-shadow(0 0 10px color-mix(in srgb, currentColor 20%, transparent)); }
    .client-list-icon .server-category-building { color: var(--violet); }
    .client-list-icon .server-category-lock { color: #f59b55; }
    .client-list-icon .server-category-network, .client-list-icon .server-category-alert { color: var(--cyan); }
    .client-list-copy { display: grid; gap: 4px; overflow-wrap: anywhere; }
    .client-list-copy strong { font-size: 14px; line-height: 1.45; }
    .client-list-copy > span { color: var(--text-soft); font-size: 12px; line-height: 1.5; }
    .client-list-type { text-align: center; }
    .client-list-type .status-pill { padding: 5px 14px; }
    .client-list-count { display: grid; gap: 3px; text-align: center; border-left: 1px solid var(--border); font-variant-numeric: tabular-nums; }
    .client-list-count strong { color: var(--cyan); font-size: 20px; line-height: 1.2; }
    .client-list-count > span { color: var(--text-soft); font-size: 12px; }
    @media (max-width: 1000px) {
        .client-list-card { grid-template-columns: 42px minmax(0, 1fr) 90px 120px; gap: 12px; padding: 14px; }
    }
    @media (max-width: 640px) {
        .client-lists .page-heading { align-items: stretch; flex-direction: column; gap: 16px; }
        .client-lists-notice { max-width: none; }
        .client-lists .client-lists-panel { padding: 10px; }
        .client-list-card { grid-template-columns: 38px minmax(0, 1fr) 106px; }
        .client-list-icon { grid-row: 1 / 3; }
        .client-list-copy { grid-column: 2 / 4; }
        .client-list-type { grid-column: 2; text-align: left; }
        .client-list-count { grid-column: 3; }
        .client-list-count strong { font-size: 18px; }
    }
</style>
