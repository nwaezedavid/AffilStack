<div class="space-y-4 text-sm">
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
