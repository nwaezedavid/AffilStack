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

    @media (prefers-color-scheme: dark) {
        html:not(.light) .afs-panel-hero,
        html:not(.light) .afs-panel-card { background: rgba(255, 255, 255, 0.03); border-color: rgba(255, 255, 255, 0.1); }
        html:not(.light) .afs-panel-hero-icon { background: rgba(37, 99, 235, 0.2); color: #60a5fa; }
        html:not(.light) .afs-panel-hero p,
        html:not(.light) .afs-panel-card p,
        html:not(.light) .afs-panel-card-body { color: #d1d5db; }
        html:not(.light) .afs-panel-card-title { color: #fff; }
        html:not(.light) .afs-panel-card-title svg { color: #9ca3af; }
        html:not(.light) .afs-panel-row { border-color: rgba(255, 255, 255, 0.08); }
        html:not(.light) .afs-panel-code { background: rgba(255, 255, 255, 0.08); }
    }

    html.dark .afs-panel-hero,
    html.dark .afs-panel-card { background: rgba(255, 255, 255, 0.03); border-color: rgba(255, 255, 255, 0.1); }
    html.dark .afs-panel-hero-icon { background: rgba(37, 99, 235, 0.2); color: #60a5fa; }
    html.dark .afs-panel-hero p,
    html.dark .afs-panel-card p,
    html.dark .afs-panel-card-body { color: #d1d5db; }
    html.dark .afs-panel-card-title { color: #fff; }
    html.dark .afs-panel-card-title svg { color: #9ca3af; }
    html.dark .afs-panel-row { border-color: rgba(255, 255, 255, 0.08); }
    html.dark .afs-panel-code { background: rgba(255, 255, 255, 0.08); }
</style>
