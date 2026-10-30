@extends('layouts.app')

@section('title', 'Inbox & Replies')

@section('content')
    @php($method = $connection?->replyDetectionMethod())
    <x-ui.page-header title="Inbox" eyebrow="Replies from your prospects" icon="inbox-arrow-down" tone="emerald"
        subtitle="When a contact answers your campaign, it shows up here. Open the conversation and let AI draft a reply that moves them toward the sale.">
        <x-slot:actions>
            @if ($method)
                <form method="POST" action="{{ route('crm.inbox.check') }}">@csrf
                    <x-ui.button variant="light" icon="arrow-path">Check now</x-ui.button>
                </form>
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    <div class="mb-6 flex flex-wrap items-center gap-3 rounded-2xl border border-line bg-surface px-4 py-3 text-sm">
        @if ($method)
            <x-ui.status-pill status="verified" label="Reply detection on" />
            <span class="text-ink-600">
                {{ $method === 'gmail' ? 'Reading your Gmail inbox' : 'Checking '.$connection->imapCredential('username').' over IMAP' }} every 5 minutes
                @php($polled = $method === 'gmail' ? $connection->gmail_last_polled_at : $connection->imap_last_polled_at)
                @if ($polled) · last checked {{ $polled->diffForHumans() }} @endif
            </span>
        @else
            <x-ui.status-pill :status="$connection?->hasImap() ? 'failed' : 'not_connected'" :label="$connection?->hasImap() ? 'Reply detection failing' : 'Reply detection off'" />
            <span class="text-ink-600 flex-1">Add your mailbox's IMAP details so we can spot replies automatically.</span>
            <x-ui.button size="sm" variant="primary" :href="route('email-connections.index').'#reply-detection'" icon="cog-6-tooth">Set up</x-ui.button>
        @endif
    </div>

    @if ($conversations->isEmpty())
        <x-ui.empty-state icon="chat-bubble-left-right" title="No replies yet" :href="route('crm.campaigns.index')" action="View campaigns">
            Replies to your campaigns and follow-ups will appear here — newest and unread first. Most replies arrive within 1–3 days of sending.
        </x-ui.empty-state>
    @else
        <ul class="divide-y divide-line overflow-hidden rounded-2xl border border-line bg-surface shadow-[0_1px_2px_rgba(16,24,40,0.04)]" role="list">
            @foreach ($conversations as $reply)
                @php($contact = $reply->contact)
                @php($unread = $reply->unread_count > 0)
                <li>
                    <a href="{{ route('crm.inbox.show', $contact) }}" @class(['flex gap-4 px-4 py-4 sm:px-5 transition hover:bg-surface-muted/60 focus:outline-none focus-visible:bg-brand-50', 'bg-brand-50/40' => $unread])>
                        <span class="relative inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-emerald-500 to-teal-700 text-sm font-semibold text-white" aria-hidden="true">
                            {{ mb_strtoupper(mb_substr($contact->name ?: ($contact->company ?: $reply->from_email), 0, 1)) }}
                            @if ($unread)
                                <span class="absolute -right-0.5 -top-0.5 h-3 w-3 rounded-full bg-rose-500 ring-2 ring-white"></span>
                            @endif
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="flex items-baseline justify-between gap-3">
                                <span @class(['truncate text-sm', 'font-semibold text-navy-900' => $unread, 'font-medium text-ink-900' => ! $unread])>
                                    {{ $contact->name ?: $contact->company ?: $reply->from_email }}
                                    @if ($contact->company && $contact->company !== $contact->name)
                                        <span class="font-normal text-ink-400">· {{ $contact->company }}</span>
                                    @endif
                                </span>
                                <span class="shrink-0 text-xs text-ink-400">{{ $reply->received_at?->diffForHumans(short: true) }}</span>
                            </span>
                            <span class="mt-0.5 block truncate text-sm text-ink-600">{{ $reply->subject ?: '(no subject)' }} — {{ \Illuminate\Support\Str::limit($reply->body_clean ?: $reply->body_text, 140) }}</span>
                            <span class="mt-1.5 flex flex-wrap items-center gap-1.5">
                                @if ($unread)
                                    <span class="rounded-full bg-rose-500 px-2 py-0.5 text-[11px] font-semibold text-white">{{ $reply->unread_count }} new</span>
                                @endif
                                @if (in_array($reply->copilot_status, ['drafted', 'auto_queued'], true))
                                    <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-0.5 text-[11px] font-semibold text-emerald-700"><x-heroicon-m-sparkles class="h-3 w-3" /> {{ $reply->copilot_status === 'auto_queued' ? 'Autopilot sending' : 'Reply drafted' }} · {{ \App\Services\Agents\Customer\ReplyCopilot::INTENTS[$reply->copilot_intent] ?? 'Reply' }}</span>
                                @endif
                                @if ($reply->campaign)
                                    <span class="inline-flex items-center gap-1 rounded-full bg-violet-50 px-2 py-0.5 text-[11px] font-medium text-violet-700"><x-heroicon-m-megaphone class="h-3 w-3" /> {{ \Illuminate\Support\Str::limit($reply->campaign->name, 40) }}</span>
                                @endif
                                @if ($reply->campaign?->offer)
                                    <span class="inline-flex items-center gap-1 rounded-full bg-gold-300/25 px-2 py-0.5 text-[11px] font-medium text-gold-600"><x-heroicon-m-cube class="h-3 w-3" /> {{ $reply->campaign->offer->product_name }}</span>
                                @else
                                    @foreach ($contact->lists->take(1) as $list)
                                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-medium ring-1 ring-inset {{ $list->chipClasses() }}">{{ $list->name }}</span>
                                    @endforeach
                                @endif
                            </span>
                        </span>
                    </a>
                </li>
            @endforeach
        </ul>
    @endif
@endsection
