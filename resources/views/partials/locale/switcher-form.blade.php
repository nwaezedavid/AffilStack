{{-- Language picker for the dashboard, partner portal and sign-in pages: saves the choice (account + cookie) — see LocaleController. Tones: dark (sidebars), light. --}}
@php
    $localeOptions = \App\Support\Translation\Languages::switcherOptions();
    $currentLocale = app()->getLocale();
    $tone = $tone ?? 'light';
@endphp

@if (count($localeOptions) > 1)
    <form method="POST" action="{{ route('locale.update') }}" class="flex items-center gap-2" translate="no">
        @csrf
        <label for="locale-switcher-{{ $tone }}" class="flex items-center gap-1.5 text-xs {{ $tone === 'dark' ? 'text-white/60' : 'text-ink-400' }}">
            <x-heroicon-o-language class="h-4 w-4" aria-hidden="true" />
            <span class="sr-only">Language</span>
        </label>
        <select id="locale-switcher-{{ $tone }}" name="locale" onchange="this.form.submit()"
                class="min-w-0 flex-1 rounded-lg border px-2 py-1.5 text-xs focus:outline-none focus:ring-2 focus:ring-brand-500 {{ $tone === 'dark' ? 'border-white/15 bg-white/5 text-white [&>option]:text-navy-900' : 'border-line bg-white text-navy-900' }}">
            @foreach ($localeOptions as $code => $label)
                <option value="{{ $code }}" lang="{{ $code }}" @selected($code === $currentLocale)>{{ $label }}</option>
            @endforeach
        </select>
        <noscript><button type="submit" class="text-xs underline">OK</button></noscript>
    </form>
@endif
