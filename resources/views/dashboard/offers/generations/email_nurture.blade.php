@php
    $nurtureContact = \App\Models\CrmContact::find($gen->input['contact_id'] ?? null);
@endphp
<p class="flex items-center gap-2 text-sm">
    <span class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-emerald-100 text-xs font-semibold text-emerald-700">{{ $nurtureContact ? mb_strtoupper(mb_substr($nurtureContact->name ?: '?', 0, 1)) : '?' }}</span>
    <span><span class="text-ink-400">To:</span> <span class="font-medium text-navy-900">{{ $nurtureContact ? $nurtureContact->name.($nurtureContact->company ? ' · '.$nurtureContact->company : '') : 'Contact no longer exists' }}</span></span>
</p>
<ol class="mt-3 space-y-2.5">
    @foreach ($gen->output_meta['emails'] ?? [] as $email)
        @php
            $lastSend = $gen->emailSends->where('sequence_step', $email['step'] ?? null)->sortByDesc('created_at')->first();
            $body = $offer->cloak($email['body'] ?? '', $gen->module, generation: $gen);
        @endphp
        <li class="rounded-2xl bg-white p-4 ring-1 ring-line">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <span class="inline-flex items-center gap-1.5 text-xs font-semibold text-emerald-700"><span class="inline-flex h-5 w-5 items-center justify-center rounded-full bg-emerald-600 text-[10px] text-white">{{ $email['step'] ?? '' }}</span> {{ $email['send_timing'] ?? '' }}</span>
                @if ($lastSend)
                    <span class="flex flex-wrap items-center gap-1.5 text-[11px]">
                        @if ($lastSend->status === 'failed')
                            <x-ui.status-pill status="failed" label="Send failed" />
                        @else
                            <x-ui.status-pill status="verified" :label="'Sent '.$lastSend->sent_at?->diffForHumans()" />
                            @if ($lastSend->opened_at)
                                <x-ui.status-pill status="connected" label="Opened" />
                            @endif
                            @if ($lastSend->first_clicked_at)
                                <span class="inline-flex items-center rounded-full bg-amber-50 px-2.5 py-1 text-xs font-medium text-amber-700 ring-1 ring-amber-200">Clicked</span>
                            @endif
                        @endif
                    </span>
                @endif
            </div>
            <p class="mt-2 font-semibold text-navy-900">{{ $email['subject'] ?? '' }}</p>
            <p class="mt-1 whitespace-pre-line text-sm text-ink-900">{{ $body }}</p>
            <div class="mt-3 flex flex-wrap items-center gap-2">
                @include('dashboard.offers._disclosure_badge', ['text' => $email['body'] ?? ''])
                <x-ui.copy-button :text="($email['subject'] ?? '').' — '.$body" size="xs" label="Copy email" />
                @if (! $nurtureContact)
                    {{-- no send action: contact is gone --}}
                @elseif ($nurtureContact->isUnsubscribed())
                    <span class="text-xs text-ink-400">{{ $nurtureContact->name }} has unsubscribed — this step can't be sent.</span>
                @elseif (! $nurtureContact->email)
                    <span class="text-xs text-ink-400">No email address on file for {{ $nurtureContact->name }}.</span>
                @else
                    <form method="POST" action="{{ route('generations.nurture.send', $gen) }}" onsubmit="return confirm({{ \Illuminate\Support\Js::from('Send this email to '.$nurtureContact->name.' now?') }})">
                        @csrf
                        <input type="hidden" name="step" value="{{ $email['step'] ?? '' }}">
                        <x-ui.button variant="brand" size="sm" icon="paper-airplane">{{ $lastSend && $lastSend->status !== 'failed' ? 'Resend' : 'Send now' }}</x-ui.button>
                    </form>
                @endif
            </div>
        </li>
    @endforeach
</ol>
