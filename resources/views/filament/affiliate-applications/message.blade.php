@include('filament.pages._panel-styles')

<div class="afs-panel-detail">
    @if ($record->website_url)
        <div>
            <div class="afs-panel-detail-label">Website / social profile</div>
            <a href="{{ $record->website_url }}" target="_blank" rel="noopener" class="afs-panel-link-external">{{ $record->website_url }}</a>
        </div>
    @endif
    <div class="afs-panel-detail-grid-2">
        <div>
            <div class="afs-panel-detail-label">Audience size</div>
            <p class="afs-panel-detail-value">{{ $record->audience_size ? (\App\Models\AffiliateApplication::audienceSizeOptions()[$record->audience_size] ?? $record->audience_size) : '—' }}</p>
        </div>
        <div>
            <div class="afs-panel-detail-label">Experience level</div>
            <p class="afs-panel-detail-value">{{ $record->experience_level ? (\App\Models\AffiliateApplication::experienceLevelOptions()[$record->experience_level] ?? $record->experience_level) : '—' }}</p>
        </div>
    </div>
    <div>
        <div class="afs-panel-detail-label">How they plan to promote</div>
        <p class="afs-panel-detail-value afs-panel-pre-line">{{ $record->promotion_channels }}</p>
    </div>
    @if ($record->message)
        <div>
            <div class="afs-panel-detail-label">Additional message</div>
            <p class="afs-panel-detail-value afs-panel-pre-line">{{ $record->message }}</p>
        </div>
    @endif
    @if ($record->rejection_reason)
        <div>
            <div class="afs-panel-detail-label">Rejection reason</div>
            <p class="afs-panel-detail-value afs-panel-pre-line">{{ $record->rejection_reason }}</p>
        </div>
    @endif
</div>
