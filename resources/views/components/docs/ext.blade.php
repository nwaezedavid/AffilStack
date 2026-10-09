@props(['href'])
<a href="{{ $href }}" target="_blank" rel="noopener noreferrer">{{ $slot }}<span style="font-size:0.8em" aria-hidden="true"> ↗</span><span class="sr-only"> (opens in a new tab)</span></a>
