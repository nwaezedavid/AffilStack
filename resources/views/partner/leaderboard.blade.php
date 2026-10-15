@extends('layouts.partner')

@section('title', 'Leaderboard')

@section('content')
    <div class="grid gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2">
            <x-ui.card :title="'Top partners · '.$month->format('F Y')" description="Ranked by new paying customers this month. Resets on the 1st." icon="trophy" padding="p-0">
                @if ($board->isEmpty())
                    <p class="px-5 py-8 text-center text-sm text-ink-600 sm:px-6">No paying customers yet this month. The first partner to bring one in takes the top spot.</p>
                @else
                    <ol class="divide-y divide-line">
                        @foreach ($board as $row)
                            @php($me = $row['user']->id === $user->id)
                            <li class="flex items-center gap-4 px-5 py-3.5 sm:px-6 {{ $me ? 'bg-emerald-50/70' : '' }}">
                                <span @class([
                                    'inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full font-display text-sm font-semibold tabular-nums',
                                    'bg-gold-400 text-navy-950' => $row['rank'] === 1,
                                    'bg-slate-200 text-navy-900' => $row['rank'] === 2,
                                    'bg-amber-700/20 text-amber-900' => $row['rank'] === 3,
                                    'bg-surface-muted text-ink-600' => $row['rank'] > 3,
                                ])>{{ $row['rank'] }}</span>
                                <span class="flex-1 font-medium text-navy-900">{{ $row['display'] }} @if ($me)<span class="ml-1 rounded-full bg-emerald-600 px-2 py-0.5 text-[11px] font-semibold text-white">You</span>@endif</span>
                                <span class="text-right">
                                    <span class="block font-mono text-lg font-semibold tabular-nums text-navy-900">{{ $row['paying'] }}</span>
                                    <span class="block text-[11px] text-ink-400">paying {{ \Illuminate\Support\Str::plural('customer', $row['paying']) }}</span>
                                </span>
                            </li>
                        @endforeach
                    </ol>
                @endif
            </x-ui.card>
        </div>
        <div class="space-y-6">
            <div class="rounded-2xl bg-gradient-to-br from-emerald-950 via-emerald-900 to-teal-700 p-5 sm:p-6 text-white">
                <p class="text-[11px] font-mono uppercase tracking-[0.18em] text-gold-300">Your position</p>
                <p class="mt-2 font-display text-4xl font-semibold">{{ $myRank ? '#'.$myRank : '—' }}</p>
                <p class="mt-1 text-sm text-emerald-100/80">{{ $myRank ? $myPaying.' paying '.\Illuminate\Support\Str::plural('customer', $myPaying).' this month' : 'Refer your first paying customer this month to get on the board.' }}</p>
            </div>
            <x-ui.card title="Privacy" icon="eye-slash">
                <p class="text-sm text-ink-600">Only first names and initials are shown, never earnings. {{ $user->show_on_partner_leaderboard ? 'You appear on the board.' : 'You\'re hidden from other partners.' }}</p>
                <a href="{{ route('partner.account') }}#leaderboard" class="mt-3 inline-block text-sm font-medium text-emerald-700 hover:text-emerald-800">Change this</a>
            </x-ui.card>
        </div>
    </div>
@endsection
