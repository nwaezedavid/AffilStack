<div class="space-y-4 text-sm">
    @if ($record->image_urls)
        <div class="flex gap-3 flex-wrap">
            @foreach ($record->image_urls as $url)
                <img src="{{ $url }}" alt="Promotional creative" class="w-40 h-40 object-cover rounded-lg border border-gray-200 dark:border-gray-700" />
            @endforeach
        </div>
    @endif

    <div>
        <div class="font-semibold text-gray-700 dark:text-gray-200 mb-1">Ad copy variants</div>
        <div class="space-y-2">
            @foreach ((array) data_get($record->brief, 'ad_copy_variants', []) as $variant)
                <div class="bg-gray-50 dark:bg-gray-800 rounded p-2">
                    <p class="font-medium">{{ data_get($variant, 'headline') }}</p>
                    <p class="text-gray-600 dark:text-gray-400">{{ data_get($variant, 'primary_text') }}</p>
                    <p class="text-xs text-gray-500 dark:text-gray-500">{{ data_get($variant, 'description') }}</p>
                </div>
            @endforeach
        </div>
    </div>

    <div class="grid grid-cols-2 gap-4">
        <div>
            <div class="font-semibold text-gray-700 dark:text-gray-200 mb-1">Targeting</div>
            <p class="text-gray-600 dark:text-gray-400">Ages: {{ data_get($record->brief, 'targeting.age_range') }}</p>
            <p class="text-gray-600 dark:text-gray-400">Interests: {{ implode(', ', (array) data_get($record->brief, 'targeting.interests', [])) }}</p>
            <p class="text-gray-600 dark:text-gray-400">Geos: {{ implode(', ', (array) data_get($record->brief, 'targeting.geos', [])) }}</p>
        </div>
        <div>
            <div class="font-semibold text-gray-700 dark:text-gray-200 mb-1">Budget</div>
            <p class="text-gray-600 dark:text-gray-400">
                {{ data_get($record->brief, 'budget_suggestion.currency') }}
                {{ data_get($record->brief, 'budget_suggestion.daily_min') }}–{{ data_get($record->brief, 'budget_suggestion.daily_max') }} / day
            </p>
        </div>
    </div>

    @if ($record->launch_transcript)
        <div>
            <div class="font-semibold text-gray-700 dark:text-gray-200 mb-1">Claude's launch report</div>
            <p class="text-gray-600 dark:text-gray-400 whitespace-pre-line">{{ $record->launch_transcript }}</p>
        </div>
    @endif

    @if ($record->last_optimization_summary)
        <div>
            <div class="font-semibold text-gray-700 dark:text-gray-200 mb-1">Latest optimization pass</div>
            <p class="text-gray-600 dark:text-gray-400">{{ $record->last_optimization_summary }}</p>
            <p class="text-xs text-gray-500 dark:text-gray-500">{{ $record->last_optimized_at?->diffForHumans() }}</p>
        </div>
    @endif

    @if ($record->failure_reason)
        <p class="text-xs text-red-600 dark:text-red-400">{{ $record->failure_reason }}</p>
    @endif
</div>
