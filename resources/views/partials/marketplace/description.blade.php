{{-- An add-on's description: developer-written plain text, shown as paragraphs and simple bullet lists (never HTML). Needs $text. --}}
<div class="space-y-3 text-sm leading-relaxed text-ink-600">
    @foreach (preg_split('/\R{2,}/', trim($text)) as $block)
        @php($lines = preg_split('/\R/', $block))
        @if (collect($lines)->every(fn ($line) => preg_match('/^\s*[-*•]\s+/', $line)))
            <ul class="list-disc space-y-1 pl-5">
                @foreach ($lines as $line)
                    <li>{{ preg_replace('/^\s*[-*•]\s+/', '', $line) }}</li>
                @endforeach
            </ul>
        @else
            <p class="whitespace-pre-line">{{ $block }}</p>
        @endif
    @endforeach
</div>
