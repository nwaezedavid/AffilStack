@props(['title' => null])
<li>
    @if ($title)
        <span class="afs-g-step-title">{{ $title }}</span>
    @endif
    <div class="afs-g-step-body">{{ $slot }}</div>
</li>
