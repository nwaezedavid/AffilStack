@extends('layouts.app')

@section('title', 'Swipe Files')

@section('content')
    <p class="text-sm text-ink-600 max-w-lg mb-6">A curated library of hooks, email subject lines, and thumbnail styles by niche — proven patterns to start from instead of a blank page. Curated by AffilStack, not AI-generated, since a swipe file's whole point is that these patterns have actually worked.</p>

    <form method="GET" action="{{ route('swipe-files.index') }}" class="flex flex-wrap gap-2 mb-6">
        <select name="type" onchange="this.form.submit()" class="rounded-md border border-line px-3 py-1.5 text-sm">
            <option value="">All types</option>
            @foreach (config('swipe_files.types') as $value => $label)
                <option value="{{ $value }}" @selected(($filters['type'] ?? null) === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <select name="niche" onchange="this.form.submit()" class="rounded-md border border-line px-3 py-1.5 text-sm">
            <option value="">All niches</option>
            @foreach (config('swipe_files.niches') as $value => $label)
                <option value="{{ $value }}" @selected(($filters['niche'] ?? null) === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Search hooks, subject lines, styles…" class="flex-1 min-w-[200px] rounded-md border border-line px-3 py-1.5 text-sm">
        <button class="rounded-md bg-navy-900 text-white text-sm px-4 py-1.5 hover:bg-navy-800 transition">Search</button>
        @if (array_filter($filters))
            <a href="{{ route('swipe-files.index') }}" class="text-xs text-ink-400 hover:text-ink-600 self-center">Clear filters</a>
        @endif
    </form>

    @if ($entries->isEmpty())
        <div class="bg-surface border border-dashed border-line rounded-lg p-8 text-center text-sm text-ink-600">
            No entries match those filters.
        </div>
    @else
        <div class="grid sm:grid-cols-2 gap-4 mb-6">
            @foreach ($entries as $entry)
                <div class="bg-surface border border-line rounded-lg p-4">
                    <div class="flex items-center gap-2 mb-2 flex-wrap">
                        <span class="text-xs font-mono uppercase tracking-wide px-2 py-0.5 rounded bg-surface-muted text-ink-600">{{ config("swipe_files.types.{$entry->type}") }}</span>
                        <span class="text-xs text-ink-400">{{ config("swipe_files.niches.{$entry->niche}") }}</span>
                    </div>
                    <div class="text-sm font-medium text-ink-900 mb-1">{{ $entry->title }}</div>
                    <p id="swipe-content-{{ $entry->id }}" class="text-sm text-ink-900 whitespace-pre-line mb-2">{{ $entry->content }}</p>
                    @if ($entry->notes)
                        <p class="text-xs text-ink-600 mb-2">{{ $entry->notes }}</p>
                    @endif
                    <button
                        type="button"
                        onclick="navigator.clipboard.writeText(document.getElementById('swipe-content-{{ $entry->id }}').innerText); this.textContent = 'Copied ✓'; setTimeout(() => this.textContent = 'Copy', 1500);"
                        class="text-xs rounded-md border border-line px-2.5 py-1 hover:bg-surface-muted transition"
                    >Copy</button>
                </div>
            @endforeach
        </div>
        <div>{{ $entries->links() }}</div>
    @endif
@endsection
