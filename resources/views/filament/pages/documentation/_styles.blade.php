{{--
    Filament's shipped admin CSS bundle (public/css/filament/filament/app.css) does not
    include arbitrary Tailwind utility classes used in custom page views — there's no
    ->viteTheme() build scanning this app's own Filament blade files. Raw utility classes
    like "rounded-xl" or "items-center" silently render as plain, unstyled text.

    These Documentation pages need real layout (cards, icon badges, a topic grid) to meet
    the "presentable, use headers/illustrations/icons" request, so this partial ships
    plain CSS in a <style> tag instead — it works regardless of any build pipeline. Scoped
    under the "afs-doc-" prefix so it can't collide with Filament's own classes.
--}}
<style>
    .afs-doc-hero {
        display: flex;
        align-items: flex-start;
        gap: 1rem;
        border-radius: 0.75rem;
        border: 1px solid #e5e7eb;
        background: #f9fafb;
        padding: 1.25rem;
        margin-bottom: 1.5rem;
    }

    .afs-doc-hero-icon,
    .afs-doc-icon-badge {
        display: flex;
        flex-shrink: 0;
        align-items: center;
        justify-content: center;
        border-radius: 9999px;
        background: #dbeafe;
        color: #2563eb;
    }

    .afs-doc-hero-icon {
        width: 3rem;
        height: 3rem;
    }

    .afs-doc-hero-icon svg {
        width: 1.5rem;
        height: 1.5rem;
    }

    .afs-doc-hero p {
        margin: 0;
        font-size: 0.875rem;
        line-height: 1.5rem;
        color: #4b5563;
    }

    .afs-doc-heading {
        margin: 0 0 0.75rem;
        font-size: 0.875rem;
        font-weight: 600;
        color: #0b0f19;
    }

    .afs-doc-grid {
        display: grid;
        gap: 1rem;
    }

    .afs-doc-grid + .afs-doc-heading {
        margin-top: 2rem;
    }

    @media (min-width: 640px) {
        .afs-doc-grid--topics {
            grid-template-columns: 1fr 1fr;
        }
    }

    .afs-doc-card,
    .afs-doc-topic {
        border-radius: 0.75rem;
        border: 1px solid #e5e7eb;
        background: #fff;
        padding: 1.25rem;
    }

    .afs-doc-topic {
        display: flex;
        align-items: flex-start;
        gap: 0.75rem;
        text-decoration: none;
        transition: border-color 0.15s ease;
    }

    .afs-doc-topic:hover {
        border-color: #60a5fa;
    }

    .afs-doc-icon-badge {
        width: 2.25rem;
        height: 2.25rem;
        border-radius: 0.5rem;
        background: #f3f4f6;
        color: #6b7280;
    }

    .afs-doc-icon-badge svg {
        width: 1.25rem;
        height: 1.25rem;
    }

    .afs-doc-card-head {
        display: flex;
        align-items: flex-start;
        gap: 0.75rem;
        margin-bottom: 0.5rem;
    }

    .afs-doc-card-head-main {
        flex: 1;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        min-width: 0;
    }

    .afs-doc-card h2,
    .afs-doc-topic-title {
        margin: 0;
        font-size: 0.9375rem;
        font-weight: 600;
        color: #0b0f19;
    }

    .afs-doc-card-link {
        flex-shrink: 0;
        font-size: 0.75rem;
        color: #2563eb;
        text-decoration: none;
    }

    .afs-doc-card-link:hover {
        text-decoration: underline;
    }

    .afs-doc-card-body {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
        padding-left: 3rem;
    }

    .afs-doc-card-body p {
        margin: 0;
        font-size: 0.875rem;
        line-height: 1.5rem;
        color: #4b5563;
    }

    .afs-doc-topic-desc {
        margin: 0.125rem 0 0;
        font-size: 0.75rem;
        line-height: 1.25rem;
        color: #4b5563;
    }

    @media (prefers-color-scheme: dark) {
        html:not(.light) .afs-doc-hero,
        html:not(.light) .afs-doc-card,
        html:not(.light) .afs-doc-topic {
            background: rgba(255, 255, 255, 0.03);
            border-color: rgba(255, 255, 255, 0.1);
        }

        html:not(.light) .afs-doc-hero-icon {
            background: rgba(37, 99, 235, 0.2);
            color: #60a5fa;
        }

        html:not(.light) .afs-doc-icon-badge {
            background: rgba(255, 255, 255, 0.08);
            color: #9ca3af;
        }

        html:not(.light) .afs-doc-hero p,
        html:not(.light) .afs-doc-card-body p,
        html:not(.light) .afs-doc-topic-desc {
            color: #d1d5db;
        }

        html:not(.light) .afs-doc-card h2,
        html:not(.light) .afs-doc-topic-title,
        html:not(.light) .afs-doc-heading {
            color: #fff;
        }

        html:not(.light) .afs-doc-topic:hover {
            border-color: #3b82f6;
        }
    }

    html.dark .afs-doc-hero,
    html.dark .afs-doc-card,
    html.dark .afs-doc-topic {
        background: rgba(255, 255, 255, 0.03);
        border-color: rgba(255, 255, 255, 0.1);
    }

    html.dark .afs-doc-hero-icon {
        background: rgba(37, 99, 235, 0.2);
        color: #60a5fa;
    }

    html.dark .afs-doc-icon-badge {
        background: rgba(255, 255, 255, 0.08);
        color: #9ca3af;
    }

    html.dark .afs-doc-hero p,
    html.dark .afs-doc-card-body p,
    html.dark .afs-doc-topic-desc {
        color: #d1d5db;
    }

    html.dark .afs-doc-card h2,
    html.dark .afs-doc-topic-title,
    html.dark .afs-doc-heading {
        color: #fff;
    }

    html.dark .afs-doc-topic:hover {
        border-color: #3b82f6;
    }
</style>
