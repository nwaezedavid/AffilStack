@extends('layouts.app')

@section('title', $version?->name ?? 'Add-on')

@section('content')
    @php($addon = $install->addon)
    <div class="mb-4">
        <a href="{{ route('addons.index') }}" class="inline-flex items-center gap-1 text-sm font-medium text-brand-600 hover:text-brand-700"><x-heroicon-m-arrow-left class="h-4 w-4" aria-hidden="true" /> My add-ons</a>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <x-ui.card padding="p-6">
                <div class="flex items-start gap-4">
                    @include('partials.marketplace.addon-icon', ['version' => $version, 'size' => 'h-14 w-14 text-base'])
                    <div class="min-w-0 flex-1">
                        <h2 class="font-display text-xl font-semibold text-navy-900">{{ $version?->name }}</h2>
                        <p class="mt-1 text-sm text-ink-400">{{ $addon->typeLabel() }} · by {{ $addon->developer?->display_name }}</p>
                        <p class="mt-2 text-sm text-ink-600">{{ $version?->tagline }}</p>
                    </div>
                    <a href="{{ route('marketplace.addon', $addon) }}" class="text-sm font-medium text-brand-600 hover:text-brand-700">Listing</a>
                </div>
            </x-ui.card>

            @if ($install->status === 'pending_payment')
                <div class="rounded-2xl border border-amber-200 bg-amber-50 p-5 text-sm text-amber-900">This add-on is waiting for your first payment. <a href="{{ route('marketplace.addon', $addon) }}" class="font-semibold underline">Subscribe</a> to start using it.</div>
            @elseif ($addon->isApp())
                <x-ui.card title="Connection" icon="link" padding="p-5 sm:p-6">
                    <p class="text-sm text-ink-600">
                        @if ($connected)
                            <span class="inline-flex items-center gap-1.5 font-medium text-emerald-700"><x-heroicon-m-check-circle class="h-5 w-5" aria-hidden="true" /> Connected.</span> {{ $version?->name }} can use your account with the permissions below.
                        @else
                            Not connected right now. Connect again from {{ $version?->name }} to switch it back on.
                        @endif
                    </p>
                    <p class="mt-4 text-sm font-semibold text-navy-900">You allowed it to</p>
                    <ul class="mt-2 space-y-1.5 text-sm text-ink-600">
                        @forelse ($install->scopes ?? [] as $scope)
                            <li class="flex gap-2"><x-heroicon-m-check class="mt-0.5 h-4 w-4 shrink-0 text-emerald-600" aria-hidden="true" /> {{ config('marketplace.scopes.'.$scope, $scope) }}</li>
                        @empty
                            <li>Nothing yet.</li>
                        @endforelse
                    </ul>
                    @if ($version?->install_url)
                        <div class="mt-5"><x-ui.button variant="secondary" :href="$version->install_url" icon="arrow-top-right-on-square">Open {{ $version->name }}</x-ui.button></div>
                    @endif
                </x-ui.card>
            @elseif ($addon->type === 'template_pack')
                <x-ui.card title="Templates in this pack" icon="document-duplicate" padding="p-5 sm:p-6">
                    <x-slot:headerActions>
                        <x-ui.button variant="secondary" size="sm" :href="route('templates.index')">Template library</x-ui.button>
                    </x-slot:headerActions>
                    <ul class="divide-y divide-line">
                        @forelse ($templates as $template)
                            <li class="flex items-center justify-between gap-3 py-2.5">
                                <span class="min-w-0"><span class="block truncate text-sm font-medium text-navy-900">{{ $template->title }}</span><span class="text-xs text-ink-400">{{ config('marketplace.template_kinds.'.$template->kind, $template->kind) }}</span></span>
                                <x-ui.copy-button :text="$template->body" />
                            </li>
                        @empty
                            <li class="py-2 text-sm text-ink-600">No templates yet.</li>
                        @endforelse
                    </ul>
                </x-ui.card>
            @elseif ($addon->type === 'automation')
                @php($content = $version?->content ?? [])
                <x-ui.card title="Set it up" icon="bolt" padding="p-5 sm:p-6">
                    <p class="text-sm text-ink-600">A {{ config('marketplace.automation_platforms.'.($content['platform'] ?? ''), 'automation') }} workflow. Follow these steps:</p>
                    <div class="mt-4">@include('partials.marketplace.description', ['text' => (string) ($content['steps'] ?? '')])</div>
                    <div class="mt-5 flex flex-wrap gap-2">
                        @if (! empty($content['share_url']))
                            <x-ui.button :href="$content['share_url']" icon="arrow-top-right-on-square">Open the template</x-ui.button>
                        @endif
                        @if (! empty($content['blueprint']))
                            <x-ui.button variant="secondary" :href="route('addons.blueprint', $install)" icon="arrow-down-tray">Download blueprint</x-ui.button>
                        @endif
                        <x-ui.button variant="ghost" :href="route('api-access.index')" icon="key">Create an API key</x-ui.button>
                    </div>
                    <p class="mt-3 text-xs text-ink-400">Most recipes ask for an AffilStack API key when you set them up. Create one on the API & Integrations page.</p>
                </x-ui.card>
            @endif
        </div>

        <div class="space-y-4">
            <x-ui.card title="{{ $install->isPaid() ? 'Subscription' : 'Plan' }}" icon="credit-card" padding="p-5">
                @if ($install->isPaid())
                    <p class="text-sm text-navy-900"><span class="font-semibold">${{ number_format($install->price_cents / 100, 2) }}</span> / month</p>
                    @if ($install->status === 'past_due')
                        <p class="mt-2 rounded-lg bg-rose-50 px-3 py-2 text-sm text-rose-700">We couldn't charge your card. Pay now to keep it — it switches off on {{ $install->current_period_end?->copy()->addDays((int) config('marketplace.grace_days'))->format('M j, Y') }}.</p>
                        <form method="POST" action="{{ route('addons.pay', $install) }}" class="mt-3 space-y-2">
                            @csrf
                            @if (count($gateways) > 1)
                                <select name="gateway" aria-label="Pay with" class="w-full rounded-xl border border-line bg-white px-3 py-2 text-sm">
                                    @foreach ($gateways as $gateway)
                                        <option value="{{ $gateway->key() }}">{{ $gateway->label() }}</option>
                                    @endforeach
                                </select>
                            @endif
                            <x-ui.button variant="gold" icon="credit-card" class="w-full justify-center">Pay now</x-ui.button>
                        </form>
                    @elseif ($install->cancel_at_period_end)
                        <p class="mt-2 text-sm text-ink-600">Cancelled — ends {{ $install->current_period_end?->format('M j, Y') }}.</p>
                        @if ($addon->isListed())
                            <form method="POST" action="{{ route('addons.resume', $install) }}" class="mt-3">@csrf<x-ui.button variant="secondary" class="w-full justify-center">Keep my subscription</x-ui.button></form>
                        @endif
                    @elseif ($install->status === 'active')
                        <p class="mt-2 text-sm text-ink-600">Renews {{ $install->current_period_end?->format('M j, Y') }}{{ $install->paymentMethod ? ' on '.$install->paymentMethod->display() : '' }}.</p>
                        <form method="POST" action="{{ route('addons.cancel', $install) }}" class="mt-3" onsubmit="return confirm('Cancel this subscription? You keep it until the end of the month you paid for.')">@csrf<x-ui.button variant="secondary" class="w-full justify-center">Cancel subscription</x-ui.button></form>
                    @endif
                @else
                    <p class="text-sm text-ink-600">Free.</p>
                @endif
            </x-ui.card>

            <x-ui.card padding="p-5">
                <p class="text-sm text-ink-600">Need help with it? Contact {{ $addon->developer?->display_name }} at <a href="mailto:{{ $addon->developer?->support_email }}" class="font-medium text-brand-600">{{ $addon->developer?->support_email }}</a>.</p>
                <form method="POST" action="{{ route('addons.destroy', $install) }}" class="mt-4" onsubmit="return confirm('Remove this add-on? {{ $addon->isApp() ? 'The app will lose access to your account.' : 'Its templates are removed from your library.' }}{{ $install->isPaid() && $install->status === 'active' ? ' Your subscription ends now, without a refund.' : '' }}')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="text-sm font-medium text-rose-600 hover:text-rose-700">{{ $addon->isApp() ? 'Disconnect and remove' : 'Remove from my account' }}</button>
                </form>
            </x-ui.card>
        </div>
    </div>
@endsection
