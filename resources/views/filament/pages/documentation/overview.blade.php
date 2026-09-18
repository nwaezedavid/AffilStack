<x-filament-panels::page>
    @include('filament.pages.documentation._styles')

    <div class="afs-doc-hero">
        <div class="afs-doc-hero-icon">
            <x-filament::icon icon="heroicon-o-book-open" />
        </div>
        <div>
            <p>{{ $this->intro() }}</p>
        </div>
    </div>

    <h2 class="afs-doc-heading">Browse by topic</h2>
    <div class="afs-doc-grid afs-doc-grid--topics">
        @foreach ($this->topics() as $topic)
            <a href="{{ $topic['url'] }}" class="afs-doc-topic">
                <div class="afs-doc-icon-badge">
                    <x-filament::icon :icon="$topic['icon']" />
                </div>
                <div>
                    <div class="afs-doc-topic-title">{{ $topic['title'] }}</div>
                    <p class="afs-doc-topic-desc">{{ $topic['description'] }}</p>
                </div>
            </a>
        @endforeach
    </div>

    <h2 class="afs-doc-heading">Getting started</h2>
    <div class="afs-doc-grid">
        @foreach ($this->sections() as $section)
            <div class="afs-doc-card">
                <div class="afs-doc-card-head">
                    <div class="afs-doc-icon-badge">
                        <x-filament::icon :icon="$section['icon']" />
                    </div>

                    <div class="afs-doc-card-head-main">
                        <h2>{{ $section['title'] }}</h2>

                        @if ($section['url'] ?? null)
                            <a href="{{ $section['url'] }}" class="afs-doc-card-link">
                                Open settings
                            </a>
                        @endif
                    </div>
                </div>

                <div class="afs-doc-card-body">
                    @foreach ($section['body'] as $paragraph)
                        <p>{{ $paragraph }}</p>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>
</x-filament-panels::page>
