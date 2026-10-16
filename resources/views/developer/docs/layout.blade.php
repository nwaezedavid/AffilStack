@extends('layouts.developer')

@section('title', $title.' · Docs')

@section('content')
    <div class="grid gap-8 lg:grid-cols-4">
        <nav class="lg:col-span-1" aria-label="Documentation">
            <p class="font-mono text-[11px] uppercase tracking-[0.16em] text-ink-400">Documentation</p>
            <ul class="mt-3 flex gap-1 overflow-x-auto lg:flex-col">
                @foreach ($pages as $slug => $label)
                    <li><a href="{{ route('developer.docs', $slug) }}" @if ($page === $slug) aria-current="page" @endif class="block whitespace-nowrap rounded-lg px-3 py-2 text-sm {{ $page === $slug ? 'bg-white font-semibold text-navy-900 ring-1 ring-line' : 'text-ink-600 hover:bg-white hover:text-navy-900' }}">{{ $label }}</a></li>
                @endforeach
            </ul>
        </nav>
        <article class="min-w-0 lg:col-span-3">
            <h1 class="font-display text-3xl font-semibold tracking-tight text-navy-900">{{ $title }}</h1>
            <div class="docs mt-6 space-y-5 text-[15px] leading-relaxed text-ink-600 [&_h2]:mt-10 [&_h2]:font-display [&_h2]:text-xl [&_h2]:font-semibold [&_h2]:text-navy-900 [&_h3]:mt-6 [&_h3]:font-semibold [&_h3]:text-navy-900 [&_code]:rounded [&_code]:bg-white [&_code]:px-1.5 [&_code]:py-0.5 [&_code]:font-mono [&_code]:text-[13px] [&_code]:text-navy-900 [&_ul]:list-disc [&_ul]:space-y-1.5 [&_ul]:pl-5 [&_ol]:list-decimal [&_ol]:space-y-1.5 [&_ol]:pl-5 [&_a]:font-medium [&_a]:text-brand-600">
                @yield('doc')
            </div>
        </article>
    </div>
@endsection
