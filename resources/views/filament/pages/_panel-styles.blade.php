{{--
    Shared layout CSS for hand-authored Filament admin page views in this
    app. Filament's own compiled CSS bundle (public/css/filament/filament/
    app.css) doesn't include arbitrary Tailwind utility classes used in
    custom page views — there's no ->viteTheme() build scanning this app's
    own Filament blade files (confirmed while building the Documentation
    cluster pages), so raw classes like "rounded-xl" or "items-center"
    silently render as unstyled text. This ships plain CSS instead, which
    always works regardless of any build pipeline. Scoped under "afs-panel-"
    so it never collides with Filament's own classes; safe to @include on
    any custom page view more than once (a duplicate <style> block is
    harmless).
--}}
<style>
    .afs-panel-hero {
        display: flex;
        align-items: flex-start;
        gap: 1rem;
        border-radius: 0.75rem;
        border: 1px solid #e5e7eb;
        background: #f9fafb;
        padding: 1.25rem;
        margin-bottom: 1.5rem;
    }

    /* Modifier for a hero that also needs a control (e.g. Analytics\Overview's
       period switcher) pinned to the far side — kept separate from the base
       class so existing two-child heroes (icon + text) don't shift layout. */
    .afs-panel-hero--split {
        justify-content: space-between;
        flex-wrap: wrap;
    }

    .afs-panel-hero-icon {
        display: flex;
        flex-shrink: 0;
        align-items: center;
        justify-content: center;
        width: 3rem;
        height: 3rem;
        border-radius: 9999px;
        background: #dbeafe;
        color: #2563eb;
    }

    .afs-panel-hero-icon svg {
        width: 1.5rem;
        height: 1.5rem;
    }

    .afs-panel-hero p {
        margin: 0;
        font-size: 0.875rem;
        line-height: 1.5rem;
        color: #4b5563;
    }

    .afs-panel-grid {
        display: grid;
        gap: 1rem;
    }

    .afs-panel-card {
        border-radius: 0.75rem;
        border: 1px solid #e5e7eb;
        background: #fff;
        padding: 1.25rem;
    }

    .afs-panel-card-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        flex-wrap: wrap;
        margin-bottom: 0.5rem;
    }

    .afs-panel-card-title {
        display: flex;
        align-items: center;
        gap: 0.625rem;
        font-size: 0.9375rem;
        font-weight: 600;
        color: #0b0f19;
    }

    .afs-panel-card-title svg {
        width: 1.25rem;
        height: 1.25rem;
        color: #6b7280;
        flex-shrink: 0;
    }

    .afs-panel-card p,
    .afs-panel-card-body {
        margin: 0;
        font-size: 0.875rem;
        line-height: 1.5rem;
        color: #4b5563;
    }

    .afs-panel-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        flex-wrap: wrap;
        padding: 0.625rem 0;
        border-top: 1px solid #f3f4f6;
    }

    .afs-panel-row:first-of-type {
        border-top: none;
    }

    .afs-panel-actions {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        flex-wrap: wrap;
    }

    .afs-panel-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
        border-radius: 9999px;
        padding: 0.125rem 0.625rem;
        font-size: 0.75rem;
        font-weight: 600;
    }

    .afs-panel-badge--success { background: #dcfce7; color: #15803d; }
    .afs-panel-badge--warning { background: #fef3c7; color: #b45309; }
    .afs-panel-badge--danger { background: #fee2e2; color: #b91c1c; }
    .afs-panel-badge--neutral { background: #f3f4f6; color: #6b7280; }

    .afs-panel-list {
        list-style: none;
        margin: 0.5rem 0 0;
        padding: 0;
        display: flex;
        flex-direction: column;
        gap: 0.375rem;
    }

    .afs-panel-code {
        font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
        font-size: 0.8125rem;
        background: #f3f4f6;
        border-radius: 0.375rem;
        padding: 0.125rem 0.375rem;
    }

    /*
     * Item #3 audit: connections-health.blade.php, funding-health.blade.php,
     * and four Infolist/modal partials (referral-payouts/details,
     * marketing-campaigns/brief, security-findings/analysis,
     * affiliate-applications/message) used raw Tailwind utility classes
     * (flex, grid, rounded-xl, text-gray-500, dark:*, …) with no styles
     * include at all — confirmed against the compiled bundle
     * (public/css/filament/filament/app.css) to contain none of those
     * class names, meaning these pages rendered as unstyled plain text.
     * This block is the small, generic utility layer that replaces them,
     * scoped "afs-u-" so any future hand-authored page can reach for it
     * instead of a raw Tailwind class that silently does nothing.
     */
    .afs-panel-lead { font-size: 0.875rem; color: #6b7280; margin: 0 0 1.5rem; }
    .afs-panel-section-title { font-size: 0.875rem; font-weight: 600; color: #374151; margin: 2rem 0 0.75rem; }
    .afs-panel-section-title:first-of-type { margin-top: 0; }

    .afs-panel-check-list { display: grid; gap: 0.75rem; margin-bottom: 2rem; }
    .afs-panel-check-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem; }
    .afs-panel-check-name { font-weight: 500; color: #030712; }
    .afs-panel-inline-badges { display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap; }
    .afs-panel-check-meta { font-size: 0.75rem; color: #4b5563; margin-top: 0.25rem; }
    .afs-panel-check-hint { font-size: 0.75rem; color: #9ca3af; margin-top: 0.25rem; }
    .afs-panel-check-actions { display: flex; flex-direction: column; align-items: flex-end; gap: 0.5rem; flex-shrink: 0; }
    .afs-panel-ai-note {
        font-size: 0.75rem; color: #2563eb; margin-top: 0.5rem;
        border-left: 2px solid #60a5fa; padding-left: 0.5rem;
    }

    .afs-panel-link { font-size: 0.75rem; color: #2563eb; text-decoration: none; }
    .afs-panel-link:hover { text-decoration: underline; }
    .afs-panel-link-external { color: #2563eb; text-decoration: underline; word-break: break-all; }
    .afs-panel-btn-plain {
        font-size: 0.75rem; color: #6b7280; background: none; border: none; padding: 0; cursor: pointer;
    }
    .afs-panel-btn-plain:hover { text-decoration: underline; }
    .afs-panel-spacer-top { margin-top: 1.5rem; }

    .afs-panel-detail { display: flex; flex-direction: column; gap: 1rem; font-size: 0.875rem; }
    .afs-panel-detail-label { font-weight: 600; color: #374151; margin-bottom: 0.25rem; }
    .afs-panel-detail-value { color: #4b5563; margin: 0; }
    .afs-panel-detail-hint { font-size: 0.75rem; color: #9ca3af; margin: 0; }
    .afs-panel-detail-list { color: #4b5563; margin: 0; padding-left: 1.125rem; display: flex; flex-direction: column; gap: 0.25rem; }
    .afs-panel-detail-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
    .afs-panel-danger-text { font-size: 0.75rem; color: #dc2626; }
    .afs-panel-warning-text { font-size: 0.75rem; color: #d97706; }
    .afs-panel-pre-line { white-space: pre-line; }
    .afs-panel-break-all { word-break: break-all; }

    .afs-panel-thumbs { display: flex; gap: 0.75rem; flex-wrap: wrap; }
    .afs-panel-thumb { width: 10rem; height: 10rem; object-fit: cover; border-radius: 0.5rem; border: 1px solid #e5e7eb; }

    .afs-panel-snippet {
        background: #f9fafb; border-radius: 0.375rem; padding: 0.5rem;
    }
    .afs-panel-pre-snippet {
        background: #f9fafb; border-radius: 0.375rem; padding: 0.5rem; font-size: 0.75rem; overflow-x: auto;
        font-family: ui-monospace, SFMono-Regular, Menlo, monospace; margin: 0;
    }

    /* Analytics\Overview (item #4) building blocks — kept in this same
       shared partial rather than a second stylesheet, so every hand-authored
       admin page draws from one design system. */
    .afs-panel-heading { margin: 2rem 0 0.75rem; font-size: 0.875rem; font-weight: 600; color: #0b0f19; }
    .afs-panel-heading:first-of-type { margin-top: 0; }
    .afs-panel-heading-row { display: flex; align-items: center; justify-content: space-between; gap: 1rem; }
    .afs-panel-heading-link { font-size: 0.75rem; font-weight: 500; color: #2563eb; text-decoration: none; }
    .afs-panel-heading-link:hover { text-decoration: underline; }

    .afs-panel-periods { display: flex; gap: 0.375rem; flex-shrink: 0; }
    .afs-panel-period-btn {
        border: 1px solid #e5e7eb; background: #fff; border-radius: 0.5rem; padding: 0.375rem 0.75rem;
        font-size: 0.8125rem; font-weight: 500; color: #4b5563; cursor: pointer; line-height: 1.25rem;
    }
    .afs-panel-period-btn:hover { border-color: #60a5fa; color: #0b0f19; }
    .afs-panel-period-btn.is-active { background: #2563eb; border-color: #2563eb; color: #fff; }

    .afs-panel-stat-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem; }
    .afs-panel-stat-value { font-size: 1.5rem; font-weight: 700; color: #0b0f19; line-height: 2rem; }
    .afs-panel-stat-label { font-size: 0.75rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.04em; color: #6b7280; margin-top: 0.25rem; }
    .afs-panel-stat-hint { font-size: 0.75rem; color: #9ca3af; margin-top: 0.375rem; }

    .afs-panel-columns-2 { display: grid; gap: 1rem; }
    @media (min-width: 768px) {
        .afs-panel-stat-grid { grid-template-columns: repeat(4, 1fr); }
        .afs-panel-columns-2 { grid-template-columns: 1fr 1fr; align-items: start; }
    }

    .afs-panel-bars { display: flex; align-items: flex-end; gap: 0.25rem; height: 8rem; margin-top: 0.5rem; }
    .afs-panel-bar { flex: 1; display: flex; align-items: flex-end; justify-content: center; min-width: 2px; }
    .afs-panel-bar-fill { width: 100%; max-width: 1.25rem; margin: 0 auto; background: #3b82f6; border-radius: 0.2rem 0.2rem 0 0; min-height: 2px; }
    .afs-panel-bar-labels { display: flex; gap: 0.25rem; margin-top: 0.5rem; }
    .afs-panel-bar-labels span { flex: 1; text-align: center; font-size: 0.625rem; color: #9ca3af; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

    .afs-panel-table { width: 100%; border-collapse: collapse; font-size: 0.8125rem; }
    .afs-panel-table th {
        text-align: left; font-size: 0.6875rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.04em;
        color: #9ca3af; padding: 0 0.5rem 0.5rem; border-bottom: 1px solid #e5e7eb;
    }
    .afs-panel-table td { padding: 0.5rem; border-bottom: 1px solid #f3f4f6; color: #0b0f19; }
    .afs-panel-table tr:last-child td { border-bottom: none; }
    .afs-panel-table .afs-panel-num { text-align: right; font-variant-numeric: tabular-nums; }

    .afs-panel-bar-track { display: block; height: 0.375rem; border-radius: 999px; background: #f3f4f6; overflow: hidden; margin-top: 0.25rem; min-width: 4rem; }
    .afs-panel-bar-track-fill { display: block; height: 100%; background: #2563eb; border-radius: 999px; }
    .afs-panel-empty { font-size: 0.8125rem; color: #9ca3af; padding: 1rem 0; }

    @media (prefers-color-scheme: dark) {
        html:not(.light) .afs-panel-hero,
        html:not(.light) .afs-panel-card,
        html:not(.light) .afs-panel-check-list > .afs-panel-card { background: rgba(255, 255, 255, 0.03); border-color: rgba(255, 255, 255, 0.1); }
        html:not(.light) .afs-panel-hero-icon { background: rgba(37, 99, 235, 0.2); color: #60a5fa; }
        html:not(.light) .afs-panel-hero p,
        html:not(.light) .afs-panel-card p,
        html:not(.light) .afs-panel-card-body { color: #d1d5db; }
        html:not(.light) .afs-panel-card-title { color: #fff; }
        html:not(.light) .afs-panel-card-title svg { color: #9ca3af; }
        html:not(.light) .afs-panel-row { border-color: rgba(255, 255, 255, 0.08); }
        html:not(.light) .afs-panel-code { background: rgba(255, 255, 255, 0.08); }
        html:not(.light) .afs-panel-lead,
        html:not(.light) .afs-panel-check-meta { color: #9ca3af; }
        html:not(.light) .afs-panel-section-title,
        html:not(.light) .afs-panel-check-name,
        html:not(.light) .afs-panel-detail-label { color: #fff; }
        html:not(.light) .afs-panel-check-hint,
        html:not(.light) .afs-panel-detail-hint { color: #6b7280; }
        html:not(.light) .afs-panel-btn-plain { color: #9ca3af; }
        html:not(.light) .afs-panel-ai-note { color: #60a5fa; border-color: #2563eb; }
        html:not(.light) .afs-panel-link,
        html:not(.light) .afs-panel-link-external { color: #60a5fa; }
        html:not(.light) .afs-panel-detail-value,
        html:not(.light) .afs-panel-detail-list { color: #d1d5db; }
        html:not(.light) .afs-panel-danger-text { color: #f87171; }
        html:not(.light) .afs-panel-warning-text { color: #fbbf24; }
        html:not(.light) .afs-panel-thumb { border-color: rgba(255, 255, 255, 0.1); }
        html:not(.light) .afs-panel-snippet,
        html:not(.light) .afs-panel-pre-snippet { background: rgba(255, 255, 255, 0.05); }
        html:not(.light) .afs-panel-heading,
        html:not(.light) .afs-panel-stat-value { color: #fff; }
        html:not(.light) .afs-panel-period-btn {
            background: rgba(255, 255, 255, 0.05); border-color: rgba(255, 255, 255, 0.1); color: #d1d5db;
        }
        html:not(.light) .afs-panel-table th { border-color: rgba(255, 255, 255, 0.1); }
        html:not(.light) .afs-panel-table td { color: #f3f4f6; border-color: rgba(255, 255, 255, 0.06); }
        html:not(.light) .afs-panel-bar-track,
        html:not(.light) .afs-panel-empty { background: rgba(255, 255, 255, 0.08); }
    }

    html.dark .afs-panel-hero,
    html.dark .afs-panel-card,
    html.dark .afs-panel-check-list > .afs-panel-card { background: rgba(255, 255, 255, 0.03); border-color: rgba(255, 255, 255, 0.1); }
    html.dark .afs-panel-hero-icon { background: rgba(37, 99, 235, 0.2); color: #60a5fa; }
    html.dark .afs-panel-hero p,
    html.dark .afs-panel-card p,
    html.dark .afs-panel-card-body { color: #d1d5db; }
    html.dark .afs-panel-card-title { color: #fff; }
    html.dark .afs-panel-card-title svg { color: #9ca3af; }
    html.dark .afs-panel-row { border-color: rgba(255, 255, 255, 0.08); }
    html.dark .afs-panel-code { background: rgba(255, 255, 255, 0.08); }
    html.dark .afs-panel-lead,
    html.dark .afs-panel-check-meta { color: #9ca3af; }
    html.dark .afs-panel-section-title,
    html.dark .afs-panel-check-name,
    html.dark .afs-panel-detail-label { color: #fff; }
    html.dark .afs-panel-check-hint,
    html.dark .afs-panel-detail-hint { color: #6b7280; }
    html.dark .afs-panel-btn-plain { color: #9ca3af; }
    html.dark .afs-panel-ai-note { color: #60a5fa; border-color: #2563eb; }
    html.dark .afs-panel-link,
    html.dark .afs-panel-link-external { color: #60a5fa; }
    html.dark .afs-panel-detail-value,
    html.dark .afs-panel-detail-list { color: #d1d5db; }
    html.dark .afs-panel-danger-text { color: #f87171; }
    html.dark .afs-panel-warning-text { color: #fbbf24; }
    html.dark .afs-panel-thumb { border-color: rgba(255, 255, 255, 0.1); }
    html.dark .afs-panel-snippet,
    html.dark .afs-panel-pre-snippet { background: rgba(255, 255, 255, 0.05); }
    html.dark .afs-panel-heading,
    html.dark .afs-panel-stat-value { color: #fff; }
    html.dark .afs-panel-period-btn {
        background: rgba(255, 255, 255, 0.05); border-color: rgba(255, 255, 255, 0.1); color: #d1d5db;
    }
    html.dark .afs-panel-table th { border-color: rgba(255, 255, 255, 0.1); }
    html.dark .afs-panel-table td { color: #f3f4f6; border-color: rgba(255, 255, 255, 0.06); }
    html.dark .afs-panel-bar-track { background: rgba(255, 255, 255, 0.08); }
</style>
