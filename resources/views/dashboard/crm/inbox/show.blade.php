@extends('layouts.app')

@section('title', 'Conversation')

@section('content')
    @php($name = $contact->name ?: $contact->company ?: $latest->from_email)
    <nav class="mb-3 text-xs text-ink-600" aria-label="Breadcrumb">
        <a href="{{ route('crm.inbox') }}" class="hover:text-brand-600">Inbox</a>
        <span class="mx-1 text-ink-400">/</span>
        <span class="text-ink-900">{{ $name }}</span>
    </nav>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1fr)_20rem]">
        <div class="min-w-0 space-y-5">
            {{-- Thread --}}
            <section class="rounded-2xl border border-line bg-surface shadow-[0_1px_2px_rgba(16,24,40,0.04)]" aria-label="Conversation">
                <header class="flex flex-wrap items-center justify-between gap-3 border-b border-line px-5 py-4">
                    <div class="min-w-0">
                        <h2 class="truncate font-display text-lg font-semibold text-navy-900">{{ $latest->subject ?: '(no subject)' }}</h2>
                        <p class="text-xs text-ink-600">{{ $thread->count() }} message{{ $thread->count() === 1 ? '' : 's' }} with {{ $name }}</p>
                    </div>
                    @if ($campaign)
                        <a href="{{ route('crm.campaigns.show', $campaign) }}" class="inline-flex items-center gap-1 rounded-full bg-violet-50 px-2.5 py-1 text-xs font-medium text-violet-700 hover:bg-violet-100"><x-heroicon-m-megaphone class="h-3.5 w-3.5" /> {{ \Illuminate\Support\Str::limit($campaign->name, 40) }}</a>
                    @endif
                </header>
                <ol class="space-y-4 p-4 sm:p-5">
                    @foreach ($thread as $item)
                        @php($in = $item['direction'] === 'in')
                        <li @class(['flex', 'justify-start' => $in, 'justify-end' => ! $in])>
                            <article @class([
                                'max-w-[min(42rem,92%)] rounded-2xl px-4 py-3 shadow-sm',
                                'bg-white ring-1 ring-line rounded-tl-sm' => $in,
                                'bg-navy-900 text-white rounded-tr-sm' => ! $in,
                            ])>
                                <header @class(['mb-1.5 flex flex-wrap items-baseline gap-x-2 text-xs', 'text-ink-400' => $in, 'text-white/60' => ! $in])>
                                    <span @class(['font-semibold', 'text-navy-900' => $in, 'text-gold-300' => ! $in])>{{ $in ? $name : 'You' }}</span>
                                    <time datetime="{{ $item['at']?->toIso8601String() }}">{{ $item['at']?->format('M j, g:i A') }}</time>
                                    @if (! $in && $item['model']->kind)
                                        <span>· {{ ['campaign' => 'Campaign email', 'follow_up' => 'Follow-up', 'reply' => 'Your reply', 'nurture' => 'Nurture email'][$item['model']->kind] ?? '' }}</span>
                                    @endif
                                    @if (! $in && $item['model']->opened_at)
                                        <span class="inline-flex items-center gap-0.5"><x-heroicon-m-eye class="h-3 w-3" /> opened</span>
                                    @endif
                                </header>
                                @if ($item['subject'] && $loop->first)
                                    <p @class(['mb-1 text-sm font-semibold', 'text-navy-900' => $in, 'text-white' => ! $in])>{{ $item['subject'] }}</p>
                                @endif
                                <div @class(['whitespace-pre-line break-words text-sm leading-relaxed', 'text-ink-900' => $in, 'text-white/90' => ! $in])>{{ $item['body'] }}</div>
                            </article>
                        </li>
                    @endforeach
                </ol>
            </section>

            {{-- Reply composer --}}
            <section class="rounded-2xl border border-line bg-surface shadow-[0_1px_2px_rgba(16,24,40,0.04)]" aria-labelledby="reply-heading">
                <header class="flex flex-wrap items-center justify-between gap-3 border-b border-line px-5 py-4">
                    <h3 id="reply-heading" class="flex items-center gap-2 font-display font-semibold text-navy-900"><x-heroicon-o-arrow-uturn-left class="h-5 w-5 text-brand-600" /> Reply to {{ $name }}</h3>
                    <span class="text-xs text-ink-400">{{ number_format($balance) }} credits left</span>
                </header>

                @if ($copilot ?? null)
                    <div class="flex flex-col gap-3 border-b border-line bg-emerald-50/70 px-5 py-4 sm:flex-row sm:items-start sm:justify-between" data-copilot>
                        <div class="flex items-start gap-3">
                            <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-emerald-600 text-white"><x-heroicon-o-chat-bubble-left-right class="h-5 w-5" /></span>
                            <div class="text-sm">
                                <p class="font-semibold text-navy-900">
                                    Reply Copilot
                                    <span class="ml-1 inline-flex items-center rounded-full bg-white px-2 py-0.5 text-[11px] font-semibold text-emerald-700 ring-1 ring-emerald-200">{{ \App\Services\Agents\Customer\ReplyCopilot::INTENTS[$copilot->copilot_intent] ?? 'Reply' }}</span>
                                </p>
                                @if ($copilot->copilot_summary)
                                    <p class="mt-0.5 text-ink-700">{{ $copilot->copilot_summary }}</p>
                                @endif
                                <p class="mt-1 text-xs text-ink-600">
                                    @if ($copilot->copilot_status === 'auto_queued')
                                        Autopilot will send the reply below at {{ $copilot->copilot_send_at?->timezone(auth()->user()->timezone ?: config('app.timezone'))->format('g:i A') }}. Edit and send it yourself, or stop it.
                                    @else
                                        The best reply is drafted below. Check it, then press Send reply.
                                    @endif
                                </p>
                            </div>
                        </div>
                        <form method="POST" action="{{ route('crm.inbox.copilot.dismiss', $copilot) }}" class="shrink-0">
                            @csrf
                            <button class="rounded-full border border-emerald-300 bg-white px-3 py-1.5 text-xs font-semibold text-emerald-800 hover:bg-emerald-100">{{ $copilot->copilot_status === 'auto_queued' ? 'Stop autopilot' : 'Dismiss draft' }}</button>
                        </form>
                    </div>
                @endif

                <form method="POST" action="{{ route('crm.inbox.draft', $latest) }}" class="border-b border-line bg-gradient-to-r from-violet-50 via-white to-brand-50 px-5 py-4">
                    @csrf
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
                        <label class="block flex-1">
                            <span class="block text-xs font-medium text-ink-600 mb-1">Guidance for the AI <span class="text-ink-400">(optional)</span></span>
                            <input name="instructions" maxlength="500" placeholder="e.g. Offer a 15-minute call on Thursday; mention the free trial" class="w-full rounded-lg border border-line bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
                        </label>
                        <button type="submit" @disabled($balance < $cost) class="inline-flex items-center justify-center gap-2 rounded-full bg-gradient-to-r from-violet-600 to-brand-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-2">
                            <x-heroicon-m-sparkles class="h-4 w-4" /> Draft reply with AI · {{ $cost }} credits
                        </button>
                    </div>
                    <p class="mt-2 text-xs text-ink-600">The AI reads the whole conversation{{ $offer ? ', your research on '.$offer->product_name : '' }}{{ $campaign ? ' and your original campaign' : '' }}, answers their question or objection, and proposes a next step.</p>
                </form>

                <form method="POST" action="{{ route('crm.inbox.send', $latest) }}" class="space-y-3 px-5 py-4">
                    @csrf
                    <label class="block">
                        <span class="block text-xs font-medium text-ink-600 mb-1">Subject</span>
                        <input name="subject" value="{{ old('subject', $replySubject) }}" required maxlength="200" class="w-full rounded-lg border border-line px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
                    </label>
                    <label class="block">
                        <span class="block text-xs font-medium text-ink-600 mb-1">Your reply</span>
                        <textarea name="body" rows="10" required maxlength="10000" placeholder="Write your reply, or let AI draft it above." class="w-full rounded-lg border border-line px-3 py-2.5 text-sm leading-relaxed focus:outline-none focus:ring-2 focus:ring-brand-500 {{ $draft ? 'ring-2 ring-violet-400/50' : '' }}">{{ old('body', $draft) }}</textarea>
                    </label>
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <p class="text-xs text-ink-400">
                            @if ($offer)
                                <code class="rounded bg-surface-muted px-1">{affiliate_link}</code> becomes your tracked link.
                            @endif
                            Sent from {{ $connection?->connected_email ?? 'your connected email' }}, in the same thread.
                        </p>
                        <x-ui.button variant="brand" icon="paper-airplane" :disabled="! $connection?->isVerified() || $contact->isUnsubscribed()">Send reply</x-ui.button>
                    </div>
                    @if (! $connection?->isVerified())
                        <p class="text-xs text-amber-700">Verify your email on the <a href="{{ route('email-connections.index') }}" class="underline">Email Sending</a> page to reply from here.</p>
                    @endif
                </form>
            </section>
        </div>

        <aside class="space-y-4">
            <x-ui.card title="Contact" icon="user">
                <dl class="space-y-2 text-sm">
                    <div><dt class="text-xs text-ink-400">Name</dt><dd class="text-ink-900">{{ $contact->name ?: '—' }}</dd></div>
                    @if ($contact->company)<div><dt class="text-xs text-ink-400">Company</dt><dd class="text-ink-900">{{ $contact->company }}</dd></div>@endif
                    <div><dt class="text-xs text-ink-400">Email</dt><dd class="break-all text-ink-900">{{ $contact->email ?: $latest->from_email }}</dd></div>
                    @if ($contact->phone)<div><dt class="text-xs text-ink-400">Phone</dt><dd class="text-ink-900">{{ $contact->phone }}</dd></div>@endif
                    @if ($contact->website)<div><dt class="text-xs text-ink-400">Website</dt><dd class="truncate"><a href="{{ $contact->website }}" target="_blank" rel="noopener" class="text-brand-600 hover:underline">{{ preg_replace('#^https?://#', '', $contact->website) }}</a></dd></div>@endif
                </dl>
                <form method="POST" action="{{ route('crm.update', $contact) }}" class="mt-4">
                    @csrf @method('PATCH')
                    <label class="block">
                        <span class="block text-xs font-medium text-ink-600 mb-1">Pipeline stage</span>
                        <select name="status" onchange="this.form.submit()" class="w-full rounded-lg border border-line px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
                            @foreach (['new', 'contacted', 'qualified', 'customer', 'unqualified'] as $status)
                                <option value="{{ $status }}" @selected($contact->status === $status)>{{ ucfirst($status) }}</option>
                            @endforeach
                        </select>
                    </label>
                </form>
                @if ($contact->lists->isNotEmpty())
                    <div class="mt-4 flex flex-wrap gap-1">
                        @foreach ($contact->lists as $list)
                            <a href="{{ route('crm.lists.show', $list) }}" class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-medium ring-1 ring-inset {{ $list->chipClasses() }}">{{ $list->name }}</a>
                        @endforeach
                    </div>
                @endif
                @if ($contact->isUnsubscribed())
                    <p class="mt-3 rounded-lg bg-rose-50 px-3 py-2 text-xs text-rose-700">This contact unsubscribed — you can't email them again.</p>
                @endif
            </x-ui.card>

            <x-ui.next-step title="Closing tip" :href="$contact->status !== 'customer' ? null : null">
                Reply within a few hours, answer exactly what they asked, and suggest one easy next step. When they buy, set their stage to <strong>Customer</strong>.
            </x-ui.next-step>
        </aside>
    </div>
@endsection
