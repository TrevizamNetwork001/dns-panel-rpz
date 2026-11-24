<style>
    .admin-empresa-form { max-width: 1120px; margin: 0 auto; }
    .empresa-form-breadcrumb { display: flex; align-items: center; gap: 12px; margin-bottom: 24px; color: var(--text-soft); font-size: 12px; overflow-wrap: anywhere; }
    .empresa-form-breadcrumb a { color: var(--cyan); }
    .admin-empresa-form .page-eyebrow { color: var(--cyan); font-size: 11px; letter-spacing: .14em; }
    .admin-empresa-form h1 { margin: 8px 0; font-size: 32px; letter-spacing: -.035em; }
    .admin-empresa-form .page-heading p { margin: 0; color: var(--text-soft); font-size: 13px; }
    .admin-empresa-form > form { display: grid; grid-template-columns: minmax(0, 1fr); gap: 18px; align-items: start; }
    .admin-empresa-form .form-panel { min-width: 0; padding: 22px; margin-bottom: 0 !important; border-radius: 13px; box-shadow: none; }
    .admin-empresa-form .panel-header { padding-bottom: 18px; margin-bottom: 20px; border-color: var(--border-soft); }
    .admin-empresa-form .panel-header h2 { display: flex; align-items: center; gap: 12px; margin: 0; font-size: 15px; }
    .admin-empresa-form .panel-header h2 > svg { color: var(--cyan); }
    .admin-empresa-form .form-grid { gap: 20px 18px; }
    .admin-empresa-form .empresa-extra-contatos { align-self: end; min-width: 0; }
    .admin-empresa-form .empresa-contatos-drawer {
        box-sizing: border-box;
        position: fixed;
        inset: 76px 14px 20px auto;
        margin: 0;
        width: min(495px, calc(100% - 28px));
        max-width: none;
        height: calc(100dvh - 96px);
        max-height: none;
        padding: 20px 18px;
        overflow-y: auto;
        border: 1px solid var(--border);
        border-radius: 13px;
        background: var(--surface);
        color: var(--text);
        box-shadow: 0 20px 65px rgb(0 0 0 / 18%);
    }
    .empresa-contatos-drawer::backdrop { background: rgb(0 8 18 / 45%); }
    body:has(.empresa-contatos-drawer[open]) { overflow: hidden; }
    .admin-empresa-form .empresa-contatos-drawer .panel-header { border-bottom: 1px solid var(--border-soft); }
    .empresa-contatos-drawer textarea { min-height: 110px; resize: vertical; }
    .empresa-contatos-actions { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 12px; margin-top: 20px; }
    .empresa-contatos-actions small { color: var(--text-soft); }
    @media (max-width: 600px) {
        .admin-empresa-form .empresa-contatos-drawer { inset: 0 0 0 auto; width: 100%; height: 100dvh; border-radius: 0; }
    }
    .admin-empresa-form .form-control { min-height: 46px; padding: 12px 14px; border: 1px solid var(--border); border-radius: 9px; background: var(--surface-soft); color: var(--text); font-size: 13px; }
    .admin-empresa-form .form-control:focus { border-color: var(--cyan); box-shadow: 0 0 0 3px var(--cyan-soft); }
    .admin-empresa-form .form-actions { justify-content: flex-end; flex-wrap: wrap; position: static; display: flex; gap: 10px; padding: 0; margin: 18px 0 0; border: 0; border-radius: 0; background: transparent; box-shadow: none; }
    .admin-empresa-form .form-actions .button { min-height: 42px; padding: 0 20px; font-size: 12px; }
    html:not([data-theme="light"]) .admin-empresa-form { --surface: #061b29; --surface-soft: #0b2130; --border: #234151; --border-soft: #173444; --text-soft: #adc5d9; --cyan: #00cbee; }
    @media (max-width: 800px) { .admin-empresa-form > form { grid-template-columns: minmax(0, 1fr); } }
    @media (max-width: 600px) { .admin-empresa-form h1 { font-size: 27px; } .admin-empresa-form .page-heading { align-items: flex-start; } .admin-empresa-form .form-panel { padding: 18px; } .admin-empresa-form .form-actions { margin-top: 16px; }  }
</style>
