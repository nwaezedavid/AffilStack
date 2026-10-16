@props(['label', 'for' => null, 'hint' => null, 'error' => null, 'optional' => false])

{{-- A labelled form field: label, the control (slot), a hint line and the
     validation error for it. Pass $error as the message string. --}}
<div {{ $attributes->merge(['class' => 'space-y-1.5']) }}>
    <label @if ($for) for="{{ $for }}" @endif class="flex items-center justify-between gap-2 text-sm font-medium text-navy-900">
        <span>{{ $label }}</span>
        @if ($optional)
            <span class="text-[11px] font-normal text-ink-400">Optional</span>
        @endif
    </label>
    {{ $slot }}
    @if ($error)
        <p class="flex items-start gap-1 text-xs text-red-600" @if ($for) id="{{ $for }}-error" @endif>
            <x-heroicon-m-exclamation-circle class="h-4 w-4 shrink-0" /> {{ \App\Support\Translation\Mark::text($error) }}
        </p>
    @elseif ($hint)
        <p class="text-xs text-ink-400" @if ($for) id="{{ $for }}-hint" @endif>{{ $hint }}</p>
    @endif
</div>
