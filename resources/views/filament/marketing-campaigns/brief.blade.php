@include('filament.pages._panel-styles')

<div class="afs-panel-detail">
    @if ($record->image_urls)
        <div class="afs-panel-thumbs">
            @foreach ($record->image_urls as $url)
                <img src="{{ $url }}" alt="Promotional creative" class="afs-panel-thumb" />
            @endforeach
        </div>
    @endif

    <div>
        <div class="afs-panel-detail-label">Ad copy variants</div>
        <div class="afs-panel-detail" style="gap: 0.5rem;">
            @foreach ((array) data_get($record->brief, 'ad_copy_variants', []) as $variant)
                <div class="afs-panel-snippet">
                    <p style="font-weight: 500; margin: 0;">{{ data_get($variant, 'headline') }}</p>
                    <p class="afs-panel-detail-value">{{ data_get($variant, 'primary_text') }}</p>
                    <p class="afs-panel-detail-hint">{{ data_get($variant, 'description') }}</p>
                </div>
            @endforeach
        </div>
    </div>

    <div class="afs-panel-detail-grid-2">
        <div>
            <div class="afs-panel-detail-label">Targeting</div>
            <p class="afs-panel-detail-value">Ages: {{ data_get($record->brief, 'targeting.age_range') }}</p>
            <p class="afs-panel-detail-value">Interests: {{ implode(', ', (array) data_get($record->brief, 'targeting.interests', [])) }}</p>
            <p class="afs-panel-detail-value">Geos: {{ implode(', ', (array) data_get($record->brief, 'targeting.geos', [])) }}</p>
        </div>
        <div>
            <div class="afs-panel-detail-label">Budget</div>
            <p class="afs-panel-detail-value">
                {{ data_get($record->brief, 'budget_suggestion.currency') }}
                {{ data_get($record->brief, 'budget_suggestion.daily_min') }}–{{ data_get($record->brief, 'budget_suggestion.daily_max') }} / day
            </p>
        </div>
    </div>

    @if ($record->launch_transcript)
        <div>
            <div class="afs-panel-detail-label">Claude's launch report</div>
            <p class="afs-panel-detail-value afs-panel-pre-line">{{ $record->launch_transcript }}</p>
        </div>
    @endif

    @if ($record->last_optimization_summary)
        <div>
            <div class="afs-panel-detail-label">Latest optimization pass</div>
            <p class="afs-panel-detail-value">{{ $record->last_optimization_summary }}</p>
            <p class="afs-panel-detail-hint">{{ $record->last_optimized_at?->diffForHumans() }}</p>
        </div>
    @endif

    @if ($record->failure_reason)
        <p class="afs-panel-danger-text">{{ $record->failure_reason }}</p>
    @endif
</div>
