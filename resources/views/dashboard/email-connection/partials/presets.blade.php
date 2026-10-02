{{-- Provider presets: picking one prefills host/port/security for this
     form (see the page script) and shows where to get the password. --}}
@php
    $marks = [
        'hostinger' => ['H', '#673DE6'],
        'zoho' => ['Z', '#E42527'],
        'yahoo' => ['Y!', '#6001D2'],
    ];
@endphp
<fieldset>
    <legend class="block text-xs font-medium text-ink-600 mb-2">Your email provider</legend>
    <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
        @foreach (config('crm.presets') as $key => $preset)
            <label class="relative cursor-pointer">
                <input type="radio" name="{{ $kind === 'smtp' ? 'preset' : 'imap_preset' }}" value="{{ $key }}" data-preset class="peer sr-only" @checked(old($kind === 'smtp' ? 'preset' : 'imap_preset', $selected) === $key)>
                <span class="flex items-center gap-2 rounded-xl border border-line bg-white px-3 py-2.5 text-sm text-ink-900 transition hover:border-brand-500/50 peer-checked:border-brand-500 peer-checked:bg-brand-50 peer-checked:ring-2 peer-checked:ring-brand-500/25 peer-focus-visible:ring-2 peer-focus-visible:ring-brand-500">
                    @if ($key === 'gmail')
                        <x-ui.brand-icon name="gmail" class="h-5 w-5" />
                    @elseif ($key === 'outlook')
                        <x-ui.brand-icon name="microsoft" class="h-5 w-5" />
                    @elseif (isset($marks[$key]))
                        <span class="inline-flex h-5 w-5 shrink-0 items-center justify-center rounded-md text-[10px] font-bold text-white" style="background: {{ $marks[$key][1] }}">{{ $marks[$key][0] }}</span>
                    @else
                        <x-heroicon-m-server class="h-5 w-5 text-ink-400" />
                    @endif
                    <span class="truncate">{{ $preset['label'] }}</span>
                </span>
            </label>
        @endforeach
    </div>
</fieldset>
