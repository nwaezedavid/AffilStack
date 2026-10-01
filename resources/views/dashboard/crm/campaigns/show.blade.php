@extends('layouts.app')

@section('title', $campaign->name)

@section('content')
    @php
        $total = (int) $counts->sum();
        $sent = (int) (($counts['sent'] ?? 0) + ($counts['replied'] ?? 0));
        $replied = (int) ($counts['replied'] ?? 0);
        $queued = (int) ($counts['queued'] ?? 0);
        $pct = fn ($n) => $sent > 0 ? round($n / $sent * 100) : 0;
        $pillStyles = [
            'queued' => 'bg-surface-muted text-ink-600 ring-line',
            'sent' => 'bg-brand-50 text-brand-700 ring-brand-600/20',
            'opened' => 'bg-violet-50 text-violet-700 ring-violet-600/20',
            'clicked' => 'bg-amber-50 text-amber-700 ring-amber-600/20',
            'replied' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
            'failed' => 'bg-red-50 text-red-700 ring-red-600/20',
            'skipped' => 'bg-surface-muted text-ink-400 ring-line',
            'unsubscribed' => 'bg-rose-50 text-rose-700 ring-rose-600/20',
        ];
    @endphp

    <nav class="mb-3 text-xs text-ink-600" aria-label="Breadcrumb">
        <a href="{{ route('crm.campaigns.index') }}" class="hover:text-brand-600">Campaigns</a>
        <span class="mx-1 text-ink-400">/</span>
        <a href="{{ route('crm.lists.show', $campaign->list) }}" class="hover:text-brand-600">{{ $campaign->list->name }}</a>
    </nav>

    <x-ui.page-header :title="$campaign->name" eyebrow="Campaign history" icon="megaphone" :tone="$campaign->isPaused() ? 'rose' : 'emerald'"
        :subtitle="'To '.$campaign->list->name.($campaign->offer ? ' · promoting '.$campaign->offer->product_name : '').' · approved '.$campaign->approved_at?->format('M j, Y')">
        <x-slot:actions>
            @if ($campaign->isPaused())
                <form method="POST" action="{{ route('crm.campaigns.resume', $campaign) }}">@csrf
                    <x-ui.button variant="gold" icon="play">Resume</x-ui.button>
                </form>
            @else
                <form method="POST" action="{{ route('crm.campaigns.pause', $campaign) }}">@csrf
                    <x-ui.button variant="light" icon="pause">Pause</x-ui.button>
                </form>
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    @if ($campaign->isPaused())
        <div class="mb-5 flex flex-wrap items-start gap-3 rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800">
            <x-heroicon-o-pause-circle class="h-5 w-5 shrink-0" />
            <div class="flex-1">
                @if ($campaign->paused_reason === 'connection')
                    <strong>Paused because your email connection stopped working.</strong> Nothing was sent from a broken sender. Fix it, then click Resume — queued contacts go out automatically.
                    <a href="{{ route('email-connections.index') }}" class="ml-1 font-medium underline">Fix email sending</a>
                @else
                    <strong>Paused.</strong> No emails or follow-ups go out until you resume.
                @endif
            </div>
        </div>
    @endif

    <div class="grid grid-cols-2 lg:grid-cols-5 gap-3 mb-6">
        <x-ui.stat label="Sent" :value="number_format($sent)" icon="paper-airplane" :hint="$queued > 0 ? number_format($queued).' still queued' : 'All delivered to the queue'" />
        <x-ui.stat label="Opened" :value="$pct($opened).'%'" icon="eye" tone="violet" :hint="number_format($opened).' '.\Illuminate\Support\Str::plural('contact', $opened)" />
        <x-ui.stat label="Clicked" :value="$pct($clicked).'%'" icon="cursor-arrow-rays" tone="gold" :hint="number_format($clicked).' '.\Illuminate\Support\Str::plural('contact', $clicked)" />
        <x-ui.stat label="Replied" :value="$pct($replied).'%'" icon="chat-bubble-left-right" tone="emerald" :hint="number_format($replied).' '.\Illuminate\Support\Str::plural('contact', $replied)" />
        <x-ui.stat label="Skipped / failed" :value="number_format(($counts['skipped'] ?? 0) + ($counts['failed'] ?? 0) + ($counts['unsubscribed'] ?? 0))" icon="no-symbol" tone="rose" hint="No email, unsubscribed or error" />
    </div>

    @if ($replied > 0)
        <x-ui.next-step class="mb-6" :href="route('crm.inbox')" action="Open Inbox">
            {{ $replied }} contact{{ $replied === 1 ? '' : 's' }} replied. Let AI draft your follow-up and close the sale.
        </x-ui.next-step>
    @endif

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2 mb-6">
        <div @class(['rounded-2xl border p-5', 'border-gold-300/60 bg-gradient-to-br from-gold-300/15 to-white' => $newContacts > 0, 'border-line bg-surface' => $newContacts === 0])>
            <div class="flex items-start gap-3">
                <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-gold-400 text-navy-950"><x-heroicon-o-user-plus class="h-5 w-5" /></span>
                <div class="flex-1">
                    <h3 class="font-display font-semibold text-navy-900">{{ $newContacts > 0 ? number_format($newContacts).' new contact'.($newContacts === 1 ? '' : 's').' on this list' : 'Everyone on the list has it' }}</h3>
                    <p class="mt-0.5 text-sm text-ink-600">{{ $newContacts > 0 ? 'They joined after this campaign went out. Send them the same email with one click.' : 'Contacts you add to '.$campaign->list->name.' later will show up here.' }}</p>
                    @if ($newContacts > 0)
                        <form method="POST" action="{{ route('crm.campaigns.send-new', $campaign) }}" class="mt-3">
                            @csrf
                            <x-ui.button variant="primary" icon="paper-airplane">Send to {{ number_format($newContacts) }} new contact{{ $newContacts === 1 ? '' : 's' }}</x-ui.button>
                        </form>
                    @endif
                </div>
            </div>
        </div>

        <div class="rounded-2xl border border-line bg-surface p-5">
            <div class="flex items-start gap-3">
                <span @class(['inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl', 'bg-emerald-500 text-white' => $campaign->autopilot, 'bg-surface-muted text-ink-400' => ! $campaign->autopilot])><x-heroicon-o-bolt class="h-5 w-5" /></span>
                <div class="flex-1">
                    <div class="flex items-center justify-between gap-3">
                        <h3 class="font-display font-semibold text-navy-900">Autopilot is {{ $campaign->autopilot ? 'on' : 'off' }}</h3>
                        <form method="POST" action="{{ route('crm.campaigns.autopilot', $campaign) }}">
                            @csrf @method('PATCH')
                            <input type="hidden" name="autopilot" value="{{ $campaign->autopilot ? 0 : 1 }}">
                            <button type="submit" role="switch" aria-checked="{{ $campaign->autopilot ? 'true' : 'false' }}" aria-label="Toggle autopilot"
                                @class(['relative inline-flex h-6 w-11 shrink-0 items-center rounded-full transition focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-2', 'bg-emerald-500' => $campaign->autopilot, 'bg-ink-400/40' => ! $campaign->autopilot])>
                                <span @class(['inline-block h-5 w-5 rounded-full bg-white shadow transition', 'translate-x-5' => $campaign->autopilot, 'translate-x-0.5' => ! $campaign->autopilot])></span>
                            </button>
                        </form>
                    </div>
                    <p class="mt-0.5 text-sm text-ink-600">{{ $campaign->autopilot ? 'Every contact added to this list — from any source — gets this email automatically (within your daily limit).' : 'Turn it on and new contacts on this list are emailed automatically, no clicking needed.' }}</p>
                    <p class="mt-2 text-xs text-ink-400">{{ number_format($remainingToday) }} of {{ number_format($dailyCap) }} sends left today{{ $connection ? ' from '.$connection->connected_email : '' }}.</p>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1fr)_22rem]">
        <x-ui.card title="Recipients" icon="users" :description="number_format($total).' contacts · most recent activity first'" padding="p-0">
            @if ($recipients->isEmpty())
                <p class="p-6 text-sm text-ink-600">No recipients yet.</p>
            @else
                <div class="relative overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="text-left text-[11px] font-mono uppercase tracking-[0.12em] text-ink-400">
                            <tr class="border-b border-line">
                                <th class="px-5 py-3 font-medium">Contact</th>
                                <th class="px-3 py-3 font-medium">Status</th>
                                <th class="px-3 py-3 font-medium">Emails</th>
                                <th class="px-3 py-3 font-medium">Last activity</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            @foreach ($recipients as $recipient)
                                @php $display = $recipient->displayStatus(); @endphp
                                <tr class="align-top">
                                    <td class="px-5 py-3">
                                        <div class="font-medium text-ink-900">{{ $recipient->contact?->name ?: $recipient->contact?->company ?: 'Deleted contact' }}</div>
                                        <div class="text-xs text-ink-600">{{ $recipient->contact?->email ?: 'no email' }}</div>
                                    </td>
                                    <td class="px-3 py-3">
                                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-medium capitalize ring-1 ring-inset {{ $pillStyles[$display] ?? $pillStyles['sent'] }}">{{ $display }}</span>
                                        @if ($recipient->source === 'autopilot')
                                            <span class="ml-1 inline-flex items-center text-[11px] text-ink-400" title="Sent by autopilot"><x-heroicon-m-bolt class="h-3 w-3" /></span>
                                        @endif
                                        @if ($recipient->error_message && in_array($display, ['failed', 'skipped'], true))
                                            <div class="mt-1 max-w-[16rem] text-[11px] text-ink-400">{{ \Illuminate\Support\Str::limit($recipient->error_message, 90) }}</div>
                                        @endif
                                        @if ($display === 'replied' && $recipient->contact)
                                            <a href="{{ route('crm.inbox.show', $recipient->contact) }}" class="mt-1 block text-[11px] font-medium text-emerald-700 hover:underline">Open conversation &rarr;</a>
                                        @endif
                                    </td>
                                    <td class="px-3 py-3 text-xs text-ink-600 whitespace-nowrap">
                                        {{ $recipient->steps_sent }} of {{ 1 + count($campaign->followUps()) }}
                                        @if ($recipient->next_step_due_at && ! $campaign->isPaused())
                                            <div class="text-[11px] text-ink-400">next {{ $recipient->next_step_due_at->diffForHumans() }}</div>
                                        @endif
                                    </td>
                                    <td class="px-3 py-3 text-xs text-ink-600 whitespace-nowrap">{{ ($recipient->replied_at ?? $recipient->last_sent_at ?? $recipient->updated_at)?->diffForHumans() }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="px-5 py-3">{{ $recipients->links() }}</div>
            @endif
        </x-ui.card>

        <aside class="space-y-4">
            <x-ui.card title="The email" icon="envelope" padding="p-4">
                <p class="font-display font-semibold text-sm text-navy-900">{{ $campaign->subject }}</p>
                <div class="mt-2 max-h-80 overflow-y-auto whitespace-pre-line break-words rounded-lg bg-surface-muted/60 p-3 font-mono text-[12px] leading-relaxed text-ink-900">{{ $campaign->body }}</div>
                @foreach ($campaign->followUps() as $i => $step)
                    <details class="mt-2 rounded-lg border border-line px-3 py-2">
                        <summary class="cursor-pointer text-xs font-medium text-ink-600">Follow-up {{ $i + 1 }} · after {{ $step['delay_days'] }} day{{ $step['delay_days'] === 1 ? '' : 's' }}</summary>
                        <div class="mt-2 whitespace-pre-line break-words font-mono text-[12px] text-ink-900">{{ $step['body'] }}</div>
                    </details>
                @endforeach
            </x-ui.card>
        </aside>
    </div>
@endsection
