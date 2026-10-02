@extends('layouts.app')

@section('title', 'Create Campaign')

@section('content')
    <x-ui.page-header title="Create a campaign" eyebrow="Step 1 of 2 · Choose who & how" icon="sparkles" tone="violet"
        subtitle="Pick the list, tell the AI what you want, and it writes a personalised cold email for that product from your research. You review everything before a single email is sent." />

    @if (! $connection || ! $connection->isVerified())
        <x-ui.next-step class="mb-6" title="Before you can send" :href="route('email-connections.index')" action="Connect email">
            Campaigns send from your own Gmail or business email. You can write the campaign now and connect your email before approving it.
        </x-ui.next-step>
    @endif

    @if ($lists->isEmpty())
        <x-ui.empty-state icon="queue-list" title="You don't have any lists yet" :href="route('offers.create')" action="Research a product">
            Each product you research gets its own contact list automatically. Research one, add prospects to it, then come back here.
        </x-ui.empty-state>
    @else
        <form method="POST" action="{{ route('crm.campaigns.store') }}" class="grid grid-cols-1 gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]">
            @csrf
            <div class="space-y-6 min-w-0">
                <x-ui.card title="Who should get it?" icon="users" description="Only contacts on this list receive the campaign.">
                    <fieldset>
                        <legend class="sr-only">Contact list</legend>
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            @foreach ($lists as $list)
                                <label class="relative cursor-pointer">
                                    <input type="radio" name="list_id" value="{{ $list->id }}" class="peer sr-only" @checked(old('list_id', $selected?->id) == $list->id)>
                                    <span class="flex h-full items-start gap-3 rounded-xl border border-line bg-white p-4 transition peer-checked:border-brand-500 peer-checked:bg-brand-50/60 peer-checked:ring-2 peer-checked:ring-brand-500/30 peer-focus-visible:ring-2 peer-focus-visible:ring-brand-500 hover:border-brand-500/50">
                                        <span class="mt-1 h-3 w-3 shrink-0 rounded-full {{ $list->dotClass() }}" aria-hidden="true"></span>
                                        <span class="min-w-0">
                                            <span class="block font-medium text-sm text-ink-900">{{ $list->name }}</span>
                                            <span class="block text-xs text-ink-600">{{ number_format($list->contacts_count) }} contact{{ $list->contacts_count === 1 ? '' : 's' }} · {{ $list->offer ? 'Product: '.$list->offer->product_name : 'Custom list' }}</span>
                                        </span>
                                    </span>
                                    <x-heroicon-s-check-circle class="pointer-events-none absolute right-3 top-3 hidden h-5 w-5 text-brand-600 peer-checked:block" />
                                </label>
                            @endforeach
                        </div>
                    </fieldset>
                </x-ui.card>

                <x-ui.card title="What should the email do?" icon="pencil-square" description="Optional — the AI already knows the product from your research.">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <label class="block sm:col-span-2">
                            <span class="block text-xs font-medium text-ink-600 mb-1">Campaign name</span>
                            <input name="name" value="{{ old('name') }}" maxlength="150" placeholder="{{ ($selected?->offer?->product_name ?? $selected?->name ?? 'My').' outreach — '.now()->format('M j') }}" class="w-full rounded-lg border border-line px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
                        </label>
                        <label class="block sm:col-span-2">
                            <span class="block text-xs font-medium text-ink-600 mb-1">Goal or angle</span>
                            <textarea name="goal" rows="3" maxlength="500" placeholder="e.g. Get dentists in Lagos to book a free demo — mention they lose patients to missed calls." class="w-full rounded-lg border border-line px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">{{ old('goal') }}</textarea>
                        </label>
                        <label class="block">
                            <span class="block text-xs font-medium text-ink-600 mb-1">Tone</span>
                            <select name="tone" class="w-full rounded-lg border border-line px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
                                @foreach (['friendly and professional', 'warm and casual', 'direct and concise', 'consultative expert'] as $tone)
                                    <option value="{{ $tone }}" @selected(old('tone') === $tone)>{{ ucfirst($tone) }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="block">
                            <span class="block text-xs font-medium text-ink-600 mb-1">Follow-up emails if no reply</span>
                            <select name="follow_ups" class="w-full rounded-lg border border-line px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
                                @foreach ([0 => 'None', 1 => '1 follow-up', 2 => '2 follow-ups (recommended)', 3 => '3 follow-ups'] as $value => $label)
                                    <option value="{{ $value }}" @selected((int) old('follow_ups', 2) === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="sm:col-span-2 flex items-start gap-3 rounded-xl border border-line bg-surface-muted/60 p-3">
                            <input type="checkbox" name="personalize_opening" value="1" @checked(old('personalize_opening')) class="mt-0.5 h-4 w-4 rounded border-line text-brand-600 focus:ring-brand-500">
                            <span class="text-sm">
                                <span class="font-medium text-ink-900">Add an AI-written personal first line for each contact</span>
                                <span class="block text-xs text-ink-600">Mentions something about their business. Costs {{ $openingCost }} credit{{ $openingCost === 1 ? '' : 's' }} per contact, charged as each email sends — skipped automatically if you run out.</span>
                            </span>
                        </label>
                    </div>
                </x-ui.card>
            </div>

            <aside class="space-y-4 lg:sticky lg:top-6 self-start">
                <div class="rounded-2xl bg-gradient-to-br from-navy-900 via-navy-800 to-violet-700 p-5 text-white shadow-sm">
                    <p class="text-[11px] font-mono uppercase tracking-[0.18em] text-gold-300">AI campaign writer</p>
                    <p class="mt-2 font-display text-3xl font-semibold">{{ $cost }} <span class="text-base font-normal text-white/70">credits</span></p>
                    <p class="mt-1 text-sm text-white/80">You have <span class="font-semibold text-white">{{ number_format($balance) }}</span> credits.</p>
                    <ul class="mt-4 space-y-2 text-sm text-white/85">
                        <li class="flex gap-2"><x-heroicon-m-check class="h-4 w-4 shrink-0 text-gold-300" /> Subject + email written for this product</li>
                        <li class="flex gap-2"><x-heroicon-m-check class="h-4 w-4 shrink-0 text-gold-300" /> Personalised with each contact's details</li>
                        <li class="flex gap-2"><x-heroicon-m-check class="h-4 w-4 shrink-0 text-gold-300" /> Follow-ups that stop when they reply</li>
                    </ul>
                    <button type="submit" name="mode" value="ai" @disabled($balance < $cost) class="mt-5 inline-flex w-full items-center justify-center gap-2 rounded-full bg-gold-400 px-4 py-2.5 text-sm font-semibold text-navy-950 shadow-sm transition hover:bg-gold-300 disabled:cursor-not-allowed disabled:opacity-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-white">
                        <x-heroicon-m-sparkles class="h-4 w-4" /> Write it with AI
                    </button>
                    @if ($balance < $cost)
                        <a href="{{ route('credit-topups.index') }}" class="mt-2 block text-center text-xs text-gold-300 underline">Top up credits</a>
                    @endif
                </div>
                <button type="submit" name="mode" value="manual" class="w-full rounded-full bg-white px-4 py-2.5 text-sm font-medium text-navy-900 ring-1 ring-line transition hover:bg-surface-muted focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500">
                    Write it myself (free)
                </button>
                <p class="px-2 text-xs text-ink-400">Nothing is sent yet — you'll edit and preview the email on the next screen.</p>
            </aside>
        </form>
    @endif
@endsection
