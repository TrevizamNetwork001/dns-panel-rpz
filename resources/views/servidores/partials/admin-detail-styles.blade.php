<style>
    .endpoint-workspace { --endpoint-orange: #e89332; max-width: 1460px; margin: 0 auto; }
    .endpoint-breadcrumb { display: flex; align-items: center; gap: 12px; color: var(--text-soft); font-size: 12px; margin-bottom: 28px; }
    .endpoint-breadcrumb a { color: var(--cyan); }
    .endpoint-workspace .page-heading { align-items: center; margin-bottom: 26px; gap: 18px; }
    .endpoint-workspace .page-heading h1 { font-size: clamp(25px, 2.4vw, 38px); line-height: 1.2; letter-spacing: -.035em; margin: 8px 0 10px; overflow-wrap: anywhere; }
    .endpoint-workspace .page-heading .page-eyebrow { color: var(--cyan); font-size: 11px; letter-spacing: .15em; }
    .endpoint-workspace .page-heading p { font-size: 13px; margin: 0; }
    .endpoint-workspace .page-heading .inline-link { color: var(--text-soft); font-size: 13px; font-weight: 450; }
    .endpoint-workspace .page-actions { flex-shrink: 0; gap: 12px; }
    .endpoint-workspace .page-actions .button { min-height: 44px; padding: 0 18px; font-size: 12px; }
    .endpoint-overview { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); gap: 20px; align-items: start; }
    .endpoint-overview > .panel:first-child { grid-column: 1 / -1; }
    .endpoint-left-column { display: grid; gap: 16px; min-width: 0; }
    .endpoint-workspace .panel { min-width: 0; padding: 20px; border-radius: 13px; box-shadow: none; }
    .endpoint-workspace .panel-header { margin: 0 0 18px; padding: 0; border: 0; gap: 12px; align-items: center; }
    .endpoint-workspace .panel-header h2 { font-size: 14px; line-height: 1.4; margin: 0; letter-spacing: -.015em; }
    .endpoint-card-title { display: flex; align-items: flex-start; gap: 16px; min-width: 0; }
    .endpoint-card-title > svg { color: var(--cyan); flex-shrink: 0; }
    .endpoint-workspace .endpoint-detail-note { display: block; margin: 6px 0 0; color: var(--text-soft); font-size: 12px; line-height: 1.6; }
    .endpoint-workspace .panel-header .button { min-height: 35px; padding: 0 10px; color: var(--cyan); font-size: 11px; font-weight: 500; flex-shrink: 0; }
    .endpoint-workspace .admin-endpoint-status { padding: 26px 20px; }
    .endpoint-workspace .endpoint-detail-statuses { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 0; }
    .endpoint-workspace .endpoint-detail-statuses > div { display: grid; grid-template-columns: 25px minmax(0, 1fr); grid-template-rows: auto 1fr; gap: 9px 16px; align-items: center; padding: 4px 18px; border-right: 1px solid var(--border-soft); min-width: 0; }
    .endpoint-workspace .endpoint-detail-statuses > div:first-child { padding-left: 0; }
    .endpoint-workspace .endpoint-detail-statuses > div:last-child { border-right: 0; padding-right: 0; }
    .endpoint-workspace .endpoint-detail-statuses svg { grid-row: 1 / 3; color: var(--text-soft); }
    .endpoint-workspace .endpoint-detail-statuses > div > span { color: var(--text-soft); font-size: 10px; white-space: nowrap; }
    .endpoint-workspace .endpoint-detail-statuses strong { grid-column: 2; font-size: 13px; font-weight: 650; justify-self: start; }
    .endpoint-workspace .endpoint-detail-statuses .status-pill { border: 0; padding: 5px 12px; text-transform: none; }
    .endpoint-tags { display: flex; flex-wrap: wrap; gap: 8px; }
    .endpoint-tag { max-width: 100%; padding: 6px 10px; border: 1px solid var(--border-soft); border-radius: 20px; background: var(--surface-soft); color: var(--text-soft); font-size: 11px; line-height: 1.5; overflow-wrap: anywhere; }
    .endpoint-lists-card .endpoint-tag { max-width: 26%; overflow: hidden; white-space: nowrap; text-overflow: ellipsis; }
    .endpoint-ip-tag { display: inline-flex; align-items: center; gap: 12px; border-radius: 9px; padding: 9px 11px; font-size: 12px; }
    .endpoint-ip-tag small { padding: 2px 8px; border-radius: 10px; background: var(--surface-raised); font-size: 10px; }
    .endpoint-disclosure { border: 1px solid var(--border); border-radius: 8px; margin-top: 8px; min-width: 0; }
    .endpoint-disclosure > summary { display: flex; align-items: center; gap: 13px; padding: 14px; cursor: pointer; color: var(--text); font-size: 12px; font-weight: 550; list-style: none; }
    .endpoint-disclosure > summary::-webkit-details-marker { display: none; }
    .endpoint-disclosure > summary svg { flex-shrink: 0; color: var(--text-soft); }
    .endpoint-disclosure > summary svg:last-child { margin-left: auto; transform: rotate(90deg); transition: transform .15s; }
    .endpoint-disclosure[open] > summary svg:last-child { transform: rotate(-90deg); }
    .endpoint-disclosure-body { padding: 4px 14px 14px; }
    .endpoint-disclosure-footer { display: flex; justify-content: flex-end; margin-top: 18px; padding-top: 14px; border-top: 1px solid var(--border-soft); }
    .endpoint-disclosure-body .field-group { margin-bottom: 16px; }
    .endpoint-workspace .endpoint-detail-mono, .endpoint-workspace code { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; overflow-wrap: anywhere; }
    .endpoint-workspace .endpoint-history-card .panel-header { padding-bottom: 16px; border-bottom: 1px solid var(--border-soft); margin: 0; }
    .endpoint-event-stream { list-style: none; padding: 0; margin: 0; }
    .endpoint-event { --event-color: var(--text-muted); display: grid; grid-template-columns: 72px 100px minmax(0, 1fr); gap: 5px 10px; align-items: start; padding: 16px 0; border-bottom: 1px solid var(--border-soft); font-size: 12px; line-height: 1.65; }
    .endpoint-event:last-child { border-bottom: 0; padding-bottom: 4px; }
    .endpoint-event time { color: var(--text-soft); font-size: 11px; font-variant-numeric: tabular-nums; }
    .endpoint-event time span { display: block; }
    .endpoint-event-sync { --event-color: var(--cyan); }
    .endpoint-event-add { --event-color: var(--green); }
    .endpoint-event-remove { --event-color: var(--endpoint-orange); }
    .endpoint-event-type { color: var(--event-color); display: flex; align-items: center; gap: 10px; }
    .endpoint-event-type::before { content: ''; display: block; width: 11px; height: 11px; border-radius: 50%; background: var(--event-color); flex-shrink: 0; margin-top: 1px; }
    .endpoint-event-message { color: var(--text-soft); overflow-wrap: anywhere; }
    .endpoint-event-message a { font-weight: 400; color: inherit; }
    .endpoint-event-message a:hover { color: var(--cyan); }
    .endpoint-event-ip { grid-column: 3; color: var(--text-muted); font-size: 10px; overflow-wrap: anywhere; }
    .endpoint-drawer { box-sizing: border-box; position: fixed; inset: 76px 14px 20px auto; margin: 0; width: min(495px, calc(100% - 28px)); max-width: none; height: calc(100dvh - 96px); max-height: none; padding: 20px 18px; border: 1px solid var(--border); border-radius: 13px; background: var(--surface); color: var(--text); overflow-y: auto; box-shadow: 0 20px 65px rgb(0 0 0 / 18%); }
    .endpoint-drawer::backdrop { background: rgb(0 8 18 / 45%); }
    body:has(.endpoint-drawer[open]) { overflow: hidden; }
    .endpoint-workspace .endpoint-drawer-heading { border-bottom: 1px solid var(--border-soft); padding: 3px 4px 22px; margin-bottom: 12px; align-items: flex-start; }
    .endpoint-drawer-heading h2 { font-size: 17px !important; }
    .endpoint-dismiss { border: 0; background: transparent; color: var(--text-soft); padding: 4px; cursor: pointer; }
    .endpoint-source-name { display: flex; align-items: center; gap: 19px; }
    .endpoint-source-name > svg { color: var(--text-soft); flex-shrink: 0; width: 23px; height: 23px; }
    .endpoint-source-name .table-primary-link { color: var(--text); font-weight: 600; font-size: 12px; }
    .endpoint-workspace .endpoint-drawer .data-table { min-width: 0; table-layout: fixed; width: 100%; }
    .endpoint-drawer .data-table th { font-size: 10px; letter-spacing: .08em; color: var(--text-soft); padding: 18px 8px; }
    .endpoint-drawer .data-table td { overflow-wrap: anywhere; padding: 22px 8px; }
    .endpoint-drawer .data-table th:nth-child(2) { width: 102px; }
    .endpoint-drawer .data-table th:last-child { width: 75px; }
    .endpoint-drawer .status-pill { white-space: normal; font-size: 10px; padding: 6px 12px; border: 0; }
    .endpoint-drawer .table-action-link { color: var(--endpoint-orange); font-size: 11px; padding: 0; cursor: pointer; }
    .endpoint-drawer-footer { display: flex; align-items: center; gap: 14px; border: 1px solid var(--border); border-radius: 10px; background: var(--surface-soft); padding: 15px; margin-top: 26px; }
    .endpoint-drawer-footer svg { flex-shrink: 0; color: var(--cyan); }
    .endpoint-drawer-footer p { color: var(--text-soft); font-size: 11px; line-height: 1.8; margin: 0; }
    .endpoint-drawer-footer .button { font-size: 11px; font-weight: 500; }
    .endpoint-drawer .endpoint-history-card { padding: 0; border: 0; box-shadow: none; }
    .endpoint-ip-drawer h3 { margin: 0; font-size: 13px; font-weight: 600; }
    .endpoint-ip-access { display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 12px; padding: 16px; margin: 20px 0; border: 1px solid var(--border); border-radius: 10px; background: var(--surface-soft); }
    .endpoint-ip-access-heading { display: flex; align-items: flex-start; gap: 10px; }
    .endpoint-ip-access-heading > svg { flex-shrink: 0; color: var(--cyan); }
    .endpoint-ip-access > p { grid-column: 1 / -1; }
    .endpoint-ip-access .button { padding: 0 10px; min-height: 34px; font-weight: 500; }
    .endpoint-ip-add { padding-bottom: 24px; border-bottom: 1px solid var(--border-soft); }
    .endpoint-ip-add label { font-size: 13px; font-weight: 600; }
    .endpoint-ip-add .endpoint-ip-form { display: flex; gap: 8px; margin-top: 12px; }
    .endpoint-ip-add input { flex: 1; font-size: 11px; }
    .endpoint-ip-add .button { background: var(--green); color: #03271d; flex-shrink: 0; }
    .endpoint-ip-list-heading { display: flex; align-items: center; justify-content: space-between; margin: 22px 0 8px; }
    .endpoint-ip-list { list-style: none; margin: 0; padding: 0; }
    .endpoint-ip-entry { display: flex; align-items: center; gap: 13px; padding: 20px 0; border-bottom: 1px solid var(--border-soft); }
    .endpoint-ip-entry > svg { color: var(--text-soft); flex-shrink: 0; }
    .endpoint-ip-entry-copy { flex: 1; min-width: 0; }
    .endpoint-ip-entry-copy > strong { font-size: 13px; font-weight: 550; overflow-wrap: anywhere; }
    .endpoint-ip-entry-meta { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; margin-top: 8px; color: var(--text-soft); font-size: 10px; }
    .endpoint-ip-entry-actions { display: grid; gap: 12px; flex-shrink: 0; }
    .endpoint-ip-entry-actions button { display: inline-flex; align-items: center; gap: 6px; padding: 0; border: 0; background: transparent; cursor: pointer; font-size: 11px; }
    .endpoint-ip-approve { color: var(--green); }
    .endpoint-ip-remove { color: var(--endpoint-orange); }
    @media (max-width: 600px) { .endpoint-ip-access { grid-template-columns: minmax(0, 1fr); } }
    .endpoint-ip-form input { min-width: 0; }
    html:not([data-theme="light"]) .endpoint-workspace { --surface: #061b29; --surface-soft: #0c2231; --surface-raised: #102736; --border: #183849; --border-soft: #133040; --text-soft: #adc5d9; --text-muted: #88a5ba; --cyan: #00cbee; --green: #00dba0; }
    html:not([data-theme="light"]) .endpoint-workspace .panel { background: linear-gradient(115deg, #051a28, #041724); }
    @media (min-width: 1500px) {
        .endpoint-workspace:has(.endpoint-drawer[open]) { margin-right: 505px; }
        .endpoint-drawer::backdrop { background: transparent; }
    }
    @media (max-width: 1199px) {
        .endpoint-workspace .endpoint-detail-statuses > div { padding: 4px 10px; gap: 8px; }
        .endpoint-workspace .endpoint-detail-statuses strong { font-size: 12px; }
        .endpoint-event { grid-template-columns: 70px minmax(0, 1fr); gap: 5px 12px; }
        .endpoint-event-message, .endpoint-event-ip { grid-column: 2; }
        .endpoint-workspace .panel-header { flex-wrap: wrap; }
    }
    @media (max-width: 900px) {
        .endpoint-overview { grid-template-columns: minmax(0, 1fr); }
        .endpoint-workspace .endpoint-detail-statuses { grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 20px 0; }
        .endpoint-workspace .endpoint-detail-statuses > div { border: 0; padding: 0 8px; }
        .endpoint-workspace .page-heading { align-items: flex-start; flex-direction: column; }
        .endpoint-event { grid-template-columns: 76px 110px minmax(0, 1fr); }
        .endpoint-event-message, .endpoint-event-ip { grid-column: 3; }
    }
    @media (max-width: 600px) {
        .endpoint-workspace .panel { padding: 16px; }
        .endpoint-workspace .endpoint-detail-statuses { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .endpoint-workspace .endpoint-detail-statuses > div > span { white-space: normal; }
        .endpoint-workspace .page-actions { flex-wrap: wrap; }
        .endpoint-workspace .page-actions .button { padding: 0 12px; }
        .endpoint-breadcrumb { margin-bottom: 20px; overflow-wrap: anywhere; }
        .endpoint-drawer { inset: 0 0 0 auto; width: 100%; height: 100dvh; border-radius: 0; padding: 18px 12px; }
        .endpoint-source-name { gap: 8px; }
        .endpoint-source-name > svg { width: 18px; }
        .endpoint-drawer .data-table td { padding: 18px 4px; }
        .endpoint-drawer .data-table th:nth-child(2) { width: 80px; }
        .endpoint-drawer .data-table th:last-child { width: 64px; }
        .endpoint-event { grid-template-columns: 76px minmax(0, 1fr); }
        .endpoint-event-message, .endpoint-event-ip { grid-column: 2; }
        .endpoint-ip-form, .endpoint-secret-row { flex-wrap: wrap; }
        .endpoint-ip-drawer h3 { margin: 0; font-size: 13px; font-weight: 600; }
    .endpoint-ip-access { display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 12px; padding: 16px; margin: 20px 0; border: 1px solid var(--border); border-radius: 10px; background: var(--surface-soft); }
    .endpoint-ip-access-heading { display: flex; align-items: flex-start; gap: 10px; }
    .endpoint-ip-access-heading > svg { flex-shrink: 0; color: var(--cyan); }
    .endpoint-ip-access > p { grid-column: 1 / -1; }
    .endpoint-ip-access .button { padding: 0 10px; min-height: 34px; font-weight: 500; }
    .endpoint-ip-add { padding-bottom: 24px; border-bottom: 1px solid var(--border-soft); }
    .endpoint-ip-add label { font-size: 13px; font-weight: 600; }
    .endpoint-ip-add .endpoint-ip-form { display: flex; gap: 8px; margin-top: 12px; }
    .endpoint-ip-add input { flex: 1; font-size: 11px; }
    .endpoint-ip-add .button { background: var(--green); color: #03271d; flex-shrink: 0; }
    .endpoint-ip-list-heading { display: flex; align-items: center; justify-content: space-between; margin: 22px 0 8px; }
    .endpoint-ip-list { list-style: none; margin: 0; padding: 0; }
    .endpoint-ip-entry { display: flex; align-items: center; gap: 13px; padding: 20px 0; border-bottom: 1px solid var(--border-soft); }
    .endpoint-ip-entry > svg { color: var(--text-soft); flex-shrink: 0; }
    .endpoint-ip-entry-copy { flex: 1; min-width: 0; }
    .endpoint-ip-entry-copy > strong { font-size: 13px; font-weight: 550; overflow-wrap: anywhere; }
    .endpoint-ip-entry-meta { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; margin-top: 8px; color: var(--text-soft); font-size: 10px; }
    .endpoint-ip-entry-actions { display: grid; gap: 12px; flex-shrink: 0; }
    .endpoint-ip-entry-actions button { display: inline-flex; align-items: center; gap: 6px; padding: 0; border: 0; background: transparent; cursor: pointer; font-size: 11px; }
    .endpoint-ip-approve { color: var(--green); }
    .endpoint-ip-remove { color: var(--endpoint-orange); }
    @media (max-width: 600px) { .endpoint-ip-access { grid-template-columns: minmax(0, 1fr); } }
    .endpoint-ip-form input { flex: 1 1 100%; }
        .endpoint-secret-row input { flex-basis: 65%; }
    }
</style>
