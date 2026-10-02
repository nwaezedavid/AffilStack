{{-- The Get Started research form. Posts to offers.store; with JavaScript
     it submits in the background and hands the new offer to the results
     area below ([data-research-results]) to poll — no page reload. --}}
@php($returnTo = $returnTo ?? null)
<form method="POST" action="{{ route('offers.store') }}" data-research-form data-tour="research-form" novalidate
      class="relative overflow-hidden rounded-3xl border border-line bg-white p-5 shadow-[0_10px_40px_-12px_rgba(11,30,61,0.18)] sm:p-7">
    @csrf
    @if ($returnTo)
        <input type="hidden" name="return_to" value="{{ $returnTo }}">
    @endif
    @if (request('clip_id'))
        <input type="hidden" name="clip_id" value="{{ request('clip_id') }}">
        <p class="mb-4 rounded-xl border border-brand-100 bg-brand-50 px-3.5 py-2.5 text-xs text-brand-700">
            Your captured research will be attached to this product once it's created.
        </p>
    @endif

    <div class="pointer-events-none absolute -right-20 -top-24 h-56 w-56 rounded-full bg-gradient-to-br from-brand-100 to-gold-300/30 blur-2xl" aria-hidden="true"></div>

    <div class="relative flex items-start gap-3 mb-5">
        <span class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-brand-500 to-navy-900 text-white shadow-md">
            <x-heroicon-o-sparkles class="h-6 w-6" />
        </span>
        <div>
            <h3 class="font-display text-lg font-semibold text-navy-900">Research a product with AI</h3>
            <p class="text-sm text-ink-600">Tell us what you're promoting. Our AI finds out what it does, who buys it, where to find them, and the exact keywords to search on Google Maps and LinkedIn.</p>
        </div>
    </div>

    <div class="relative grid gap-4 sm:grid-cols-2 [&>*]:min-w-0">
        <div>
            <label for="product_name" class="block text-sm font-medium text-navy-900 mb-1.5">Product name</label>
            <input id="product_name" name="product_name" value="{{ old('product_name', request('product_name')) }}" required maxlength="255"
                   placeholder="e.g. Systeme.io, Doola LLC Formation"
                   class="w-full rounded-xl border border-line bg-white px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
        </div>
        <div>
            <label for="product_url" class="block text-sm font-medium text-navy-900 mb-1.5">Official product URL</label>
            <input id="product_url" name="product_url" type="url" value="{{ old('product_url', request('product_url')) }}" required maxlength="2048"
                   placeholder="https://the-product.com"
                   class="w-full rounded-xl border border-line bg-white px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
        </div>
        <div>
            <label for="affiliate_link" class="block text-sm font-medium text-navy-900 mb-1.5">Your affiliate link <span class="font-normal text-ink-400">(optional)</span></label>
            <input id="affiliate_link" name="affiliate_link" type="url" value="{{ old('affiliate_link', request('affiliate_link')) }}" maxlength="2048"
                   placeholder="https://your-tracking-link…"
                   class="w-full rounded-xl border border-line bg-white px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
            <p class="mt-1 text-xs text-ink-400">We cloak and track it in everything AffilStack writes.</p>
        </div>
        <div>
            @include('dashboard.offers.partials.network-picker')
        </div>
    </div>

    <div data-form-errors hidden role="alert" class="relative mt-4 rounded-xl border border-red-200 bg-red-50 px-3.5 py-2.5 text-sm text-red-700"></div>

    <div class="relative mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-between">
        <p class="flex items-center gap-1.5 text-xs text-ink-600">
            <x-heroicon-o-bolt class="h-4 w-4 text-gold-500" />
            Uses {{ config('credits.costs.research') }} credits · takes about a minute
        </p>
        <button type="submit" data-research-submit
                class="inline-flex items-center justify-center gap-2 rounded-full bg-gradient-to-r from-brand-600 to-navy-900 px-6 py-3 text-sm font-semibold text-white shadow-lg shadow-brand-600/25 transition hover:from-brand-500 hover:to-navy-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-brand-500 disabled:opacity-60">
            <x-heroicon-m-magnifying-glass-circle class="h-5 w-5" />
            <span data-research-submit-label>Research with AI</span>
        </button>
    </div>
</form>
