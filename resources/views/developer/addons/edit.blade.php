@extends('layouts.developer')

@section('title', 'Edit '.$version->name)
@section('noindex', '1')

@php
    $input = 'w-full rounded-xl border border-line bg-white px-3.5 py-2.5 text-sm text-navy-900 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/30';
    $content = $version->content ?? [];
    $items = old('items', $content['items'] ?? []);
    $selectedScopes = old('scopes', $version->scopes ?? []);
    $selectedEvents = old('webhook_events', $version->webhook_events ?? []);
    $pricing = old('pricing', $version->pricing);
@endphp

@section('content')
    <a href="{{ route('developer.addons.show', $addon) }}" class="inline-flex items-center gap-1 text-sm font-medium text-brand-600 hover:text-brand-700"><x-heroicon-m-arrow-left class="h-4 w-4" aria-hidden="true" /> Back to the add-on</a>

    <div class="mt-3 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="font-display text-2xl font-semibold text-navy-900">Edit {{ $version->name }}</h1>
            <p class="mt-1 text-sm text-ink-600">{{ $addon->typeLabel() }} · draft version {{ $version->version }}. Customers {{ $addon->liveVersion ? 'keep seeing version '.$addon->liveVersion->version.' until this one is approved' : 'can\'t see it until it is approved' }}.</p>
        </div>
    </div>

    @if (session('client_secret'))
        <div class="mt-5 rounded-xl border border-gold-300 bg-gold-300/15 p-4">
            <p class="text-sm font-semibold text-navy-900">Your client secret — copy it now</p>
            <p class="mt-1 text-xs text-ink-600">We only show it once. Your client ID and the rest of the credentials are on the add-on's page.</p>
            <div class="mt-2 flex items-center gap-2"><code id="client-secret" class="min-w-0 flex-1 truncate rounded-lg bg-white px-3 py-2 font-mono text-xs ring-1 ring-line">{{ session('client_secret') }}</code><x-ui.copy-button target="client-secret" /></div>
        </div>
    @endif

    @if ($version->status === 'rejected' && $version->review_note)
        <div class="mt-5 rounded-xl bg-rose-50 p-4 text-sm text-rose-800"><p class="font-semibold">Reviewer's note</p><p class="mt-1 whitespace-pre-line">{{ $version->review_note }}</p></div>
    @endif

    <form method="POST" action="{{ route('developer.addons.update', $addon) }}" enctype="multipart/form-data" class="mt-6 space-y-6">
        @csrf
        @method('PUT')

        <x-ui.card title="Listing" description="What customers see in the marketplace." icon="megaphone">
            <div class="grid gap-5 sm:grid-cols-2">
                <x-ui.field label="Name" for="name" :error="$errors->first('name')">
                    <input id="name" name="name" value="{{ old('name', $version->name) }}" required maxlength="60" class="{{ $input }}">
                </x-ui.field>
                <x-ui.field label="Version" for="version" hint="Like 1.0.0. Raise it with each update." :error="$errors->first('version')">
                    <input id="version" name="version" value="{{ old('version', $version->version) }}" required class="{{ $input }} font-mono">
                </x-ui.field>
                <x-ui.field class="sm:col-span-2" label="Tagline" for="tagline" hint="One line, up to 140 characters." :error="$errors->first('tagline')">
                    <input id="tagline" name="tagline" value="{{ old('tagline', $version->tagline) }}" maxlength="140" class="{{ $input }}">
                </x-ui.field>
                <x-ui.field class="sm:col-span-2" label="Description" for="description" hint="What it does, who it's for and how it works. Plain text: blank lines start paragraphs, lines starting with - become bullets." :error="$errors->first('description')">
                    <textarea id="description" name="description" rows="8" maxlength="8000" class="{{ $input }}">{{ old('description', $version->description) }}</textarea>
                </x-ui.field>
                <x-ui.field label="Category" for="category" :error="$errors->first('category')">
                    <select id="category" name="category" class="{{ $input }}">
                        @foreach ($categories as $key => $label)
                            <option value="{{ $key }}" @selected(old('category', $version->category) === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </x-ui.field>
                <x-ui.field label="Icon" for="icon" hint="Square PNG, JPG or WebP, at least 128×128, up to 1 MB." :error="$errors->first('icon')">
                    <div class="flex items-center gap-3">
                        @include('partials.marketplace.addon-icon', ['version' => $version, 'size' => 'h-12 w-12 text-sm'])
                        <input id="icon" type="file" name="icon" accept="image/png,image/jpeg,image/webp" class="block w-full text-sm text-ink-600 file:mr-3 file:rounded-lg file:border-0 file:bg-surface-muted file:px-3 file:py-2 file:text-sm file:font-medium">
                    </div>
                </x-ui.field>
                <div class="sm:col-span-2">
                    <x-ui.field label="Screenshots" for="screenshots" optional hint="Up to 6 images, 4 MB each." :error="$errors->first('screenshots') ?: $errors->first('screenshots.*')">
                        <input id="screenshots" type="file" name="screenshots[]" multiple accept="image/png,image/jpeg,image/webp" class="block w-full text-sm text-ink-600 file:mr-3 file:rounded-lg file:border-0 file:bg-surface-muted file:px-3 file:py-2 file:text-sm file:font-medium">
                    </x-ui.field>
                    @if (! empty($version->screenshots))
                        <div class="mt-3 flex flex-wrap gap-3">
                            @foreach ($version->screenshots as $index => $path)
                                <label class="relative block">
                                    <img src="{{ $version->screenshotUrls()[$index] }}" alt="" class="h-24 w-auto rounded-lg border border-line object-cover">
                                    <span class="mt-1 flex items-center gap-1 text-xs text-ink-600"><input type="checkbox" name="remove_screenshots[]" value="{{ $path }}"> Remove</span>
                                </label>
                            @endforeach
                        </div>
                    @endif
                </div>
                <x-ui.field label="Website" for="website_url" optional :error="$errors->first('website_url')">
                    <input id="website_url" type="url" name="website_url" value="{{ old('website_url', $version->website_url) }}" placeholder="https://" class="{{ $input }}">
                </x-ui.field>
                <x-ui.field label="Support link" for="support_url" optional hint="A help page or mailto: link." :error="$errors->first('support_url')">
                    <input id="support_url" name="support_url" value="{{ old('support_url', $version->support_url) }}" placeholder="https://" class="{{ $input }}">
                </x-ui.field>
            </div>
        </x-ui.card>

        <x-ui.card title="Pricing" description="AffilStack bills customers and pays you your share." icon="banknotes">
            <div class="flex flex-wrap gap-3" data-pricing>
                @foreach (['free' => 'Free', 'monthly' => 'Monthly subscription'] as $key => $label)
                    <label class="flex cursor-pointer items-center gap-2 rounded-xl border border-line px-4 py-2.5 text-sm has-[:checked]:border-brand-500 has-[:checked]:ring-2 has-[:checked]:ring-brand-500/30">
                        <input type="radio" name="pricing" value="{{ $key }}" @checked($pricing === $key)> {{ $label }}
                    </label>
                @endforeach
            </div>
            <div class="mt-4 max-w-xs {{ $pricing === 'monthly' ? '' : 'hidden' }}" data-price-field>
                <x-ui.field label="Price per month (US$)" for="price" :hint="'Between $'.number_format(config('marketplace.min_price_cents') / 100, 2).' and $'.number_format(config('marketplace.max_price_cents') / 100, 2).'. Nigerian customers pay the naira equivalent.'" :error="$errors->first('price')">
                    <input id="price" type="number" name="price" step="0.01" min="{{ config('marketplace.min_price_cents') / 100 }}" max="{{ config('marketplace.max_price_cents') / 100 }}" value="{{ old('price', $version->price_cents ? number_format($version->price_cents / 100, 2, '.', '') : '') }}" class="{{ $input }}">
                </x-ui.field>
            </div>
            <p class="mt-3 text-xs text-ink-400">A price change only applies to new subscribers; existing customers keep the price they signed up at.</p>
        </x-ui.card>

        @if ($addon->isApp())
            <x-ui.card title="Connect with AffilStack" description="How customers connect your app, and what it can access." icon="link">
                <div class="grid gap-5 sm:grid-cols-2">
                    <x-ui.field class="sm:col-span-2" label="Connect page" for="install_url" hint="Where the Connect button in the marketplace sends customers: a page in your app that starts the OAuth flow." :error="$errors->first('install_url')">
                        <input id="install_url" type="url" name="install_url" value="{{ old('install_url', $version->install_url) }}" placeholder="https://yourapp.com/connect/affilstack" class="{{ $input }}">
                    </x-ui.field>
                    <x-ui.field class="sm:col-span-2" label="Redirect URLs" for="redirect_uris" hint="One per line, up to 10. Exactly as your app sends them. http://localhost is allowed for testing." :error="$errors->first('redirect_uris')">
                        <textarea id="redirect_uris" name="redirect_uris" rows="3" class="{{ $input }} font-mono">{{ old('redirect_uris', implode("\n", $version->redirect_uris ?? [])) }}</textarea>
                    </x-ui.field>
                    <x-ui.field class="sm:col-span-2" label="Privacy policy" for="privacy_url" :error="$errors->first('privacy_url')">
                        <input id="privacy_url" type="url" name="privacy_url" value="{{ old('privacy_url', $version->privacy_url) }}" placeholder="https://" class="{{ $input }}">
                    </x-ui.field>
                </div>
                <fieldset class="mt-6">
                    <legend class="text-sm font-medium text-navy-900">Permissions</legend>
                    <p class="text-xs text-ink-400">Ask only for what your app needs — customers see this list before they allow it.</p>
                    <div class="mt-3 grid gap-2 sm:grid-cols-2">
                        @foreach ($scopes as $scope => $description)
                            <label class="flex items-start gap-2 rounded-xl border border-line px-3 py-2.5 text-sm">
                                <input type="checkbox" name="scopes[]" value="{{ $scope }}" class="mt-0.5" @checked(in_array($scope, $selectedScopes, true))>
                                <span><span class="font-mono text-xs text-navy-900">{{ $scope }}</span><span class="block text-ink-600">{{ $description }}</span></span>
                            </label>
                        @endforeach
                    </div>
                </fieldset>
                <div class="mt-6 grid gap-5 sm:grid-cols-2">
                    <x-ui.field class="sm:col-span-2" label="Webhook URL" for="webhook_url" optional hint="We send the events below here for every customer who connects, signed with your webhook signing secret." :error="$errors->first('webhook_url')">
                        <input id="webhook_url" type="url" name="webhook_url" value="{{ old('webhook_url', $version->webhook_url) }}" placeholder="https://yourapp.com/webhooks/affilstack" class="{{ $input }}">
                    </x-ui.field>
                </div>
                <fieldset class="mt-3">
                    <legend class="text-sm font-medium text-navy-900">Webhook events</legend>
                    <div class="mt-2 grid gap-2 sm:grid-cols-2">
                        @foreach ($events as $event => $scope)
                            <label class="flex items-center gap-2 text-sm text-ink-600">
                                <input type="checkbox" name="webhook_events[]" value="{{ $event }}" @checked(in_array($event, $selectedEvents, true))>
                                <span class="font-mono text-xs text-navy-900">{{ $event }}</span> <span class="text-xs text-ink-400">needs {{ $scope }}</span>
                            </label>
                        @endforeach
                    </div>
                </fieldset>
            </x-ui.card>
        @elseif ($addon->type === 'template_pack')
            <x-ui.card title="Templates" description="Each one is added to the customer's template library. Up to 200." icon="document-duplicate">
                <div class="space-y-4" data-template-items>
                    @foreach (array_values($items ?: [['kind' => 'prompt', 'title' => '', 'body' => '']]) as $index => $item)
                        <div class="rounded-xl border border-line p-4" data-template-item>
                            <div class="grid gap-3 sm:grid-cols-4">
                                <select name="items[{{ $index }}][kind]" aria-label="Type" class="{{ $input }}">
                                    @foreach ($kinds as $key => $label)
                                        <option value="{{ $key }}" @selected(($item['kind'] ?? 'prompt') === $key)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                <input name="items[{{ $index }}][title]" value="{{ $item['title'] ?? '' }}" placeholder="Title" aria-label="Title" maxlength="160" class="{{ $input }} sm:col-span-3">
                            </div>
                            <textarea name="items[{{ $index }}][body]" rows="4" placeholder="The template. Use [PRODUCT], [AUDIENCE] and similar for parts customers fill in." aria-label="Template" class="{{ $input }} mt-3">{{ $item['body'] ?? '' }}</textarea>
                            <button type="button" class="mt-2 text-xs font-medium text-rose-600 hover:text-rose-700" data-remove-item>Remove</button>
                        </div>
                    @endforeach
                </div>
                <button type="button" class="mt-4 inline-flex items-center gap-1.5 rounded-xl bg-surface-muted px-3.5 py-2 text-sm font-medium text-navy-900 ring-1 ring-line hover:bg-white" data-add-item><x-heroicon-m-plus class="h-4 w-4" aria-hidden="true" /> Add a template</button>
                @error('items')<p class="mt-2 text-xs text-red-600">{{ \App\Support\Translation\Mark::text($message) }}</p>@enderror
            </x-ui.card>
        @else
            <x-ui.card title="The recipe" description="What customers import and how they set it up." icon="bolt">
                <div class="grid gap-5 sm:grid-cols-2">
                    <x-ui.field label="Platform" for="platform" :error="$errors->first('platform')">
                        <select id="platform" name="platform" class="{{ $input }}">
                            <option value="">Choose…</option>
                            @foreach ($platforms as $key => $label)
                                <option value="{{ $key }}" @selected(old('platform', $content['platform'] ?? '') === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </x-ui.field>
                    <x-ui.field label="Template link" for="share_url" optional hint="A shared Zap, Make template or n8n link." :error="$errors->first('share_url')">
                        <input id="share_url" type="url" name="share_url" value="{{ old('share_url', $content['share_url'] ?? '') }}" placeholder="https://" class="{{ $input }}">
                    </x-ui.field>
                    <x-ui.field class="sm:col-span-2" label="Blueprint file" for="blueprint" optional hint="The JSON export customers import, up to 512 KB." :error="$errors->first('blueprint')">
                        <input id="blueprint" type="file" name="blueprint" accept="application/json,.json" class="block w-full text-sm text-ink-600 file:mr-3 file:rounded-lg file:border-0 file:bg-surface-muted file:px-3 file:py-2 file:text-sm file:font-medium">
                        @if (! empty($content['blueprint']))
                            <label class="mt-2 flex items-center gap-2 text-xs text-ink-600"><input type="checkbox" name="remove_blueprint" value="1"> Remove {{ $content['blueprint_name'] ?? 'the current file' }}</label>
                        @endif
                    </x-ui.field>
                    <x-ui.field class="sm:col-span-2" label="Setup steps" for="steps" hint="Plain text. Lines starting with - become a list." :error="$errors->first('steps')">
                        <textarea id="steps" name="steps" rows="7" maxlength="6000" class="{{ $input }}">{{ old('steps', $content['steps'] ?? '') }}</textarea>
                    </x-ui.field>
                </div>
            </x-ui.card>
        @endif

        <x-ui.card title="What's new" icon="sparkles">
            <x-ui.field label="Notes for this version" for="changelog" optional hint="Shown to the reviewer. Helpful for updates." :error="$errors->first('changelog')">
                <textarea id="changelog" name="changelog" rows="3" maxlength="2000" class="{{ $input }}">{{ old('changelog', $version->changelog) }}</textarea>
            </x-ui.field>
        </x-ui.card>

        <div class="sticky bottom-0 -mx-4 flex flex-wrap items-center gap-3 border-t border-line bg-surface-muted/95 px-4 py-4 backdrop-blur sm:mx-0 sm:rounded-2xl sm:border sm:bg-surface">
            <x-ui.button name="action" value="save" icon="check">Save draft</x-ui.button>
            <x-ui.button name="action" value="submit" variant="gold" icon="paper-airplane">Save and submit for review</x-ui.button>
            <span class="text-xs text-ink-400">You can try the saved draft in your own account before submitting.</span>
        </div>
    </form>
@endsection

@push('scripts')
<script>
(function () {
    document.querySelectorAll('[data-pricing] input').forEach(function (radio) {
        radio.addEventListener('change', function () {
            document.querySelector('[data-price-field]')?.classList.toggle('hidden', radio.value !== 'monthly' || !radio.checked);
        });
    });

    var list = document.querySelector('[data-template-items]');
    if (!list) return;

    function renumber() {
        list.querySelectorAll('[data-template-item]').forEach(function (item, index) {
            item.querySelectorAll('[name^="items["]').forEach(function (field) {
                field.name = field.name.replace(/^items\[\d+\]/, 'items[' + index + ']');
            });
        });
    }

    document.querySelector('[data-add-item]')?.addEventListener('click', function () {
        var items = list.querySelectorAll('[data-template-item]');
        if (items.length >= 200) return;
        var copy = items[items.length - 1].cloneNode(true);
        copy.querySelectorAll('input, textarea').forEach(function (field) { field.value = ''; });
        list.appendChild(copy);
        renumber();
        copy.querySelector('input')?.focus();
    });

    list.addEventListener('click', function (event) {
        var button = event.target.closest('[data-remove-item]');
        if (!button) return;
        var items = list.querySelectorAll('[data-template-item]');
        if (items.length === 1) {
            items[0].querySelectorAll('input, textarea').forEach(function (field) { field.value = ''; });
        } else {
            button.closest('[data-template-item]').remove();
        }
        renumber();
    });
})();
</script>
@endpush
