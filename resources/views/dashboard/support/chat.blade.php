@extends('layouts.app')

@section('title', 'AI support assistant')

@section('content')
    <a href="{{ route('support.index') }}" class="text-sm text-ink-400 hover:text-ink-900">&larr; Back to support</a>

    <div class="bg-surface border border-line rounded-lg mt-4 flex flex-col" style="height: 65vh;">
        <div class="border-b border-line px-4 py-3">
            <h1 class="text-sm font-semibold text-ink-900">AffilStack AI Assistant</h1>
            <p class="text-xs text-ink-400">Answers instantly from our knowledge base. Ask it to reach a human anytime.</p>
        </div>

        <div id="chat-log" class="flex-1 overflow-y-auto px-4 py-4 space-y-3">
            @forelse ($messages as $message)
                <div @class([
                    'max-w-lg rounded-lg px-3 py-2 text-sm whitespace-pre-line',
                    'ml-auto bg-navy-900 text-white' => $message->role === 'user',
                    'bg-surface-muted text-ink-900' => $message->role === 'assistant',
                ])>{{ $message->content }}</div>
            @empty
                <div class="bg-surface-muted text-ink-900 rounded-lg px-3 py-2 text-sm max-w-lg">
                    Hi! I'm the AffilStack assistant. Ask me anything about offers, LinkedIn content, billing, or your account — and I'll connect you to a human if I can't help.
                </div>
            @endforelse
        </div>

        <form id="chat-form" class="border-t border-line p-3 flex gap-2">
            @csrf
            <input type="text" id="chat-input" autocomplete="off" maxlength="2000" placeholder="Type your question…"
                class="flex-1 rounded-md border-line focus:border-brand-500 focus:ring-brand-500 text-sm">
            <button type="submit" class="rounded-md bg-navy-900 text-white text-sm font-medium px-4 py-2 hover:bg-navy-800 transition">Send</button>
        </form>
    </div>

    <form id="escalate-form" method="POST" action="{{ route('support.chat.escalate') }}" class="mt-3 hidden" data-escalate>
        @csrf
        <button type="submit" class="text-sm text-brand-600 underline">This didn't help — open a ticket with a human, attaching this conversation</button>
    </form>

    @push('scripts')
        <script>
            (function () {
                const log = document.getElementById('chat-log');
                const form = document.getElementById('chat-form');
                const input = document.getElementById('chat-input');
                const escalate = document.querySelector('[data-escalate]');
                const csrf = document.querySelector('input[name="_token"]').value;

                function bubble(text, role) {
                    const div = document.createElement('div');
                    div.className = role === 'user'
                        ? 'ml-auto bg-navy-900 text-white max-w-lg rounded-lg px-3 py-2 text-sm whitespace-pre-line'
                        : 'bg-surface-muted text-ink-900 max-w-lg rounded-lg px-3 py-2 text-sm whitespace-pre-line';
                    div.textContent = text;
                    log.appendChild(div);
                    log.scrollTop = log.scrollHeight;
                    return div;
                }

                form.addEventListener('submit', async function (e) {
                    e.preventDefault();
                    const message = input.value.trim();
                    if (!message) return;

                    bubble(message, 'user');
                    input.value = '';
                    input.disabled = true;
                    const thinking = bubble('Thinking…', 'assistant');

                    try {
                        const response = await fetch('{{ route('support.chat.message') }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': csrf,
                                Accept: 'application/json',
                            },
                            body: JSON.stringify({ message }),
                        });

                        if (!response.ok) {
                            thinking.textContent = "Sorry, something went wrong. Please try again.";
                        } else {
                            const data = await response.json();
                            thinking.textContent = data.reply;
                            if (data.should_escalate) {
                                escalate.classList.remove('hidden');
                            }
                        }
                    } catch (err) {
                        thinking.textContent = "Sorry, something went wrong. Please try again.";
                    } finally {
                        input.disabled = false;
                        input.focus();
                    }
                });
            })();
        </script>
    @endpush
@endsection
