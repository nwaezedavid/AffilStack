<div class="space-y-4 text-sm">
    <div>
        <div class="font-semibold text-gray-700 dark:text-gray-200 mb-1">Tom's summary</div>
        <p class="text-gray-600 dark:text-gray-400">{{ $record->ai_summary }}</p>
    </div>
    <div>
        <div class="font-semibold text-gray-700 dark:text-gray-200 mb-1">Suggested fix</div>
        <p class="text-gray-600 dark:text-gray-400">{{ $record->ai_suggested_fix }}</p>
    </div>
    <div>
        <div class="font-semibold text-gray-700 dark:text-gray-200 mb-1">Evidence</div>
        <pre class="text-xs bg-gray-50 dark:bg-gray-800 rounded p-2 overflow-x-auto">{{ json_encode($record->evidence, JSON_PRETTY_PRINT) }}</pre>
    </div>
    @if (! $record->isFixable())
        <p class="text-xs text-amber-600 dark:text-amber-400">
            This one needs a person's judgment — there's no safe automatic fix Tom can schedule for it.
        </p>
    @endif
</div>
