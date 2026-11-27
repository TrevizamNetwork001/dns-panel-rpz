<style>
    .recursive-dns { --dns-accent: #55dfaa; max-width: 1160px; margin-inline: auto; }
    .recursive-dns .dns-heading { border-inline-start: 3px solid var(--dns-accent); padding-inline-start: 16px; }
    .recursive-dns .dns-kicker { color: var(--dns-accent); font: 12px ui-monospace, monospace; letter-spacing: .12em; }
    .recursive-dns .dns-primary { background: var(--dns-accent); border-color: var(--dns-accent); color: #072419; }
    .recursive-dns .dns-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 260px), 1fr)); gap: 18px; }
    .recursive-dns .dns-field { display: grid; align-content: start; gap: 6px; margin-bottom: 14px; }
    .recursive-dns .dns-field input, .recursive-dns .dns-field select { width: 100%; min-width: 0; }
    .recursive-dns .dns-scroll { overflow-x: auto; }
    .recursive-dns .dns-status { overflow-wrap: anywhere; }
    .recursive-dns .dns-section { margin-bottom: 18px; padding: 20px; border: 1px solid var(--border-soft); border-radius: 12px; background: var(--surface); }
    .recursive-dns .dns-section h2 { margin: 0 0 12px; font-size: 17px; }
    .recursive-dns .dns-section dt { color: var(--text-soft); font-size: 12px; }
    .recursive-dns .dns-section dd { margin: 0; }
</style>
