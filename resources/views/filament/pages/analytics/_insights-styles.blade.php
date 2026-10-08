{{--
    Agent 04: styles for the Audience Insights / Journeys / Audiences /
    Downloads admin pages. Plain CSS (scoped "afs-ins-") for the same reason
    as _panel-styles: Filament's compiled bundle doesn't contain arbitrary
    Tailwind classes used in this app's own page views. Light + dark.
--}}
<style>
    .afs-ins { --ins-ink: #0b1e3d; --ins-muted: #5b6478; --ins-faint: #8a93a8; --ins-line: #e4e8f1; --ins-card: #fff; --ins-soft: #f5f7fb; --ins-brand: #2452d9; --ins-brand-soft: #e8eeff; --ins-gold: #c9a24a; --ins-good: #047857; --ins-good-soft: #ecfdf5; --ins-warn: #b45309; --ins-warn-soft: #fffbeb; display: flex; flex-direction: column; gap: 1.25rem; color: var(--ins-ink); }
    .dark .afs-ins { --ins-ink: #f3f5fa; --ins-muted: #b7bfd1; --ins-faint: #8a93a8; --ins-line: rgba(255,255,255,.1); --ins-card: rgba(255,255,255,.03); --ins-soft: rgba(255,255,255,.05); --ins-brand: #7b9bff; --ins-brand-soft: rgba(36,82,217,.22); --ins-good: #6ee7b7; --ins-good-soft: rgba(16,185,129,.12); --ins-warn: #fcd34d; --ins-warn-soft: rgba(245,158,11,.12); }

    .afs-ins-hero { position: relative; overflow: hidden; border-radius: 1rem; padding: 1.5rem; background: linear-gradient(135deg, #0b1e3d 0%, #142752 55%, #2452d9 120%); color: #fff; }
    .afs-ins-hero::after { content: ""; position: absolute; right: -4rem; top: -5rem; width: 16rem; height: 16rem; border-radius: 999px; background: radial-gradient(circle, rgba(201,162,74,.45), transparent 70%); pointer-events: none; }
    .afs-ins-hero-row { position: relative; z-index: 1; display: flex; flex-wrap: wrap; align-items: flex-end; justify-content: space-between; gap: 1rem; }
    .afs-ins-eyebrow { font-size: .6875rem; letter-spacing: .16em; text-transform: uppercase; color: #e0b563; font-weight: 600; }
    .afs-ins-hero h2 { margin: .35rem 0 0; font-size: 1.375rem; font-weight: 700; line-height: 1.3; }
    .afs-ins-hero p { margin: .4rem 0 0; max-width: 44rem; font-size: .875rem; line-height: 1.55; color: rgba(255,255,255,.78); }

    .afs-ins-filter { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem; }
    .afs-ins-chip { border: 1px solid rgba(255,255,255,.22); background: rgba(255,255,255,.08); color: #fff; border-radius: 999px; padding: .375rem .8rem; font-size: .8125rem; font-weight: 500; cursor: pointer; line-height: 1.25rem; }
    .afs-ins-chip:hover { background: rgba(255,255,255,.16); }
    .afs-ins-chip.is-active { background: #fff; color: #0b1e3d; border-color: #fff; }
    .afs-ins-chip:focus-visible, .afs-ins-btn:focus-visible, .afs-ins-input:focus-visible { outline: 2px solid #e0b563; outline-offset: 2px; }
    .afs-ins-dates { display: flex; align-items: center; gap: .375rem; font-size: .8125rem; color: rgba(255,255,255,.8); }
    .afs-ins-dates input { background: rgba(255,255,255,.1); border: 1px solid rgba(255,255,255,.22); color: #fff; border-radius: .5rem; padding: .3rem .5rem; font-size: .8125rem; color-scheme: dark; }

    .afs-ins-note { display: flex; gap: .75rem; align-items: flex-start; border-radius: .875rem; padding: 1rem 1.125rem; background: var(--ins-brand-soft); border: 1px solid color-mix(in srgb, var(--ins-brand) 25%, transparent); font-size: .8125rem; line-height: 1.55; color: var(--ins-ink); }
    .afs-ins-note svg { width: 1.25rem; height: 1.25rem; flex-shrink: 0; color: var(--ins-brand); margin-top: .1rem; }
    .afs-ins-note--warn { background: var(--ins-warn-soft); border-color: color-mix(in srgb, var(--ins-warn) 30%, transparent); }
    .afs-ins-note--warn svg { color: var(--ins-warn); }

    .afs-ins-grid { display: grid; gap: 1.25rem; }
    .afs-ins-kpis { display: grid; gap: .875rem; grid-template-columns: repeat(2, minmax(0, 1fr)); }
    @media (min-width: 768px) { .afs-ins-kpis { grid-template-columns: repeat(4, minmax(0, 1fr)); } .afs-ins-grid--2 { grid-template-columns: repeat(2, minmax(0, 1fr)); } .afs-ins-grid--3 { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
    @media (min-width: 768px) { .afs-ins-kpis--6 { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
    @media (min-width: 1600px) { .afs-ins-kpis--6 { grid-template-columns: repeat(6, minmax(0, 1fr)); } }

    .afs-ins-card { border-radius: 1rem; border: 1px solid var(--ins-line); background: var(--ins-card); padding: 1.25rem; min-width: 0; box-shadow: 0 1px 2px rgba(11,30,61,.04); }
    .afs-ins-card-head { display: flex; align-items: flex-start; justify-content: space-between; gap: .75rem; margin-bottom: .875rem; }
    .afs-ins-card-title { display: flex; align-items: center; gap: .5rem; font-size: .9375rem; font-weight: 650; margin: 0; }
    .afs-ins-card-title svg { width: 1.125rem; height: 1.125rem; color: var(--ins-brand); flex-shrink: 0; }
    .afs-ins-card-sub { font-size: .75rem; color: var(--ins-faint); margin: .15rem 0 0; }
    .afs-ins-explain { margin: 1rem 0 0; padding-top: .75rem; border-top: 1px dashed var(--ins-line); font-size: .78rem; line-height: 1.5; color: var(--ins-muted); }
    .afs-ins-explain strong { color: var(--ins-ink); }

    .afs-ins-kpi { border-radius: 1rem; border: 1px solid var(--ins-line); background: var(--ins-card); padding: 1rem 1.125rem; }
    .afs-ins-kpi-label { display: flex; align-items: center; gap: .4rem; font-size: .6875rem; font-weight: 650; letter-spacing: .06em; text-transform: uppercase; color: var(--ins-faint); }
    .afs-ins-kpi-label svg { width: .95rem; height: .95rem; color: var(--ins-brand); }
    .afs-ins-kpi-value { margin-top: .35rem; font-size: 1.625rem; font-weight: 700; line-height: 1.1; font-variant-numeric: tabular-nums; }
    .afs-ins-kpi-hint { margin-top: .3rem; font-size: .75rem; color: var(--ins-muted); }

    .afs-ins-chart { width: 100%; height: auto; display: block; }
    .afs-ins-legend { display: flex; flex-wrap: wrap; gap: .875rem; font-size: .75rem; color: var(--ins-muted); }
    .afs-ins-legend span { display: inline-flex; align-items: center; gap: .375rem; }
    .afs-ins-swatch { width: .625rem; height: .625rem; border-radius: 3px; display: inline-block; }

    .afs-ins-bars { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: .625rem; }
    .afs-ins-bar-row { display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: .25rem .75rem; align-items: center; font-size: .8125rem; }
    .afs-ins-bar-label { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; color: var(--ins-ink); }
    .afs-ins-bar-num { font-variant-numeric: tabular-nums; color: var(--ins-muted); font-size: .78rem; }
    .afs-ins-bar-num b { color: var(--ins-ink); font-weight: 650; }
    .afs-ins-bar-track { grid-column: 1 / -1; height: .5rem; border-radius: 999px; background: var(--ins-soft); overflow: hidden; }
    .afs-ins-bar-fill { display: block; height: 100%; border-radius: 999px; }

    .afs-ins-donut-wrap { display: flex; align-items: center; gap: 1.25rem; flex-wrap: wrap; }
    .afs-ins-donut { width: 8.5rem; height: 8.5rem; flex-shrink: 0; }

    .afs-ins-funnel { display: flex; flex-direction: column; gap: .625rem; }
    .afs-ins-funnel-step { display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: .35rem .75rem; align-items: center; font-size: .8125rem; }
    .afs-ins-funnel-step .afs-ins-funnel-bar { grid-column: 1 / -1; grid-row: 2; }
    .afs-ins-funnel-bar { height: 2.25rem; border-radius: .625rem; background: var(--ins-soft); overflow: hidden; }
    .afs-ins-funnel-fill { height: 100%; border-radius: .625rem; display: flex; align-items: center; padding: 0 .75rem; color: #fff; font-weight: 650; font-variant-numeric: tabular-nums; min-width: 2.5rem; background: linear-gradient(90deg, #2452d9, #4f75ec); }
    .afs-ins .sr-only { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0, 0, 0, 0); white-space: nowrap; border: 0; }
    .afs-ins-funnel-rate { text-align: right; font-size: .75rem; color: var(--ins-muted); }
    .afs-ins-funnel-rate b { color: var(--ins-ink); }

    .afs-ins-table-wrap { overflow-x: auto; margin: 0 -.25rem; }
    .afs-ins-table { width: 100%; border-collapse: collapse; font-size: .8125rem; }
    .afs-ins-table th { text-align: left; font-size: .6875rem; font-weight: 650; text-transform: uppercase; letter-spacing: .05em; color: var(--ins-faint); padding: 0 .5rem .5rem; border-bottom: 1px solid var(--ins-line); white-space: nowrap; }
    .afs-ins-table td { padding: .55rem .5rem; border-bottom: 1px solid var(--ins-line); vertical-align: top; }
    .afs-ins-table tr:last-child td { border-bottom: none; }
    .afs-ins-num { text-align: right !important; font-variant-numeric: tabular-nums; white-space: nowrap; }
    .afs-ins-mono { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: .75rem; }

    .afs-ins-empty { display: flex; flex-direction: column; align-items: center; gap: .375rem; padding: 1.5rem .5rem; text-align: center; font-size: .8125rem; color: var(--ins-faint); }
    .afs-ins-empty svg { width: 1.75rem; height: 1.75rem; opacity: .6; }

    .afs-ins-list { margin: 0; padding-left: 1.1rem; display: flex; flex-direction: column; gap: .35rem; font-size: .8125rem; line-height: 1.5; color: var(--ins-muted); }
    .afs-ins-pill { display: inline-flex; align-items: center; gap: .25rem; border-radius: 999px; padding: .15rem .6rem; font-size: .72rem; font-weight: 600; background: var(--ins-soft); color: var(--ins-muted); white-space: nowrap; }
    .afs-ins-pill--good { background: var(--ins-good-soft); color: var(--ins-good); }
    .afs-ins-pill--brand { background: var(--ins-brand-soft); color: var(--ins-brand); }
    .afs-ins-pill--warn { background: var(--ins-warn-soft); color: var(--ins-warn); }

    .afs-ins-btn { display: inline-flex; align-items: center; justify-content: center; gap: .4rem; border-radius: .625rem; padding: .5rem .875rem; font-size: .8125rem; font-weight: 600; border: 1px solid var(--ins-line); background: var(--ins-card); color: var(--ins-ink); text-decoration: none; cursor: pointer; white-space: nowrap; }
    .afs-ins-btn:hover { border-color: var(--ins-brand); color: var(--ins-brand); }
    .afs-ins-btn svg { width: 1rem; height: 1rem; }
    .afs-ins-btn--primary { background: var(--ins-brand); border-color: var(--ins-brand); color: #fff; }
    .afs-ins-btn--primary:hover { color: #fff; filter: brightness(1.08); }
    .afs-ins-input { width: 100%; border-radius: .625rem; border: 1px solid var(--ins-line); background: var(--ins-card); color: var(--ins-ink); padding: .55rem .75rem; font-size: .875rem; }

    .afs-ins-stages { display: grid; gap: .5rem; grid-template-columns: repeat(2, minmax(0, 1fr)); }
    @media (min-width: 768px) { .afs-ins-stages { grid-template-columns: repeat(4, minmax(0, 1fr)); } }
    .afs-ins-stage { border-radius: .75rem; padding: .75rem .875rem; background: var(--ins-soft); }
    .afs-ins-stage-value { font-size: 1.25rem; font-weight: 700; font-variant-numeric: tabular-nums; }
    .afs-ins-stage-label { font-size: .75rem; color: var(--ins-muted); margin-top: .1rem; }

    .afs-ins-timeline { list-style: none; margin: 0; padding: 0; position: relative; }
    .afs-ins-timeline::before { content: ""; position: absolute; left: .9rem; top: .25rem; bottom: .25rem; width: 2px; background: var(--ins-line); }
    .afs-ins-tl-item { position: relative; display: grid; grid-template-columns: 1.8rem minmax(0, 1fr); gap: .75rem; padding: .45rem 0; }
    .afs-ins-tl-dot { position: relative; z-index: 1; width: 1.8rem; height: 1.8rem; border-radius: 999px; display: flex; align-items: center; justify-content: center; background: var(--ins-card); border: 2px solid var(--ins-line); color: var(--ins-muted); }
    .afs-ins-tl-dot svg { width: .9rem; height: .9rem; }
    .afs-ins-tl-dot--conversion { border-color: #059669; color: #059669; }
    .afs-ins-tl-dot--feature { border-color: #7c3aed; color: #7c3aed; }
    .afs-ins-tl-dot--session { border-color: var(--ins-brand); color: var(--ins-brand); background: var(--ins-brand-soft); }
    .afs-ins-tl-dot--consent { border-color: var(--ins-gold); color: var(--ins-gold); }
    .afs-ins-tl-title { font-size: .8125rem; font-weight: 600; }
    .afs-ins-tl-meta { font-size: .75rem; color: var(--ins-faint); margin-top: .1rem; overflow-wrap: anywhere; }
    .afs-ins-dl { display: grid; grid-template-columns: auto minmax(0, 1fr); gap: .4rem 1rem; font-size: .8125rem; margin: 0; }
    .afs-ins-dl dt { color: var(--ins-faint); }
    .afs-ins-dl dd { margin: 0; overflow-wrap: anywhere; }
</style>
