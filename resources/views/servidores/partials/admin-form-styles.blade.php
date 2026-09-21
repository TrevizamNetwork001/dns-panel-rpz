<style>
    .admin-endpoint-form { max-width: 1460px; margin: 0 auto; }
    .endpoint-form-breadcrumb { display: flex; align-items: center; gap: 12px; margin-bottom: 24px; color: var(--text-soft); font-size: 12px; overflow-wrap: anywhere; }
    .endpoint-form-breadcrumb a { color: var(--cyan); }
    .admin-endpoint-form .page-heading { align-items: center; gap: 18px; margin-bottom: 26px; }
    .admin-endpoint-form .page-eyebrow { color: var(--cyan); font-size: 11px; letter-spacing: .14em; }
    .admin-endpoint-form h1 { margin: 8px 0; font-size: 32px; letter-spacing: -.035em; }
    .admin-endpoint-form .page-heading p { color: var(--text-soft); font-size: 13px; overflow-wrap: anywhere; }
    .admin-endpoint-form > form { display: grid; grid-template-columns: minmax(0, 1.1fr) minmax(0, 1fr); align-items: start; gap: 20px; }
    .admin-endpoint-form .server-settings { min-width: 0; display: grid; gap: 18px; }
    .admin-endpoint-form .form-panel { max-width: none; min-width: 0; padding: 22px; margin-bottom: 0 !important; border-radius: 13px; box-shadow: none; }
    .admin-endpoint-form .panel-header { padding-bottom: 18px; margin-bottom: 20px; border-color: var(--border-soft); gap: 14px; flex-wrap: wrap; }
    .admin-endpoint-form .panel-header h2 { display: flex; align-items: center; gap: 12px; margin: 0; font-size: 15px; }
    .admin-endpoint-form .panel-header h2 > svg { color: var(--cyan); flex-shrink: 0; }
    .admin-endpoint-form .form-grid { gap: 20px 18px; }
    .admin-endpoint-form .field-group { min-width: 0; }
    .admin-endpoint-form .field-group label { font-size: 12px; font-weight: 550; }
    .admin-endpoint-form .form-control { min-height: 46px; padding: 12px 14px; border: 1px solid var(--border); border-radius: 9px; color: var(--text); background: var(--surface-soft); font-size: 13px; }
    .admin-endpoint-form .form-control:focus { border-color: var(--cyan); box-shadow: 0 0 0 3px var(--cyan-soft); }
    .admin-endpoint-form select option { background: var(--surface); color: var(--text); }
    .admin-endpoint-form .endpoint-form-note { font-size: 11px; line-height: 1.7; padding: 12px 14px; margin: 18px 0 0; border-radius: 9px; background: var(--surface-soft); border: 1px solid var(--border-soft); color: var(--text-soft); }
    .admin-endpoint-form .server-dns-notice { font-size: 11px; line-height: 1.6; }
    .admin-endpoint-form .endpoint-secret-row { flex-wrap: wrap; }
    .admin-endpoint-form .endpoint-secret-row .endpoint-code-field { flex-basis: 55%; min-height: 43px; border-radius: 9px; }
    .admin-endpoint-form .endpoint-secret-row .button { min-height: 36px; padding: 0 10px; font-weight: 500; color: var(--cyan); }
    .admin-endpoint-form .endpoint-sources-heading { display: flex; flex-wrap: wrap; justify-content: flex-start; gap: 12px; width: 100%; }
    .admin-endpoint-form .endpoint-selection-action { font-size: 11px; color: var(--cyan); font-weight: 500; }
    .admin-endpoint-form #fontes-selecionadas-count { border: 0; padding: 5px 9px; border-radius: 20px; background: rgba(53, 211, 154, .09); color: var(--green); margin-left: auto; font-size: 11px; }
    .admin-endpoint-form .endpoint-source-help { font-size: 12px; color: var(--text-soft); margin: 0 0 14px; }
    .admin-endpoint-form .endpoint-source-search { display: flex; align-items: center; position: relative; margin-bottom: 16px; }
    .admin-endpoint-form .endpoint-source-search > svg { position: absolute; left: 14px; color: var(--text-muted); }
    .admin-endpoint-form .endpoint-source-search input { padding-left: 42px; }
    .admin-endpoint-form .endpoint-source-options { display: grid; gap: 9px; max-height: 620px; overflow-y: auto; padding: 2px; }
    .admin-endpoint-form .checkbox-label { cursor: pointer; gap: 12px; padding: 15px 12px; border-radius: 10px; background: var(--surface-soft); border-color: var(--border); transition: border-color .15s, background .15s; }
    .admin-endpoint-form .checkbox-label[hidden] { display: none !important; }
    .admin-endpoint-form .checkbox-label:has(input:checked) { border-color: var(--green); background: rgba(53, 211, 154, .06); }
    .admin-endpoint-form .checkbox-label input { accent-color: var(--green); width: 17px; height: 17px; margin: 0; flex-shrink: 0; }
    .admin-endpoint-form .checkbox-label > svg { width: 23px; height: 23px; flex-shrink: 0; }
    .admin-endpoint-form .checkbox-label > div { min-width: 0; overflow-wrap: anywhere; }
    .admin-endpoint-form .checkbox-label strong { color: var(--text); font-size: 12px; font-weight: 600; }
    .admin-endpoint-form .checkbox-label small { color: var(--text-soft); font-size: 10px; line-height: 1.5; }
    .admin-endpoint-form > form > .form-actions { grid-column: 1 / -1; position: sticky; bottom: 12px; z-index: 5; justify-content: flex-end; flex-wrap: wrap; padding: 16px 20px; margin: 0; border: 1px solid var(--border); border-radius: 12px; background: var(--surface); box-shadow: 0 8px 30px rgb(0 0 0 / 12%); }
    .admin-endpoint-form .form-actions .button { min-height: 42px; padding: 0 20px; font-size: 12px; }
    html:not([data-theme="light"]) .admin-endpoint-form { --surface: #061b29; --surface-soft: #0b2130; --border: #234151; --border-soft: #173444; --text-soft: #adc5d9; --text-muted: #88a5ba; --cyan: #00cbee; --green: #00dba0; }
    @media (max-width: 1050px) { .admin-endpoint-form > form { grid-template-columns: minmax(0, 1fr); } }
    @media (max-width: 600px) {
        .admin-endpoint-form .page-heading { align-items: flex-start; flex-direction: column; }
        .admin-endpoint-form h1 { font-size: 27px; }
        .admin-endpoint-form .form-panel { padding: 18px; }
        .admin-endpoint-form .form-grid { grid-template-columns: minmax(0, 1fr); }
        .admin-endpoint-form #fontes-selecionadas-count { margin-left: 0; width: auto; }
        .admin-endpoint-form > form > .form-actions { bottom: 8px; padding: 12px; }
    }
</style>
