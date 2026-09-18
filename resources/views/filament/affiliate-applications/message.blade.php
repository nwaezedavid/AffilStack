<div class="space-y-4 text-sm">
    @if ($record->website_url)
        <div>
            <div class="font-semibold text-gray-700 dark:text-gray-200 mb-1">Website / social profile</div>
            <a href="{{ $record->website_url }}" target="_blank" rel="noopener" class="text-primary-600 underline break-all">{{ $record->website_url }}</a>
        </div>
    @endif
    <div class="grid grid-cols-2 gap-4">
        <div>
            <div class="font-semibold text-gray-700 dark:text-gray-200 mb-1">Audience size</div>
            <p class="text-gray-600 dark:text-gray-400">{{ $record->audience_size ? (\App\Models\AffiliateApplication::audienceSizeOptions()[$record->audience_size] ?? $record->audience_size) : '—' }}</p>
        </div>
        <div>
            <div class="font-semibold text-gray-700 dark:text-gray-200 mb-1">Experience level</div>
            <p class="text-gray-600 dark:text-gray-400">{{ $record->experience_level ? (\App\Models\AffiliateApplication::experienceLevelOptions()[$record->experience_level] ?? $record->experience_level) : '—' }}</p>
        </div>
    </div>
    <div>
        <div class="font-semibold text-gray-700 dark:text-gray-200 mb-1">How they plan to promote</div>
        <p class="text-gray-600 dark:text-gray-400 whitespace-pre-line">{{ $record->promotion_channels }}</p>
    </div>
    @if ($record->message)
        <div>
            <div class="font-semibold text-gray-700 dark:text-gray-200 mb-1">Additional message</div>
            <p class="text-gray-600 dark:text-gray-400 whitespace-pre-line">{{ $record->message }}</p>
        </div>
    @endif
    @if ($record->rejection_reason)
        <div>
            <div class="font-semibold text-gray-700 dark:text-gray-200 mb-1">Rejection reason</div>
            <p class="text-gray-600 dark:text-gray-400 whitespace-pre-line">{{ $record->rejection_reason }}</p>
        </div>
    @endif
</div>
