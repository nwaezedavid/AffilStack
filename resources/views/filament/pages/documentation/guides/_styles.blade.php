{{--
    Styles for the Integration Guides (hub + every guide page). Plain CSS for the same
    reason as ../_styles.blade.php: Filament's admin bundle doesn't include this app's
    Tailwind utilities. Everything is scoped under .afs-g and themed with custom
    properties, so html.dark (Filament's dark mode) only swaps the variables.
--}}
<style>
    .afs-g {
        --g-ink: #0b1e3d;
        --g-text: #374151;
        --g-muted: #6b7280;
        --g-line: #e5e7eb;
        --g-card: #ffffff;
        --g-soft: #f8fafc;
        --g-brand: #2452d9;
        --g-brand-soft: #eef2ff;
        --g-navy: #0b1e3d;
        --g-gold: #c9a24a;
        --g-code: #f1f5f9;
        color: var(--g-text);
        font-size: 0.9rem;
        line-height: 1.6;
    }

    html.dark .afs-g {
        --g-ink: #f8fafc;
        --g-text: #d1d5db;
        --g-muted: #9ca3af;
        --g-line: rgba(255, 255, 255, 0.1);
        --g-card: rgba(255, 255, 255, 0.03);
        --g-soft: rgba(255, 255, 255, 0.04);
        --g-brand: #7c9cff;
        --g-brand-soft: rgba(36, 82, 217, 0.18);
        --g-code: rgba(255, 255, 255, 0.06);
    }

    .afs-g *,
    .afs-g *::before,
    .afs-g *::after {
        box-sizing: border-box;
    }

    .afs-g a {
        color: var(--g-brand);
        text-decoration: underline;
        text-underline-offset: 2px;
    }

    .afs-g a:focus-visible,
    .afs-g button:focus-visible,
    .afs-g input:focus-visible {
        outline: 2px solid var(--g-brand);
        outline-offset: 2px;
    }

    .afs-g svg {
        flex-shrink: 0;
    }

    /* ---------- Hero ---------- */
    .afs-g-hero {
        position: relative;
        overflow: hidden;
        border-radius: 1rem;
        padding: 1.5rem;
        margin-bottom: 1.5rem;
        background: linear-gradient(135deg, #0b1e3d 0%, #1d3568 55%, #2452d9 100%);
        color: #e5e7eb;
    }

    .afs-g-hero::after {
        content: '';
        position: absolute;
        right: -60px;
        top: -60px;
        width: 220px;
        height: 220px;
        border-radius: 9999px;
        background: radial-gradient(circle, rgba(224, 181, 99, 0.35), transparent 70%);
    }

    .afs-g-eyebrow {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        font-size: 0.7rem;
        font-weight: 700;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        color: #e0b563;
    }

    .afs-g-hero h1,
    .afs-g-hero h2 {
        margin: 0.35rem 0 0.5rem;
        font-size: 1.45rem;
        line-height: 1.25;
        font-weight: 700;
        color: #fff;
    }

    .afs-g-hero p {
        margin: 0;
        max-width: 46rem;
        color: #cbd5e1;
    }

    .afs-g-hero-row {
        position: relative;
        z-index: 1;
        display: flex;
        flex-wrap: wrap;
        align-items: flex-start;
        justify-content: space-between;
        gap: 1rem;
    }

    .afs-g-hero-stats {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
        margin-top: 1rem;
    }

    .afs-g-hero-stat {
        border-radius: 0.75rem;
        background: rgba(255, 255, 255, 0.08);
        border: 1px solid rgba(255, 255, 255, 0.14);
        padding: 0.5rem 0.85rem;
        font-size: 0.8rem;
        color: #e5e7eb;
    }

    .afs-g-hero-stat strong {
        display: block;
        font-size: 1.15rem;
        color: #fff;
    }

    .afs-g-progress {
        height: 0.5rem;
        border-radius: 9999px;
        background: rgba(255, 255, 255, 0.15);
        overflow: hidden;
        margin-top: 0.85rem;
        max-width: 28rem;
    }

    .afs-g-progress span {
        display: block;
        height: 100%;
        background: linear-gradient(90deg, #e0b563, #34d399);
    }

    /* ---------- Search ---------- */
    .afs-g-search {
        position: relative;
        margin-bottom: 1.25rem;
    }

    .afs-g-search svg {
        position: absolute;
        left: 0.85rem;
        top: 50%;
        width: 1.1rem;
        height: 1.1rem;
        transform: translateY(-50%);
        color: var(--g-muted);
    }

    .afs-g-search input {
        width: 100%;
        border-radius: 0.75rem;
        border: 1px solid var(--g-line);
        background: var(--g-card);
        color: var(--g-ink);
        padding: 0.75rem 1rem 0.75rem 2.6rem;
        font-size: 0.95rem;
    }

    .afs-g-results {
        display: grid;
        gap: 0.6rem;
        margin-bottom: 1.5rem;
    }

    .afs-g-result {
        display: block;
        border-radius: 0.75rem;
        border: 1px solid var(--g-line);
        background: var(--g-card);
        padding: 0.8rem 1rem;
        text-decoration: none !important;
    }

    .afs-g-result:hover {
        border-color: var(--g-brand);
    }

    .afs-g-result strong {
        color: var(--g-ink);
    }

    .afs-g-result p {
        margin: 0.25rem 0 0;
        font-size: 0.82rem;
        color: var(--g-muted);
    }

    .afs-g-result mark {
        background: rgba(224, 181, 99, 0.45);
        color: inherit;
        border-radius: 0.2rem;
    }

    /* ---------- Phases + cards (hub) ---------- */
    .afs-g-phase {
        margin-bottom: 1.75rem;
    }

    .afs-g-phase-head {
        display: flex;
        align-items: baseline;
        flex-wrap: wrap;
        gap: 0.25rem 0.75rem;
        margin-bottom: 0.75rem;
        padding-bottom: 0.5rem;
        border-bottom: 1px solid var(--g-line);
    }

    .afs-g-phase-head h2 {
        margin: 0;
        font-size: 1.05rem;
        font-weight: 700;
        color: var(--g-ink);
    }

    .afs-g-phase-head p {
        margin: 0;
        font-size: 0.85rem;
        color: var(--g-muted);
    }

    .afs-g-cards {
        display: grid;
        gap: 0.85rem;
        grid-template-columns: repeat(auto-fill, minmax(min(100%, 16rem), 1fr));
    }

    .afs-g-card {
        position: relative;
        display: flex;
        flex-direction: column;
        gap: 0.6rem;
        border-radius: 0.9rem;
        border: 1px solid var(--g-line);
        background: var(--g-card);
        padding: 1rem;
        transition: border-color 0.15s ease, box-shadow 0.15s ease;
    }

    .afs-g-card:hover {
        border-color: var(--g-brand);
        box-shadow: 0 6px 20px -12px rgba(36, 82, 217, 0.45);
    }

    .afs-g-card-top {
        display: flex;
        align-items: flex-start;
        gap: 0.75rem;
    }

    .afs-g-logo {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 2.5rem;
        height: 2.5rem;
        border-radius: 0.7rem;
        background: var(--g-brand-soft);
        color: var(--g-brand);
    }

    .afs-g-logo svg {
        width: 1.35rem;
        height: 1.35rem;
    }

    .afs-g-num {
        font-size: 0.7rem;
        font-weight: 700;
        color: var(--g-muted);
        letter-spacing: 0.04em;
    }

    .afs-g-card h3 {
        margin: 0;
        font-size: 0.98rem;
        line-height: 1.3;
        font-weight: 700;
        color: var(--g-ink);
    }

    .afs-g-card h3 a {
        color: inherit;
        text-decoration: none;
    }

    .afs-g-card h3 a::after {
        content: '';
        position: absolute;
        inset: 0;
        border-radius: 0.9rem;
    }

    .afs-g-card p {
        margin: 0;
        font-size: 0.84rem;
        color: var(--g-text);
    }

    .afs-g-facts {
        display: grid;
        gap: 0.3rem;
        margin: 0;
        padding: 0.6rem 0.7rem;
        border-radius: 0.6rem;
        background: var(--g-soft);
        font-size: 0.78rem;
    }

    .afs-g-facts div {
        display: flex;
        gap: 0.45rem;
        align-items: flex-start;
    }

    .afs-g-facts svg {
        width: 0.95rem;
        height: 0.95rem;
        margin-top: 0.15rem;
        color: var(--g-muted);
    }

    .afs-g-card-foot {
        position: relative;
        z-index: 1;
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
        margin-top: auto;
    }

    .afs-g-chips {
        display: flex;
        flex-wrap: wrap;
        gap: 0.35rem;
    }

    /* ---------- Pills ---------- */
    .afs-g-pill {
        display: inline-flex;
        align-items: center;
        gap: 0.3rem;
        border-radius: 9999px;
        padding: 0.12rem 0.55rem;
        font-size: 0.72rem;
        font-weight: 600;
        line-height: 1.4;
        white-space: nowrap;
        border: 1px solid transparent;
    }

    .afs-g-pill::before {
        content: '';
        width: 0.45rem;
        height: 0.45rem;
        border-radius: 9999px;
        background: currentColor;
    }

    .afs-g-pill--plain::before {
        display: none;
    }

    .afs-g-pill--working { background: #ecfdf5; color: #047857; border-color: #a7f3d0; }
    .afs-g-pill--failing { background: #fff1f2; color: #be123c; border-color: #fecdd3; }
    .afs-g-pill--configured,
    .afs-g-pill--pending { background: #fffbeb; color: #b45309; border-color: #fde68a; }
    .afs-g-pill--off { background: #f1f5f9; color: #475569; border-color: #e2e8f0; }
    .afs-g-pill--not_set { background: #f8fafc; color: #64748b; border-color: #e2e8f0; }
    .afs-g-pill--user { background: #f5f3ff; color: #6d28d9; border-color: #ddd6fe; }
    .afs-g-pill--required { background: #0b1e3d; color: #fff; }
    .afs-g-pill--recommended { background: #eef2ff; color: #1b3fb3; border-color: #c7d2fe; }
    .afs-g-pill--optional { background: #f8fafc; color: #475569; border-color: #e2e8f0; }

    html.dark .afs-g-pill--working { background: rgba(16, 185, 129, 0.15); color: #6ee7b7; border-color: rgba(16, 185, 129, 0.3); }
    html.dark .afs-g-pill--failing { background: rgba(244, 63, 94, 0.15); color: #fda4af; border-color: rgba(244, 63, 94, 0.3); }
    html.dark .afs-g-pill--configured,
    html.dark .afs-g-pill--pending { background: rgba(245, 158, 11, 0.15); color: #fcd34d; border-color: rgba(245, 158, 11, 0.3); }
    html.dark .afs-g-pill--off,
    html.dark .afs-g-pill--not_set,
    html.dark .afs-g-pill--optional { background: rgba(255, 255, 255, 0.06); color: #cbd5e1; border-color: rgba(255, 255, 255, 0.12); }
    html.dark .afs-g-pill--user { background: rgba(139, 92, 246, 0.15); color: #c4b5fd; border-color: rgba(139, 92, 246, 0.3); }
    html.dark .afs-g-pill--required { background: #e0b563; color: #0b1e3d; }
    html.dark .afs-g-pill--recommended { background: rgba(36, 82, 217, 0.2); color: #a5b4fc; border-color: rgba(99, 102, 241, 0.35); }

    /* ---------- Buttons ---------- */
    .afs-g-btn {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        border-radius: 0.55rem;
        padding: 0.4rem 0.75rem;
        font-size: 0.8rem;
        font-weight: 600;
        text-decoration: none !important;
        border: 1px solid var(--g-line);
        background: var(--g-card);
        color: var(--g-ink) !important;
        cursor: pointer;
    }

    .afs-g-btn svg {
        width: 1rem;
        height: 1rem;
    }

    .afs-g-btn--primary {
        background: var(--g-brand);
        border-color: var(--g-brand);
        color: #fff !important;
    }

    html.dark .afs-g-btn--primary {
        background: #2452d9;
        border-color: #2452d9;
    }

    .afs-g-btn--light {
        background: rgba(255, 255, 255, 0.12);
        border-color: rgba(255, 255, 255, 0.25);
        color: #fff !important;
    }

    /* ---------- Guide page layout ---------- */
    .afs-g-layout {
        display: grid;
        gap: 1.5rem;
        grid-template-columns: minmax(0, 1fr);
    }

    @media (min-width: 1440px) {
        .afs-g-layout {
            grid-template-columns: minmax(0, 1fr) 15rem;
        }
    }

    .afs-g-toc {
        order: -1;
        border-radius: 0.9rem;
        border: 1px solid var(--g-line);
        background: var(--g-card);
        padding: 0.9rem 1rem;
        font-size: 0.84rem;
    }

    @media (min-width: 1440px) {
        .afs-g-toc {
            order: 0;
            position: sticky;
            top: 5rem;
            align-self: start;
        }
    }

    .afs-g-toc strong {
        display: block;
        margin-bottom: 0.4rem;
        font-size: 0.72rem;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        color: var(--g-muted);
    }

    .afs-g-toc ol {
        margin: 0;
        padding-left: 1.1rem;
        display: grid;
        gap: 0.25rem;
    }

    .afs-g-toc a {
        text-decoration: none;
    }

    .afs-g-meta {
        display: grid;
        gap: 0.6rem;
        grid-template-columns: repeat(auto-fill, minmax(min(100%, 13rem), 1fr));
        margin-bottom: 1.25rem;
    }

    .afs-g-meta-item {
        border-radius: 0.75rem;
        border: 1px solid var(--g-line);
        background: var(--g-card);
        padding: 0.7rem 0.85rem;
        font-size: 0.82rem;
    }

    .afs-g-meta-label {
        display: flex;
        align-items: center;
        gap: 0.35rem;
        font-size: 0.7rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        color: var(--g-muted);
        margin-bottom: 0.25rem;
    }

    .afs-g-meta-label svg {
        width: 0.9rem;
        height: 0.9rem;
    }

    .afs-g-section {
        scroll-margin-top: 5rem;
        border-radius: 0.9rem;
        border: 1px solid var(--g-line);
        background: var(--g-card);
        padding: 1.1rem 1.25rem;
        margin-bottom: 1rem;
    }

    .afs-g-section > h2 {
        display: flex;
        align-items: center;
        gap: 0.55rem;
        margin: 0 0 0.75rem;
        font-size: 1.05rem;
        font-weight: 700;
        color: var(--g-ink);
    }

    .afs-g-section > h2 svg {
        width: 1.2rem;
        height: 1.2rem;
        color: var(--g-brand);
    }

    .afs-g-section h3 {
        margin: 1.1rem 0 0.5rem;
        font-size: 0.95rem;
        font-weight: 700;
        color: var(--g-ink);
    }

    .afs-g-section p {
        margin: 0 0 0.65rem;
    }

    .afs-g-section ul {
        margin: 0 0 0.75rem;
        padding-left: 1.2rem;
    }

    .afs-g-section li {
        margin-bottom: 0.3rem;
    }

    .afs-g-section strong {
        color: var(--g-ink);
    }

    /* Numbered steps */
    .afs-g-steps {
        list-style: none;
        counter-reset: afs-step;
        margin: 0;
        padding: 0;
    }

    .afs-g-steps > li {
        counter-increment: afs-step;
        position: relative;
        padding: 0 0 1rem 2.6rem;
        margin: 0;
    }

    .afs-g-steps > li::before {
        content: counter(afs-step);
        position: absolute;
        left: 0;
        top: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        width: 1.8rem;
        height: 1.8rem;
        border-radius: 9999px;
        background: var(--g-navy);
        color: #fff;
        font-size: 0.8rem;
        font-weight: 700;
    }

    html.dark .afs-g-steps > li::before {
        background: #2452d9;
    }

    .afs-g-steps > li:not(:last-child)::after {
        content: '';
        position: absolute;
        left: 0.88rem;
        top: 2rem;
        bottom: 0.2rem;
        width: 2px;
        background: var(--g-line);
    }

    .afs-g-step-title {
        display: block;
        font-weight: 700;
        color: var(--g-ink);
        margin-bottom: 0.25rem;
        padding-top: 0.15rem;
    }

    .afs-g-step-body > :last-child {
        margin-bottom: 0;
    }

    .afs-g-ui {
        display: inline-block;
        border-radius: 0.35rem;
        border: 1px solid var(--g-line);
        background: var(--g-soft);
        padding: 0 0.35rem;
        font-weight: 600;
        font-size: 0.85em;
        color: var(--g-ink);
        white-space: normal;
    }

    .afs-g code,
    .afs-g kbd {
        border-radius: 0.3rem;
        background: var(--g-code);
        padding: 0.05rem 0.3rem;
        font-size: 0.85em;
        font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
        word-break: break-word;
        color: var(--g-ink);
    }

    /* Copy box */
    .afs-g-copy {
        display: flex;
        align-items: stretch;
        margin: 0.4rem 0 0.75rem;
        border-radius: 0.6rem;
        border: 1px solid var(--g-line);
        background: var(--g-code);
        overflow: hidden;
    }

    .afs-g-copy-label {
        display: block;
        font-size: 0.72rem;
        font-weight: 700;
        color: var(--g-muted);
        text-transform: uppercase;
        letter-spacing: 0.05em;
        margin-top: 0.5rem;
    }

    .afs-g-copy code {
        flex: 1;
        min-width: 0;
        background: transparent;
        padding: 0.55rem 0.75rem;
        font-size: 0.82rem;
        overflow-wrap: anywhere;
        white-space: pre-wrap;
    }

    .afs-g-copy button {
        display: inline-flex;
        align-items: center;
        gap: 0.3rem;
        border: 0;
        border-left: 1px solid var(--g-line);
        background: var(--g-card);
        color: var(--g-brand);
        font-size: 0.78rem;
        font-weight: 700;
        padding: 0 0.8rem;
        cursor: pointer;
        white-space: nowrap;
    }

    .afs-g-copy button svg {
        width: 0.95rem;
        height: 0.95rem;
    }

    /* Callouts */
    .afs-g-callout {
        display: flex;
        gap: 0.7rem;
        border-radius: 0.75rem;
        border: 1px solid;
        padding: 0.8rem 0.95rem;
        margin: 0.6rem 0 0.9rem;
        font-size: 0.86rem;
    }

    .afs-g-callout svg {
        width: 1.2rem;
        height: 1.2rem;
        margin-top: 0.1rem;
    }

    .afs-g-callout-title {
        display: block;
        font-weight: 700;
        margin-bottom: 0.15rem;
    }

    .afs-g-callout p:last-child,
    .afs-g-callout ul:last-child {
        margin-bottom: 0;
    }

    .afs-g-callout--tip { background: #ecfdf5; border-color: #a7f3d0; color: #065f46; }
    .afs-g-callout--info { background: #eff6ff; border-color: #bfdbfe; color: #1e3a8a; }
    .afs-g-callout--warning { background: #fffbeb; border-color: #fde68a; color: #92400e; }
    .afs-g-callout--danger { background: #fff1f2; border-color: #fecdd3; color: #9f1239; }
    .afs-g-callout--cost { background: #fdf8ec; border-color: #ecd9a6; color: #7a5c17; }
    .afs-g-callout strong,
    .afs-g-callout code { color: inherit; }

    .afs-g-callout--big {
        border-width: 2px;
        padding: 1rem 1.1rem;
        font-size: 0.92rem;
    }

    .afs-g-callout--big .afs-g-callout-title {
        font-size: 1.02rem;
    }

    html.dark .afs-g-callout--tip { background: rgba(16, 185, 129, 0.1); border-color: rgba(16, 185, 129, 0.35); color: #a7f3d0; }
    html.dark .afs-g-callout--info { background: rgba(59, 130, 246, 0.1); border-color: rgba(59, 130, 246, 0.35); color: #bfdbfe; }
    html.dark .afs-g-callout--warning { background: rgba(245, 158, 11, 0.1); border-color: rgba(245, 158, 11, 0.35); color: #fde68a; }
    html.dark .afs-g-callout--danger { background: rgba(244, 63, 94, 0.1); border-color: rgba(244, 63, 94, 0.4); color: #fecdd3; }
    html.dark .afs-g-callout--cost { background: rgba(224, 181, 99, 0.1); border-color: rgba(224, 181, 99, 0.35); color: #f5deb0; }

    /* Troubleshooting */
    .afs-g-trouble {
        border-radius: 0.65rem;
        border: 1px solid var(--g-line);
        margin-bottom: 0.5rem;
        background: var(--g-soft);
    }

    .afs-g-trouble summary {
        cursor: pointer;
        padding: 0.65rem 0.85rem;
        font-weight: 600;
        color: var(--g-ink);
    }

    .afs-g-trouble > div {
        padding: 0 0.85rem 0.75rem;
    }

    .afs-g-table {
        width: 100%;
        border-collapse: collapse;
        margin: 0.4rem 0 0.9rem;
        font-size: 0.83rem;
    }

    .afs-g-table-wrap {
        overflow-x: auto;
    }

    .afs-g-table th,
    .afs-g-table td {
        text-align: left;
        vertical-align: top;
        padding: 0.45rem 0.6rem;
        border-bottom: 1px solid var(--g-line);
    }

    .afs-g-table th {
        font-size: 0.72rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: var(--g-muted);
    }

    .afs-g-docs {
        display: grid;
        gap: 0.4rem;
        margin: 0;
        padding: 0;
        list-style: none;
    }

    .afs-g-docs a {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
    }

    .afs-g-docs svg {
        width: 0.9rem;
        height: 0.9rem;
    }

    .afs-g-small {
        font-size: 0.78rem;
        color: var(--g-muted);
    }

    .afs-g-back {
        display: inline-flex;
        align-items: center;
        gap: 0.3rem;
        margin-bottom: 0.75rem;
        font-size: 0.84rem;
        font-weight: 600;
        text-decoration: none !important;
    }

    .afs-g-back svg {
        width: 1rem;
        height: 1rem;
    }

    .afs-g-pager {
        display: flex;
        flex-wrap: wrap;
        justify-content: space-between;
        gap: 0.75rem;
        margin-top: 0.5rem;
    }

    .afs-g .sr-only {
        position: absolute;
        width: 1px;
        height: 1px;
        overflow: hidden;
        clip: rect(0, 0, 0, 0);
        white-space: nowrap;
    }

    [x-cloak] {
        display: none !important;
    }
</style>
