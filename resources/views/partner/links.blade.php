@extends('layouts.partner')

@section('title', 'Links & campaigns')

@php
    $input = 'w-full rounded-xl border border-line bg-white px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-600';
    $newCampaign = session('new_campaign');
    $shareText = 'I use AffilStack to research affiliate offers, find buyers and write campaigns with AI. Worth a look:';
@endphp

@section('content')
    <div class="grid gap-6 lg:grid-cols-5">
        <x-ui.card class="lg:col-span-3" title="Your referral link" :description="'Anyone who clicks it is tracked to you for '.\App\Support\ReferralProgram::cookieDays().' days.'" icon="link">
            <label for="main-link" class="sr-only">Your referral link</label>
            <div class="flex flex-col gap-2 sm:flex-row">
                <input id="main-link" type="text" readonly value="{{ $links['plain'] }}" onclick="this.select()"
                       class="w-full min-w-0 rounded-xl border border-line bg-surface-muted px-3.5 py-2.5 font-mono text-sm text-navy-900 focus:outline-none focus:ring-2 focus:ring-emerald-600">
                <x-ui.copy-button target="main-link" label="Copy link" variant="primary" size="md" class="shrink-0" />
            </div>
            <div class="mt-4 flex flex-wrap items-center gap-2">
                <span class="mr-1 text-xs font-medium text-ink-600">Share now:</span>
                <a href="https://twitter.com/intent/tweet?text={{ rawurlencode($shareText.' '.$links['plain']) }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 rounded-full bg-white px-3 py-1.5 text-xs font-medium text-navy-900 ring-1 ring-line hover:bg-surface-muted"><x-ui.brand-icon name="x" class="h-3.5 w-3.5" /> X</a>
                <a href="https://www.linkedin.com/sharing/share-offsite/?url={{ rawurlencode($links['plain']) }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 rounded-full bg-white px-3 py-1.5 text-xs font-medium text-navy-900 ring-1 ring-line hover:bg-surface-muted"><x-ui.brand-icon name="linkedin" class="h-3.5 w-3.5" /> LinkedIn</a>
                <a href="https://www.facebook.com/sharer/sharer.php?u={{ rawurlencode($links['plain']) }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 rounded-full bg-white px-3 py-1.5 text-xs font-medium text-navy-900 ring-1 ring-line hover:bg-surface-muted"><x-ui.brand-icon name="facebook" class="h-3.5 w-3.5" /> Facebook</a>
                <a href="https://wa.me/?text={{ rawurlencode($shareText.' '.$links['plain']) }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 rounded-full bg-white px-3 py-1.5 text-xs font-medium text-navy-900 ring-1 ring-line hover:bg-surface-muted"><x-ui.brand-icon name="whatsapp" class="h-3.5 w-3.5" /> WhatsApp</a>
            </div>
        </x-ui.card>

        <x-ui.card class="lg:col-span-2" title="New campaign link" description="One link per place you promote, so you can see which one earns." icon="plus-circle">
            <form method="POST" action="{{ route('partner.campaigns.store') }}" class="space-y-3">
                @csrf
                <div>
                    <label for="campaign-name" class="mb-1 block text-xs font-medium text-ink-900">Name</label>
                    <input id="campaign-name" name="name" value="{{ old('name') }}" required maxlength="80" placeholder="e.g. YouTube review, June newsletter" class="{{ $input }}">
                </div>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label for="campaign-channel" class="mb-1 block text-xs font-medium text-ink-900">Where you'll share it</label>
                        <select id="campaign-channel" name="channel" class="{{ $input }}">
                            @foreach ($channels as $value => $label)
                                <option value="{{ $value }}" @selected(old('channel') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="campaign-landing" class="mb-1 block text-xs font-medium text-ink-900">Send visitors to</label>
                        <select id="campaign-landing" name="landing_path" class="{{ $input }}">
                            @foreach ($landingPages as $path => $label)
                                <option value="{{ $path }}" @selected(old('landing_path', '/pricing') === $path)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <button type="submit" class="w-full rounded-full bg-emerald-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600 focus-visible:ring-offset-2">Create link</button>
            </form>
        </x-ui.card>
    </div>

    <x-ui.card class="mt-6" title="Your campaign links" description="All-time results. Pick a period on the Overview page for more." icon="tag" padding="p-0">
        @if ($campaigns->isEmpty())
            <div class="px-5 py-8 text-center sm:px-6">
                <p class="font-display font-semibold text-navy-900">No campaign links yet</p>
                <p class="mx-auto mt-1 max-w-md text-sm text-ink-600">Make one for your YouTube description, one for your newsletter, one for your WhatsApp status. Each tracks its own clicks, sign-ups and earnings.</p>
            </div>
        @else
            <ul class="divide-y divide-line">
                @foreach ($campaigns as $row)
                    @php($campaign = $row['campaign'])
                    <li class="px-5 py-4 sm:px-6 {{ $newCampaign === $campaign->id ? 'bg-emerald-50/60' : '' }}">
                        <div class="flex flex-col gap-3 lg:flex-row lg:items-center">
                            <div class="min-w-0 lg:w-64">
                                <p class="truncate font-medium text-navy-900">{{ $campaign->name }}</p>
                                <p class="text-xs text-ink-400">{{ $campaign->channelLabel() }} · lands on {{ $landingPages[$campaign->landing_path] ?? $campaign->landing_path }}</p>
                            </div>
                            <div class="flex min-w-0 flex-1 items-center gap-2 rounded-lg bg-surface-muted px-3 py-2">
                                <code id="campaign-link-{{ $campaign->id }}" class="min-w-0 flex-1 truncate font-mono text-xs text-navy-900">{{ $row['link'] }}</code>
                                <x-ui.copy-button :target="'campaign-link-'.$campaign->id" label="Copy" size="xs" class="shrink-0" />
                            </div>
                            <dl class="flex shrink-0 gap-5 text-center text-xs">
                                <div><dt class="text-ink-400">Clicks</dt><dd class="font-mono font-semibold tabular-nums text-navy-900">{{ number_format($campaign->clicks_count) }}</dd></div>
                                <div><dt class="text-ink-400">Sign-ups</dt><dd class="font-mono font-semibold tabular-nums text-navy-900">{{ number_format($campaign->referrals_count) }}</dd></div>
                                <div><dt class="text-ink-400">Paying</dt><dd class="font-mono font-semibold tabular-nums text-navy-900">{{ number_format($campaign->paying_count) }}</dd></div>
                            </dl>
                            <form method="POST" action="{{ route('partner.campaigns.destroy', $campaign) }}" onsubmit="return confirm('Delete this campaign link? It will stop tracking new clicks.')" class="shrink-0">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="rounded-full p-2 text-ink-400 hover:bg-rose-50 hover:text-rose-600" aria-label="Delete {{ $campaign->name }}"><x-heroicon-o-trash class="h-4 w-4" /></button>
                            </form>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-ui.card>

    <h2 class="mt-10 mb-4 font-display text-lg font-semibold text-navy-900">Text links & brand assets</h2>
    @include('dashboard.referrals.partials.links')
@endsection
