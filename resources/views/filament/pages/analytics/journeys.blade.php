<x-filament-panels::page>
    @include('filament.pages.analytics._insights-styles')

    @php
        $user = $this->selectedUser;
        $visitor = $this->selectedVisitor;
        $profile = $this->profile;
        $icons = ['session' => 'heroicon-m-arrow-right-end-on-rectangle', 'page' => 'heroicon-m-document-text', 'event' => 'heroicon-m-cursor-arrow-rays', 'conversion' => 'heroicon-m-check-badge', 'feature' => 'heroicon-m-sparkles', 'consent' => 'heroicon-m-shield-check'];
    @endphp

    <div class="afs-ins">
        <div class="afs-ins-card">
            <label for="journey-search" class="afs-ins-card-title" style="margin-bottom:.5rem;"><x-filament::icon icon="heroicon-o-magnifying-glass" /> Find a person</label>
            <input id="journey-search" type="search" wire:model.live.debounce.400ms="search" class="afs-ins-input" placeholder="Email, name, user id — or the 8-character visitor id from an export" autocomplete="off">
            <p class="afs-ins-card-sub" style="margin-top:.5rem;">Shows everything a person did on AffilStack (only people who allowed analytics are tracked), including their anonymous visits before they signed up.</p>

            @php($matches = $this->matches)
            @if (mb_strlen(trim($search)) >= 2 && ! $user && ! $visitor)
                <div style="margin-top: 1rem;">
                    @if ($matches['users']->isEmpty() && $matches['visitors']->isEmpty())
                        <div class="afs-ins-empty"><x-filament::icon icon="heroicon-o-user" /><span>No one matches “{{ $search }}”.</span></div>
                    @else
                        <div class="afs-ins-table-wrap">
                            <table class="afs-ins-table">
                                <tbody>
                                    @foreach ($matches['users'] as $match)
                                        <tr>
                                            <td><strong>{{ $match->name }}</strong><div class="afs-ins-card-sub">{{ $match->email }} · user #{{ $match->id }}</div></td>
                                            <td class="afs-ins-num"><button type="button" class="afs-ins-btn" wire:click="selectUser({{ $match->id }})">View journey</button></td>
                                        </tr>
                                    @endforeach
                                    @foreach ($matches['visitors'] as $match)
                                        <tr>
                                            <td><strong>Visitor {{ $match->shortId() }}</strong><div class="afs-ins-card-sub">{{ $match->user_id ? 'linked to user #'.$match->user_id : 'anonymous' }} · last seen {{ $match->last_seen_at?->diffForHumans() }}</div></td>
                                            <td class="afs-ins-num"><button type="button" class="afs-ins-btn" wire:click="selectVisitor({{ $match->id }})">View journey</button></td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            @endif
        </div>

        @if (! $user && ! $visitor)
            <div class="afs-ins-card">
                <div class="afs-ins-card-head"><div><h3 class="afs-ins-card-title"><x-filament::icon icon="heroicon-o-clock" /> Recently active</h3><p class="afs-ins-card-sub">People with an interest profile, most recent first</p></div></div>
                @if ($this->recent->isEmpty())
                    <div class="afs-ins-empty"><x-filament::icon icon="heroicon-o-user-group" /><span>No profiles yet. They appear once visitors allow analytics and the profile builder runs (every 30 minutes).</span></div>
                @else
                    <div class="afs-ins-table-wrap">
                        <table class="afs-ins-table">
                            <thead><tr><th>Person</th><th>Stage</th><th class="afs-ins-num">Score</th><th>Came from</th><th class="afs-ins-num">Last seen</th><th></th></tr></thead>
                            <tbody>
                                @foreach ($this->recent as $row)
                                    <tr>
                                        <td>
                                            @if ($row->user)
                                                <strong>{{ $row->user->name }}</strong><div class="afs-ins-card-sub">{{ $row->user->email }}</div>
                                            @else
                                                <strong>Visitor {{ $row->visitor?->shortId() }}</strong><div class="afs-ins-card-sub">anonymous</div>
                                            @endif
                                        </td>
                                        <td><span class="afs-ins-pill {{ in_array($row->lifecycle_stage, ['customer', 'active'], true) ? 'afs-ins-pill--good' : (in_array($row->lifecycle_stage, ['at_risk', 'churned'], true) ? 'afs-ins-pill--warn' : 'afs-ins-pill--brand') }}">{{ $row->stageLabel() }}</span></td>
                                        <td class="afs-ins-num">{{ $row->engagement_score }}</td>
                                        <td>{{ $row->ft_source ?: $row->ft_channel ?: '—' }}</td>
                                        <td class="afs-ins-num">{{ $row->last_seen_at?->diffForHumans() ?? '—' }}</td>
                                        <td class="afs-ins-num">
                                            <button type="button" class="afs-ins-btn" wire:click="{{ $row->user_id ? 'selectUser('.$row->user_id.')' : 'selectVisitor('.$row->visitor_id.')' }}">Open</button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        @else
            <div class="afs-ins-hero">
                <div class="afs-ins-hero-row">
                    <div>
                        <div class="afs-ins-eyebrow">{{ $user ? 'Customer journey' : 'Visitor journey' }}</div>
                        <h2>{{ $user ? $user->name : 'Visitor '.$visitor->shortId() }}</h2>
                        <p>
                            @if ($user)
                                {{ $user->email }} · user #{{ $user->id }} · joined {{ $user->created_at?->format('M j, Y') }}
                            @else
                                {{ $visitor->user_id ? 'Linked to user #'.$visitor->user_id : 'Not signed up (anonymous)' }} · first seen {{ $visitor->first_seen_at?->format('M j, Y') }}
                            @endif
                        </p>
                    </div>
                    <div class="afs-ins-filter">
                        <button type="button" class="afs-ins-chip" wire:click="refreshProfile">Refresh profile</button>
                        <button type="button" class="afs-ins-chip" wire:click="clearSelection">Back to search</button>
                    </div>
                </div>
            </div>

            <div class="afs-ins-grid afs-ins-grid--3">
                <div class="afs-ins-card">
                    <div class="afs-ins-card-head"><h3 class="afs-ins-card-title"><x-filament::icon icon="heroicon-o-identification" /> Profile</h3></div>
                    @if ($profile)
                        <dl class="afs-ins-dl">
                            <dt>Stage</dt><dd><span class="afs-ins-pill afs-ins-pill--brand">{{ $profile->stageLabel() }}</span></dd>
                            <dt>Engagement</dt><dd><strong>{{ $profile->engagement_score }}</strong>/100</dd>
                            <dt>Visits</dt><dd>{{ number_format($profile->sessions_count) }} · {{ number_format($profile->pageviews_count) }} pages</dd>
                            <dt>Viewed pricing</dt><dd>{{ $profile->viewed_pricing_at?->format('M j, Y') ?? '—' }}</dd>
                            <dt>Checkout</dt><dd>{{ $profile->checkout_started_at?->format('M j, Y') ?? '—' }}</dd>
                            <dt>Paid</dt><dd>{{ $profile->paid_at?->format('M j, Y') ?? '—' }}</dd>
                            <dt>Last seen</dt><dd>{{ $profile->last_seen_at?->diffForHumans() ?? '—' }}</dd>
                        </dl>
                        <p class="afs-ins-card-sub" style="margin-top:.75rem;">Updated {{ $profile->computed_at?->diffForHumans() }}</p>
                    @else
                        <div class="afs-ins-empty"><x-filament::icon icon="heroicon-o-identification" /><span>No profile — either this person hasn't allowed analytics, or it hasn't been built yet (try "Refresh profile").</span></div>
                    @endif
                </div>

                <div class="afs-ins-card">
                    <div class="afs-ins-card-head"><h3 class="afs-ins-card-title"><x-filament::icon icon="heroicon-o-light-bulb" /> Interests</h3></div>
                    @php($interests = (array) ($profile?->interests ?? []))
                    @if (empty($interests))
                        <div class="afs-ins-empty"><x-filament::icon icon="heroicon-o-light-bulb" /><span>Nothing yet.</span></div>
                    @else
                        <dl class="afs-ins-dl">
                            @if (! empty($interests['features']))
                                <dt>Uses</dt><dd>{{ collect($interests['features'])->map(fn ($n, $k) => \App\Models\AnalyticsEvent::label($k).' ('.$n.')')->implode(', ') }}</dd>
                            @endif
                            @if (! empty($interests['modules']))
                                <dt>Creates</dt><dd>{{ collect($interests['modules'])->map(fn ($n, $k) => str($k)->replace('_', ' ')->title().' ('.$n.')')->implode(', ') }}</dd>
                            @endif
                            @if (! empty($interests['networks']))
                                <dt>Networks</dt><dd>{{ collect($interests['networks'])->keys()->implode(', ') }}</dd>
                            @endif
                            @if (! empty($interests['products']))
                                <dt>Researched</dt><dd>{{ implode(', ', $interests['products']) }}</dd>
                            @endif
                            @if (! empty($interests['top_pages']))
                                <dt>Reads</dt><dd>{{ collect($interests['top_pages'])->keys()->map(fn ($p) => '/'.ltrim($p, '/'))->implode(', ') }}</dd>
                            @endif
                            @if (! empty($interests['ctas']))
                                <dt>Clicked</dt><dd>{{ collect($interests['ctas'])->keys()->implode(', ') }}</dd>
                            @endif
                        </dl>
                    @endif
                </div>

                <div class="afs-ins-card">
                    <div class="afs-ins-card-head"><h3 class="afs-ins-card-title"><x-filament::icon icon="heroicon-o-arrows-right-left" /> How they found you</h3></div>
                    @php($first = $this->devices->sortBy('first_seen_at')->first())
                    @php($latest = $this->devices->sortByDesc('lt_at')->first())
                    <dl class="afs-ins-dl">
                        <dt>First visit</dt><dd>{{ $first ? collect([$first->ft_channel, $first->ft_source, $first->ft_campaign])->filter()->unique()->implode(' · ') : '—' }}</dd>
                        <dt>Landing page</dt><dd class="afs-ins-mono">{{ $first?->ft_landing_path ? '/'.ltrim($first->ft_landing_path, '/') : '—' }}</dd>
                        <dt>Latest source</dt><dd>{{ $latest ? collect([$latest->lt_channel, $latest->lt_source, $latest->lt_campaign])->filter()->unique()->implode(' · ') : '—' }}</dd>
                        <dt>Devices</dt><dd>{{ $this->devices->map(fn ($d) => trim(ucfirst((string) $d->device_type).' '.$d->browser))->unique()->implode(', ') ?: '—' }}</dd>
                        <dt>Location</dt><dd>{{ $this->devices->map(fn ($d) => $d->city ? $d->city.', '.$d->country : $d->country)->filter()->unique()->implode('; ') ?: '—' }}</dd>
                        <dt>Consent</dt>
                        <dd>
                            @if ($this->consent)
                                <span class="afs-ins-pill {{ $this->consent->analytics ? 'afs-ins-pill--good' : '' }}">Analytics {{ $this->consent->analytics ? 'on' : 'off' }}</span>
                                <span class="afs-ins-pill {{ $this->consent->marketing ? 'afs-ins-pill--good' : '' }}">Ads {{ $this->consent->marketing ? 'on' : 'off' }}</span>
                            @else
                                No choice recorded
                            @endif
                        </dd>
                    </dl>
                </div>
            </div>

            <div class="afs-ins-card">
                <div class="afs-ins-card-head"><div><h3 class="afs-ins-card-title"><x-filament::icon icon="heroicon-o-queue-list" /> Timeline</h3><p class="afs-ins-card-sub">Newest first · up to 150 items</p></div></div>
                @if ($this->timeline->isEmpty())
                    <div class="afs-ins-empty"><x-filament::icon icon="heroicon-o-queue-list" /><span>No tracked activity — this person hasn't allowed analytics on any device.</span></div>
                @else
                    <ol class="afs-ins-timeline">
                        @foreach ($this->timeline as $item)
                            <li class="afs-ins-tl-item">
                                <span class="afs-ins-tl-dot afs-ins-tl-dot--{{ $item['kind'] }}" aria-hidden="true"><x-filament::icon :icon="$icons[$item['kind']] ?? 'heroicon-m-cursor-arrow-rays'" /></span>
                                <div>
                                    <div class="afs-ins-tl-title">{{ $item['title'] }}</div>
                                    <div class="afs-ins-tl-meta"><time datetime="{{ $item['at']->toIso8601String() }}">{{ $item['at']->format('M j, Y · H:i') }}</time>@if ($item['meta']) · {{ $item['meta'] }}@endif</div>
                                </div>
                            </li>
                        @endforeach
                    </ol>
                @endif
            </div>
        @endif
    </div>
</x-filament-panels::page>
