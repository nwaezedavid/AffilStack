@extends('layouts.app')

@section('title', 'Template Library')

@section('content')
    <x-ui.page-header eyebrow="Publish & track" title="Template Library" subtitle="Prompts, emails, posts, hooks and scripts from the template packs you've added. Copy one and make it yours." icon="document-duplicate" tone="navy">
        <x-slot:actions>
            <x-ui.button variant="gold" :href="route('marketplace.browse', ['type' => 'template_pack'])" icon="plus">Get template packs</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <nav class="mb-5 flex flex-wrap gap-2" aria-label="Template types">
        <a href="{{ route('templates.index') }}" class="rounded-full px-3.5 py-1.5 text-sm font-medium {{ $kind === '' ? 'bg-navy-900 text-white' : 'bg-white text-ink-600 ring-1 ring-line hover:bg-surface-muted' }}">All</a>
        @foreach ($kinds as $key => $label)
            <a href="{{ route('templates.index', ['kind' => $key]) }}" class="rounded-full px-3.5 py-1.5 text-sm font-medium {{ $kind === $key ? 'bg-navy-900 text-white' : 'bg-white text-ink-600 ring-1 ring-line hover:bg-surface-muted' }}">{{ $label }}</a>
        @endforeach
    </nav>

    @if ($templates->isEmpty())
        <x-ui.empty-state icon="document-duplicate" title="Your library is empty" :href="route('marketplace.browse', ['type' => 'template_pack'])" action="Browse template packs">
            Template packs from the marketplace add their templates here.
        </x-ui.empty-state>
    @else
        <div class="grid gap-4 md:grid-cols-2">
            @foreach ($templates as $template)
                <x-ui.card padding="p-5">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="font-medium text-navy-900">{{ $template->title }}</p>
                            <p class="text-xs text-ink-400">{{ $kinds[$template->kind] ?? $template->kind }} · {{ $template->install?->addon?->liveVersion?->name }}</p>
                        </div>
                        <x-ui.copy-button :text="$template->body" />
                    </div>
                    <p class="mt-3 line-clamp-6 whitespace-pre-line text-sm text-ink-600">{{ $template->body }}</p>
                </x-ui.card>
            @endforeach
        </div>
        <div class="mt-6">{{ $templates->links() }}</div>
    @endif
@endsection
