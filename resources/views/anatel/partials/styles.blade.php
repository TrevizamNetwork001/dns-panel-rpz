<style>
.admin-anatel{max-width:1400px;margin:0 auto;min-width:0}
.admin-anatel .page-heading{align-items:center;gap:16px;margin-bottom:24px}
.admin-anatel .page-heading h1{font-size:28px;letter-spacing:-.03em;overflow-wrap:anywhere}
.admin-anatel .page-heading p,.anatel-help{font-size:13px;color:var(--text-soft);line-height:1.6}
.anatel-navigation{display:flex;flex-wrap:wrap;align-items:center;gap:10px;margin-bottom:22px;font-size:12px;color:var(--text-soft)}
.anatel-navigation a{color:var(--text-soft);overflow-wrap:anywhere}
.anatel-navigation a:hover,.anatel-navigation a[aria-current]{color:var(--cyan)}
.anatel-source-links{display:flex;flex-wrap:wrap;gap:16px;margin-left:auto}
.anatel-steps{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;padding:0;margin:0 0 24px;list-style:none}
.anatel-steps li{display:flex;align-items:center;gap:12px;padding:16px;border:1px solid var(--border-soft);border-radius:12px;background:var(--surface);color:var(--text-soft)}
.anatel-steps li[aria-current]{border-color:var(--cyan);background:var(--cyan-soft);color:var(--text)}
.anatel-steps li>span{display:grid;place-items:center;flex-shrink:0;width:30px;height:30px;border:1px solid var(--border);border-radius:50%;font-size:12px;color:var(--cyan)}
.anatel-steps strong{display:block;font-size:13px;font-weight:600}.anatel-steps small{display:block;margin-top:4px;font-size:11px;color:var(--text-soft);line-height:1.5}
.admin-anatel .panel{padding:22px;border-radius:13px;box-shadow:none;min-width:0;margin-bottom:20px}
.admin-anatel .panel-header{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:16px}
.admin-anatel .panel-header h2,.admin-anatel .panel>h2{font-size:15px;letter-spacing:-.015em}
.admin-anatel .panel-header>div:has(>.button),.admin-anatel .panel-header>div:has(>form){display:flex;flex-wrap:wrap;align-items:center;gap:8px}
.admin-anatel .form-grid{gap:20px;margin-bottom:20px}.admin-anatel .form-control{max-width:100%;min-height:42px;border-radius:9px;background:var(--surface-soft);color:var(--text)}
.admin-anatel label{display:block;margin:14px 0 8px;font-size:12px;font-weight:600}
.admin-anatel .field-group label{margin-top:0}.admin-anatel .panel>form>.button{margin-top:16px}
.admin-anatel input[type=file]{padding:12px}.admin-anatel input::file-selector-button{border:1px solid var(--border);border-radius:6px;padding:7px 10px;margin-right:12px;background:var(--surface-raised);color:var(--text);cursor:pointer}
.anatel-filters{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:18px}.anatel-filters .button{min-height:34px;padding:7px 12px;font-size:12px;border-radius:8px}
.admin-anatel .table-responsive{max-width:100%;overflow-x:auto}.admin-anatel .data-table th{padding:12px;font-size:11px;white-space:nowrap}.admin-anatel .data-table td{padding:14px 12px;font-size:12px;vertical-align:middle}.admin-anatel .data-table tbody tr:hover{background:var(--surface-soft)}
.admin-anatel .data-table td:has(details){min-width:250px}.admin-anatel details[open]{display:block!important;padding:14px;border:1px solid var(--border);border-radius:10px;margin-bottom:10px;background:var(--surface-soft)}
.admin-anatel details form{min-width:200px;max-width:420px}.admin-anatel details form .button{margin-top:12px}.admin-anatel .data-table .button{min-height:32px;font-size:12px}
.anatel-progress-track{height:8px;background:var(--surface-raised);border-radius:8px;overflow:hidden}.anatel-progress-fill{height:100%;background:var(--cyan);transition:width .4s}.anatel-progress-results{margin-top:16px;overflow-wrap:anywhere;line-height:1.7;font-size:13px}
.anatel-sr-only{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0}
@media(max-width:1000px){.anatel-steps{gap:8px}.anatel-steps li{padding:12px}.admin-anatel .details-grid{grid-template-columns:1fr}}
@media(max-width:640px){.anatel-steps{grid-template-columns:1fr}.anatel-steps li{padding:12px}.admin-anatel .page-heading{align-items:flex-start;flex-direction:column}.admin-anatel .page-heading h1{font-size:23px}.admin-anatel .panel{padding:16px}.admin-anatel .form-grid{grid-template-columns:1fr}.admin-anatel .panel-header{align-items:flex-start}.admin-anatel .panel-header>div:has(>form){width:100%}.anatel-source-links{margin-left:0;width:100%}.admin-anatel .metrics-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.admin-anatel .metric-value{font-size:26px}}
@media(prefers-reduced-motion:reduce){.anatel-progress-fill{transition:none}}
</style>
