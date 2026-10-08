@props(['id', 'title', 'icon' => 'heroicon-o-bookmark'])
<section class="afs-g-section" id="{{ $id }}" data-toc-id="{{ $id }}" data-toc="{{ $title }}">
    <h2><x-filament::icon :icon="$icon" /> {{ $title }}</h2>
    {{ $slot }}
</section>
