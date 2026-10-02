@props(['name', 'label', 'description' => null, 'checked' => false, 'icon' => null])

{{-- An accessible on/off switch backed by a real checkbox (submits "1"
     when on, nothing when off — read it with $request->boolean()). --}}
<label {{ $attributes->merge(['class' => 'group flex cursor-pointer items-start justify-between gap-4 rounded-xl border border-line bg-white p-4 transition hover:border-brand-100 hover:bg-brand-50/40']) }}>
    <span class="flex items-start gap-3 min-w-0">
        @if ($icon)
            <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-brand-50 text-brand-600">
                <x-dynamic-component :component="'heroicon-o-'.$icon" class="h-5 w-5" />
            </span>
        @endif
        <span class="min-w-0">
            <span class="block text-sm font-medium text-navy-900">{{ $label }}</span>
            @if ($description)
                <span class="mt-0.5 block text-xs text-ink-600">{{ $description }}</span>
            @endif
        </span>
    </span>
    <span class="relative mt-0.5 inline-flex shrink-0">
        <input type="checkbox" name="{{ $name }}" value="1" class="peer sr-only" @checked($checked)>
        <span class="h-6 w-11 rounded-full bg-line transition peer-checked:bg-brand-500 peer-focus-visible:ring-2 peer-focus-visible:ring-brand-500 peer-focus-visible:ring-offset-2"></span>
        <span class="absolute left-0.5 top-0.5 h-5 w-5 rounded-full bg-white shadow transition peer-checked:translate-x-5"></span>
    </span>
</label>
