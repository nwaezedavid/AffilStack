{{-- Search and filters for a marketplace grid. Needs $action, $types, $categories. --}}
<form method="GET" action="{{ $action }}" class="flex flex-col gap-2 sm:flex-row sm:items-center" role="search">
    <label for="marketplace-q" class="sr-only">Search add-ons</label>
    <input id="marketplace-q" type="search" name="q" value="{{ request('q') }}" placeholder="Search add-ons" class="w-full rounded-xl border border-line bg-white px-3.5 py-2.5 text-sm text-navy-900 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/30 sm:flex-1">
    <label for="marketplace-type" class="sr-only">Type</label>
    <select id="marketplace-type" name="type" class="rounded-xl border border-line bg-white px-3 py-2.5 text-sm text-navy-900">
        <option value="">All types</option>
        @foreach ($types as $key => $type)
            <option value="{{ $key }}" @selected(request('type') === $key)>{{ $type['label'] }}</option>
        @endforeach
    </select>
    <label for="marketplace-category" class="sr-only">Category</label>
    <select id="marketplace-category" name="category" class="rounded-xl border border-line bg-white px-3 py-2.5 text-sm text-navy-900">
        <option value="">All categories</option>
        @foreach ($categories as $key => $label)
            <option value="{{ $key }}" @selected(request('category') === $key)>{{ $label }}</option>
        @endforeach
    </select>
    <button type="submit" class="inline-flex items-center justify-center gap-1.5 rounded-xl bg-navy-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-navy-800">
        <x-heroicon-m-magnifying-glass class="h-4 w-4" aria-hidden="true" /> Search
    </button>
</form>
