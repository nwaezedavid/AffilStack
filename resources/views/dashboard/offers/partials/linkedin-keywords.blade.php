{{-- LinkedIn people/company keywords with Copy / "Search on LinkedIn".
     Expects $research (ResearchPresenter). --}}
@php($linkedin = $research->linkedinKeywords())
@if ($linkedin['people'] !== [] || $linkedin['companies'] !== [])
    <div data-tour="linkedin-keywords" class="@container rounded-3xl border border-line bg-white p-5 sm:p-6">
        <div class="flex items-start gap-3">
            <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-white ring-1 ring-line">
                <x-ui.brand-icon name="linkedin" class="h-5 w-5" />
            </span>
            <div>
                <h4 class="font-display font-semibold text-navy-900">LinkedIn keywords</h4>
                <p class="text-sm text-ink-600">Search these on LinkedIn, then use the Chrome extension to write your connection message and reply to every answer.</p>
            </div>
        </div>
        <div class="mt-4 grid gap-4 @2xl:grid-cols-2">
            @foreach (['people' => ['People to contact', 'user'], 'companies' => ['Companies to target', 'building-office']] as $type => [$typeLabel, $typeIcon])
                @if ($linkedin[$type] !== [])
                    <div>
                        <p class="mb-2 inline-flex items-center gap-1.5 rounded-full bg-sky-50 px-2.5 py-1 text-xs font-semibold text-sky-700">
                            <x-dynamic-component :component="'heroicon-m-'.$typeIcon" class="h-3.5 w-3.5" /> {{ $typeLabel }}
                        </p>
                        <ul class="space-y-2">
                            @foreach ($linkedin[$type] as $keyword)
                                <li class="flex flex-wrap items-center justify-between gap-2 rounded-xl bg-surface-muted px-3 py-2">
                                    <span class="text-sm font-medium text-navy-900 min-w-0 break-words">{{ $keyword }}</span>
                                    <span class="flex shrink-0 items-center gap-1.5">
                                        <x-ui.copy-button :text="$keyword" size="xs" />
                                        <a href="{{ \App\Support\ResearchPresenter::linkedinUrl($type, $keyword) }}" target="_blank" rel="noopener"
                                           class="inline-flex items-center gap-1 rounded-full bg-[#0A66C2] px-2.5 py-1 text-[11px] font-semibold text-white hover:bg-[#004182] transition">
                                            <x-heroicon-m-magnifying-glass class="h-3.5 w-3.5" /> Search on LinkedIn
                                        </a>
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            @endforeach
        </div>
        @if ($research->linkedinTips() !== [])
            <div class="mt-4 rounded-2xl bg-sky-50/70 p-4 ring-1 ring-sky-100">
                <p class="flex items-center gap-1.5 text-xs font-semibold text-sky-800"><x-heroicon-m-light-bulb class="h-4 w-4" /> Search tips</p>
                <ul class="mt-2 space-y-1 text-sm text-ink-900">
                    @foreach ($research->linkedinTips() as $tip)
                        <li class="flex gap-2"><span class="text-sky-500">•</span> {{ $tip }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>
@endif
