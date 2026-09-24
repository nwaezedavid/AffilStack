@include('filament.pages._panel-styles')

<div class="afs-panel-detail">
    <div>
        <div class="afs-panel-detail-label">Tom's summary</div>
        <p class="afs-panel-detail-value">{{ $record->ai_summary }}</p>
    </div>
    <div>
        <div class="afs-panel-detail-label">Suggested fix</div>
        <p class="afs-panel-detail-value">{{ $record->ai_suggested_fix }}</p>
    </div>
    <div>
        <div class="afs-panel-detail-label">Evidence</div>
        <pre class="afs-panel-pre-snippet">{{ json_encode($record->evidence, JSON_PRETTY_PRINT) }}</pre>
    </div>
    @if (! $record->isFixable())
        <p class="afs-panel-warning-text">
            This one needs a person's judgment — there's no safe automatic fix Tom can schedule for it.
        </p>
    @endif
</div>
