<style>
    .profile-refined { max-width:1200px; margin:0 auto; }
    .profile-refined .profile-layout { display:grid; grid-template-columns:minmax(270px,350px) minmax(0,1fr); gap:18px; align-items:start; }
    .profile-refined .profile-sidebar { display:grid; gap:18px; }
    .profile-refined .panel { padding:22px; margin:0; box-shadow:none; }
    .profile-refined .panel-header h2 { font-size:15px; font-weight:500; }
    .profile-refined .profile-summary-card { align-items:center; }
    .profile-refined .profile-summary-card h2 { font-size:18px; line-height:1.7; overflow-wrap:anywhere; font-weight:500; }
    .profile-refined .profile-summary-card .user-avatar-large { width:58px; height:58px; border-radius:15px; flex-shrink:0; }
    .profile-refined .details-list > div { display:grid; grid-template-columns:minmax(0,1fr); gap:7px; padding:13px 0; border-bottom:1px solid var(--border-soft); }
    .profile-refined .details-list > div:last-child { border:0; }
    .profile-refined .details-list dt { font-size:12px; color:var(--text-muted); }
    .profile-refined .details-list dd { margin:0; text-align:left; font-size:13px; line-height:1.8; overflow-wrap:anywhere; }
    .profile-refined .avatar-picker { grid-template-columns:repeat(4,minmax(0,1fr)); gap:10px; }
    .profile-refined .avatar-option { gap:10px; padding:14px 8px; border-radius:9px; transition:none; }
    .profile-refined .avatar-option:hover { transform:none; }
    .profile-refined .avatar-option:focus-within { outline:2px solid var(--cyan); outline-offset:3px; }
    .profile-refined .avatar-option-label { font-size:11px; font-weight:400; white-space:normal; overflow-wrap:anywhere; line-height:1.6; }
    .profile-refined .profile-help { font-size:12px; color:var(--text-muted); line-height:1.8; margin:0 0 18px; }
    .profile-refined .profile-avatar-initial { width:44px; height:44px; border-radius:12px; display:grid; place-items:center; }
    .profile-refined.profile-password { max-width:800px; }
    .profile-refined .profile-password-form { display:grid; gap:18px; }
    .profile-refined .profile-password-form .field-group { margin:0; }
    .profile-refined .profile-password-form label { font-size:13px; }
    .profile-refined .profile-password-form .form-control { min-height:42px; font-size:14px; }
    .profile-refined .profile-password-form .profile-help { margin:8px 0 0; }
    .profile-refined .form-actions { display:flex; flex-wrap:wrap; gap:8px; margin-top:0; padding-top:18px; border-top:1px solid var(--border-soft); }
    @media(max-width:1000px) { .profile-refined .profile-layout { grid-template-columns:minmax(0,1fr); } }
    @media(max-width:600px) { .profile-refined .panel { padding:16px; } .profile-refined .avatar-picker { grid-template-columns:repeat(3,minmax(0,1fr)); } }
</style>
