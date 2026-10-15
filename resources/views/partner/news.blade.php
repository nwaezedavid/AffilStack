@extends('layouts.partner')

@section('title', 'News')

@section('content')
    @if ($announcements->isEmpty())
        <x-ui.card>
            <div class="py-8 text-center">
                <x-heroicon-o-newspaper class="mx-auto h-10 w-10 text-ink-300" />
                <p class="mt-3 font-display font-semibold text-navy-900">No news yet</p>
                <p class="mt-1 text-sm text-ink-600">Promotions, new banners and program updates will appear here.</p>
            </div>
        </x-ui.card>
    @else
        <div class="space-y-4">
            @foreach ($announcements as $item)
                @php($isNew = $seenBefore === null || $item->published_at->gt($seenBefore))
                <article id="news-{{ $item->id }}" class="scroll-mt-24 rounded-2xl border bg-surface p-5 sm:p-6 {{ $item->is_pinned ? 'border-gold-300' : 'border-line' }}">
                    <div class="flex flex-wrap items-center gap-2 text-xs text-ink-400">
                        @if ($item->is_pinned)
                            <span class="inline-flex items-center gap-1 rounded-full bg-gold-300/30 px-2 py-0.5 font-medium text-gold-600"><x-heroicon-s-bookmark class="h-3 w-3" /> Pinned</span>
                        @endif
                        @if ($isNew)
                            <span class="rounded-full bg-emerald-100 px-2 py-0.5 font-medium text-emerald-800">New</span>
                        @endif
                        <time datetime="{{ $item->published_at->toIso8601String() }}">{{ $item->published_at->format('F j, Y') }}</time>
                    </div>
                    <h2 class="mt-2 font-display text-lg font-semibold text-navy-900">{{ $item->title }}</h2>
                    <div class="mt-3 max-w-none text-sm leading-relaxed text-ink-900 [&_p+p]:mt-3 [&_a]:text-emerald-700 [&_a]:underline [&_ul]:mt-2 [&_ul]:list-disc [&_ul]:pl-5 [&_ol]:mt-2 [&_ol]:list-decimal [&_ol]:pl-5 [&_strong]:font-semibold [&_h2]:mt-4 [&_h2]:font-semibold [&_h3]:mt-3 [&_h3]:font-semibold">{!! \App\Support\SafeHtml::clean($item->body) !!}</div>
                </article>
            @endforeach
        </div>
        <div class="mt-6">{{ $announcements->links() }}</div>
    @endif
@endsection
