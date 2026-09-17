@extends('layouts.app')

@section('title', 'Intelligence Centre')

@section('content')
    <p class="text-sm text-ink-600 max-w-2xl mb-6">
        A plain-English self-assessment of your account — how your offers and generations are actually performing,
        which channels your plan already unlocks that you haven't tried yet, and whether an upgrade would genuinely
        pay for itself right now. Costs {{ config('credits.costs.intelligence_centre') }} credits to run.
    </p>

    @if (! $report)
        <div class="bg-surface border border-line rounded-lg p-8 text-center max-w-lg">
            <div class="text-3xl mb-3" aria-hidden="true">🧠</div>
            <h2 class="font-display font-semibold text-navy-900 mb-2">No assessment yet</h2>
            <p class="text-sm text-ink-600 mb-5">Run your first Intelligence Centre assessment to see your account's health score and next steps.</p>
            <form method="POST" action="{{ route('intelligence-centre.store') }}">
                @csrf
                <button class="inline-flex items-center gap-2 rounded-md bg-navy-900 text-white text-sm font-medium px-4 py-2.5 hover:bg-navy-800 transition">
                    Run my assessment ({{ config('credits.costs.intelligence_centre') }} credits)
                </button>
            </form>
        </div>
    @else
        @php
            $assessment = $report->assessment;
            $score = $report->healthScore();
            $scoreColor = $score >= 75 ? 'text-emerald-700' : ($score >= 45 ? 'text-amber-600' : 'text-red-600');
        @endphp

        <div class="flex items-center justify-between mb-6">
            <p class="text-xs text-ink-500">Last run {{ $report->generated_at->diffForHumans() }}</p>
            <form method="POST" action="{{ route('intelligence-centre.store') }}">
                @csrf
                <button class="text-xs rounded-md border border-line text-ink-900 px-3 py-1.5 hover:bg-surface-muted transition">
                    Run a fresh assessment ({{ config('credits.costs.intelligence_centre') }} credits)
                </button>
            </form>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-6">
            <div class="bg-surface border border-line rounded-lg p-6 lg:col-span-1 flex flex-col items-center justify-center text-center">
                <div class="text-xs uppercase tracking-wide text-ink-400 font-mono mb-1">Health score</div>
                <div class="text-5xl font-display font-semibold {{ $scoreColor }}">{{ $score }}</div>
                <div class="text-xs text-ink-500 mt-1">out of 100</div>
            </div>
            <div class="bg-surface border border-line rounded-lg p-6 lg:col-span-2 flex items-center">
                <p class="text-sm text-navy-900">{{ $assessment['headline'] }}</p>
            </div>
        </div>

        @if ($report->upgradeRecommended())
            @php $suggestedPlan = $report->suggestedPlan(); @endphp
            <div class="bg-amber-50 border border-amber-200 rounded-lg p-5 mb-6">
                <h3 class="font-display font-semibold text-sm text-amber-900 mb-1">
                    Upgrade recommended{{ $suggestedPlan ? " — {$suggestedPlan->name}" : '' }}
                </h3>
                <p class="text-sm text-amber-800">{{ $assessment['upgrade_reason'] }}</p>
                @if ($suggestedPlan)
                    <a href="{{ route('billing.index') }}" class="inline-flex mt-3 items-center gap-2 rounded-md bg-amber-600 text-white text-xs font-medium px-3 py-2 hover:bg-amber-700 transition">
                        View plans on Billing &amp; Plan
                    </a>
                @endif
            </div>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
            <div class="bg-surface border border-line rounded-lg p-5">
                <h3 class="font-display font-semibold text-sm text-navy-900 mb-3">Strengths</h3>
                <ul class="space-y-2 text-sm text-ink-700">
                    @forelse ($assessment['strengths'] as $item)
                        <li class="flex gap-2"><span class="text-emerald-600">✓</span><span>{{ $item }}</span></li>
                    @empty
                        <li class="text-ink-500">Nothing notable yet.</li>
                    @endforelse
                </ul>
            </div>
            <div class="bg-surface border border-line rounded-lg p-5">
                <h3 class="font-display font-semibold text-sm text-navy-900 mb-3">Gaps</h3>
                <ul class="space-y-2 text-sm text-ink-700">
                    @forelse ($assessment['gaps'] as $item)
                        <li class="flex gap-2"><span class="text-amber-600">!</span><span>{{ $item }}</span></li>
                    @empty
                        <li class="text-ink-500">No major gaps found.</li>
                    @endforelse
                </ul>
            </div>
            <div class="bg-surface border border-line rounded-lg p-5">
                <h3 class="font-display font-semibold text-sm text-navy-900 mb-3">Next steps</h3>
                <ol class="space-y-2 text-sm text-ink-700 list-decimal list-inside">
                    @forelse ($assessment['next_steps'] as $item)
                        <li>{{ $item }}</li>
                    @empty
                        <li class="text-ink-500 list-none">Nothing to add right now.</li>
                    @endforelse
                </ol>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-4 gap-4 mt-6">
            <div class="bg-surface border border-line rounded-lg p-4">
                <div class="text-xs uppercase tracking-wide text-ink-400 font-mono mb-1">Generation success rate</div>
                <div class="text-xl font-display font-semibold text-navy-900">
                    {{ $report->metrics['generations']['success_rate_percent'] !== null ? $report->metrics['generations']['success_rate_percent'].'%' : '—' }}
                </div>
            </div>
            <div class="bg-surface border border-line rounded-lg p-4">
                <div class="text-xs uppercase tracking-wide text-ink-400 font-mono mb-1">Credits remaining</div>
                <div class="text-xl font-display font-semibold text-navy-900">{{ number_format($report->metrics['credits']['balance']) }}</div>
            </div>
            <div class="bg-surface border border-line rounded-lg p-4">
                <div class="text-xs uppercase tracking-wide text-ink-400 font-mono mb-1">Channels used</div>
                <div class="text-xl font-display font-semibold text-navy-900">{{ count($report->metrics['channels']['used']) }} / {{ count($report->metrics['channels']['available_on_plan']) }}</div>
            </div>
            <div class="bg-surface border border-line rounded-lg p-4">
                <div class="text-xs uppercase tracking-wide text-ink-400 font-mono mb-1">Offers</div>
                <div class="text-xl font-display font-semibold text-navy-900">{{ $report->metrics['offers']['total'] }}</div>
            </div>
        </div>
    @endif
@endsection
