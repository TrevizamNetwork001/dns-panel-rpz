<style>
    .cliente-rpz-health { margin-top: 16px; padding: 22px; }
    .cliente-health-heading, .cliente-health-title, .cliente-health-subheading > div { display: flex; align-items: center; }
    .cliente-health-heading { justify-content: space-between; gap: 16px; margin-bottom: 20px; }
    .cliente-health-title { gap: 15px; }
    .cliente-health-title > svg { color: var(--cyan); flex: 0 0 31px; width: 31px; height: 31px; align-self: center; }
    .cliente-health-title > svg, .cliente-health-metric-icon svg, .cliente-health-subheading svg { display: block; }
    .cliente-health-title h2 { margin: 0; font-size: 17px; }
    .cliente-health-title p { margin: 4px 0 0; color: var(--text-soft); font-size: 11px; }
    .cliente-health-metrics { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 10px; }
    .cliente-health-metric { display: grid; grid-template-columns: 42px minmax(0, 1fr); grid-template-rows: 1fr auto; align-items: center; align-content: center; gap: 4px 10px; min-width: 0; padding: 13px; border: 1px solid var(--border-soft); border-radius: 10px; background: var(--surface-soft); }
    .cliente-health-metric-icon { display: grid; grid-row: 1 / -1; place-items: center; align-self: center; justify-self: center; width: 42px; height: 42px; border-radius: 10px; background: var(--cyan-soft); color: var(--cyan); }
    .cliente-health-metric-icon.is-green { background: rgba(53, 211, 154, .1); color: var(--green); }
    .cliente-health-metric > div { min-width: 0; }
    .cliente-health-metric strong, .cliente-health-metric span { display: block; overflow-wrap: anywhere; }
    .cliente-health-metric strong { font-size: 16px; line-height: 1.25; }
    .cliente-health-metric div span { margin-top: 3px; color: var(--text-soft); font-size: 10px; }
    .cliente-health-metric small { grid-column: 2; color: var(--text-muted); font-size: 9px; line-height: 1.35; }
    .cliente-health-metric > .cliente-health-metric-icon { display: grid; overflow: visible; }
    .cliente-health-panels { display: grid; grid-template-columns: minmax(0, 1.35fr) minmax(300px, .9fr); gap: 12px; margin-top: 12px; }
    .cliente-health-chart, .cliente-health-status { min-width: 0; padding: 15px; border: 1px solid var(--border-soft); border-radius: 10px; background: var(--surface-soft); }
    .cliente-health-chart .panel { margin: 0 !important; padding: 0; border: 0; box-shadow: none; background: transparent; }
    .cliente-health-chart .panel-header { margin-bottom: 12px; padding: 0 0 10px; }
    .cliente-health-chart .panel-header h2 { font-size: 13px; }
    .cliente-health-chart .panel-header .status-pill { display: none; }
    .cliente-health-chart > .panel > p { font-size: 10px !important; }
    .cliente-health-chart svg { min-height: 160px; }
    .cliente-health-subheading { display: flex; align-items: center; justify-content: space-between; gap: 10px; padding-bottom: 12px; border-bottom: 1px solid var(--border-soft); }
    .cliente-health-subheading > div { gap: 10px; min-width: 0; }
    .cliente-health-subheading svg { color: var(--cyan); flex: 0 0 22px; width: 22px; height: 22px; align-self: center; }
    .cliente-health-subheading h3 { margin: 0; font-size: 13px; }
    .cliente-health-subheading > span { color: var(--text-muted); font-size: 10px; }
    .cliente-status-summary { display: flex; align-items: center; gap: 16px; padding: 16px 0 12px; }
    .cliente-status-summary > div { display: grid; gap: 3px; }
    .cliente-status-summary strong { color: var(--text); font-size: 25px; line-height: 1; }
    .cliente-status-summary span { color: var(--text-soft); font-size: 11px; }
    .cliente-status-summary small { color: var(--text-muted); font-size: 9px; }
    .cliente-status-donut { display: block; width: 86px; height: 86px; transform: rotate(-90deg); flex: 0 0 86px; }
    .cliente-status-donut circle { fill: none; stroke-width: 9; }
    .cliente-status-donut-track { stroke: var(--border); }
    .cliente-status-donut-value { stroke: var(--green); stroke-linecap: round; }
    .cliente-status-donut text { fill: var(--text); font-size: 19px; font-weight: 700; transform: rotate(90deg); transform-origin: 50px 50px; }
    .cliente-status-donut .cliente-status-donut-label { fill: var(--text-muted); font-size: 9px; font-weight: 500; }
    .cliente-status-list { display: grid; gap: 7px; max-height: 260px; overflow-y: auto; }
    .cliente-status-item { display: grid; grid-template-columns: 9px minmax(0, 1fr) auto; align-items: center; gap: 10px; padding: 10px; border: 1px solid var(--border-soft); border-radius: 8px; color: var(--text); }
    .cliente-status-item:hover { border-color: var(--cyan); background: var(--cyan-soft); }
    .cliente-status-dot { width: 9px; height: 9px; border-radius: 50%; background: var(--text-muted); }
    .cliente-status-dot.is-active { background: var(--green); }
    .cliente-status-dot.is-warning { background: var(--amber); }
    .cliente-status-item strong, .cliente-status-item small { display: block; overflow-wrap: anywhere; }
    .cliente-status-item strong { font-size: 11px; }
    .cliente-status-item small { margin-top: 3px; color: var(--text-muted); font-size: 9px; }
    .cliente-status-item .status-pill { font-size: 9px; white-space: nowrap; }
    @media (max-width: 1050px) { .cliente-health-metrics { grid-template-columns: repeat(2, minmax(0, 1fr)); } .cliente-health-panels { grid-template-columns: minmax(0, 1fr); } }
    @media (max-width: 600px) { .cliente-rpz-health { padding: 16px; } .cliente-health-heading { align-items: flex-start; flex-direction: column; } .cliente-health-metrics { grid-template-columns: minmax(0, 1fr); } .cliente-health-title h2 { font-size: 15px; } .cliente-status-item { grid-template-columns: 9px minmax(0, 1fr); } .cliente-status-item .status-pill { grid-column: 2; justify-self: start; } }
</style>
