@extends('layouts.developer')

@section('title', 'Your add-ons')
@section('noindex', '1')

@php
    $statusStyles = [
        'draft' => 'bg-surface-muted text-ink-600 ring-line',
        'submitted' => 'bg-sky-50 text-sky-700 ring-sky-500/20',
        'rejected' => 'bg-rose-50 text-rose-700 ring-rose-500/20',
        'approved' => 'bg-emerald-50 text-emerald-700 ring-emerald-500/20',
    ];
    $statusLabels = ['draft' => 'Draft', 'submitted' => 'In review', 'rejected' => 'Changes needed', 'approved' => 'Live'];
@endphp

@section('content')
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <p class="font-mono text-xs uppercase tracking-[0.16em] text-ink-400">{{ $developer->display_name }}</p>
            <h1 class="mt-1 font-display text-2xl font-semibold text-navy-900">Your add-ons</h1>
        </div>
        <x-ui.button :href="route('developer.addons.create')" icon="plus">New add-on</x-ui.button>
    </div>

    <div class="mt-6 grid gap-4 sm:grid-cols-3">
        <x-ui.stat label="Available to pay out" :value="'$'.number_format($balance['available'] / 100, 2)" icon="banknotes" tone="emerald" />
        <x-ui.stat label="Waiting out the refund window" :value="'$'.number_format($balance['pending'] / 100, 2)" icon="clock" tone="gold" />
        <x-ui.stat label="Paid to you" :value="'$'.number_format($balance['paid'] / 100, 2)" icon="check-badge" tone="brand" />
    </div>

    @if ($addons->isEmpty())
        <x-ui.empty-state class="mt-8" icon="puzzle-piece" title="Create your first add-on" :href="route('developer.addons.create')" action="New add-on">
            A connected app, a template pack or an automation recipe. You can try it in your own account before submitting it.
        </x-ui.empty-state>
    @else
        <div class="mt-8 overflow-hidden rounded-2xl border border-line bg-surface">
            <ul class="divide-y divide-line">
                @foreach ($addons as $addon)
                    @php($shown = $addon->workingVersion ?? $addon->liveVersion)
                    <li>
                        <a href="{{ route('developer.addons.show', $addon) }}" class="flex flex-wrap items-center gap-4 px-5 py-4 hover:bg-surface-muted sm:px-6">
                            @include('partials.marketplace.addon-icon', ['version' => $shown, 'size' => 'h-10 w-10 text-sm'])
                            <div class="min-w-0 flex-1">
                                <p class="truncate font-medium text-navy-900">{{ $shown?->name }}</p>
                                <p class="text-xs text-ink-400">{{ $addon->typeLabel() }} · {{ number_format($addon->installs_count) }} {{ \Illuminate\Support\Str::plural('install', $addon->installs_count) }}</p>
                            </div>
                            <div class="flex flex-wrap gap-2 text-[11px] font-medium">
                                @if ($addon->status === 'suspended')
                                    <span class="rounded-full bg-rose-50 px-2.5 py-1 text-rose-700 ring-1 ring-rose-500/20">Suspended</span>
                                @elseif ($addon->status === 'retired')
                                    <span class="rounded-full bg-surface-muted px-2.5 py-1 text-ink-600 ring-1 ring-line">Retired</span>
                                @elseif ($addon->liveVersion)
                                    <span class="rounded-full px-2.5 py-1 ring-1 {{ $statusStyles['approved'] }}">Live {{ $addon->liveVersion->version }}</span>
                                @endif
                                @if ($addon->workingVersion)
                                    <span class="rounded-full px-2.5 py-1 ring-1 {{ $statusStyles[$addon->workingVersion->status] ?? '' }}">{{ $statusLabels[$addon->workingVersion->status] ?? '' }} {{ $addon->workingVersion->version }}</span>
                                @endif
                            </div>
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
@endsection
