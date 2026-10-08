<?php

namespace App\Services\Analytics;

use App\Models\AnalyticsEvent;
use App\Models\AnalyticsSession;
use App\Models\AnalyticsVisitor;
use App\Models\ConsentRecord;
use App\Models\PageView;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Agent 04: one person's journey for Analytics → Journeys — visits (with
 * how they arrived), pages, events and cookie choices merged into a single
 * newest-first timeline, across every device linked to them. Because
 * visitors are back-filled on login/purchase (IdentityStitcher), a
 * customer's timeline starts with the anonymous visits BEFORE they bought.
 */
class JourneyTimeline
{
    /**
     * @return Collection<int, array{at: Carbon, kind: string, title: string, meta: ?string}>
     */
    public function forUser(User $user, int $limit = 150): Collection
    {
        $visitorIds = AnalyticsVisitor::query()->where('user_id', $user->id)->pluck('id');

        return $this->merge(
            AnalyticsSession::query()->where(fn ($q) => $q->where('user_id', $user->id)->orWhereIn('visitor_id', $visitorIds))->latest('started_at')->limit($limit)->get(),
            PageView::query()->where(fn ($q) => $q->where('user_id', $user->id)->orWhereIn('visitor_id', $visitorIds))->latest('id')->limit($limit)->get(),
            AnalyticsEvent::query()->where(fn ($q) => $q->where('user_id', $user->id)->orWhereIn('visitor_id', $visitorIds))->latest('id')->limit($limit)->get(),
            ConsentRecord::query()->where(fn ($q) => $q->where('user_id', $user->id)->orWhereIn('visitor_id', $visitorIds))->latest('id')->limit(20)->get(),
            $limit,
        );
    }

    /**
     * @return Collection<int, array{at: Carbon, kind: string, title: string, meta: ?string}>
     */
    public function forVisitor(AnalyticsVisitor $visitor, int $limit = 150): Collection
    {
        return $this->merge(
            AnalyticsSession::query()->where('visitor_id', $visitor->id)->latest('started_at')->limit($limit)->get(),
            PageView::query()->where('visitor_id', $visitor->id)->latest('id')->limit($limit)->get(),
            AnalyticsEvent::query()->where('visitor_id', $visitor->id)->latest('id')->limit($limit)->get(),
            ConsentRecord::query()->where('visitor_id', $visitor->id)->latest('id')->limit(20)->get(),
            $limit,
        );
    }

    /**
     * @param  Collection<int, AnalyticsSession>  $sessions
     * @param  Collection<int, PageView>  $views
     * @param  Collection<int, AnalyticsEvent>  $events
     * @param  Collection<int, ConsentRecord>  $consents
     * @return Collection<int, array{at: Carbon, kind: string, title: string, meta: ?string}>
     */
    protected function merge(Collection $sessions, Collection $views, Collection $events, Collection $consents, int $limit): Collection
    {
        $items = collect();

        foreach ($sessions as $s) {
            $how = collect([$s->channel, $s->source && $s->source !== '(direct)' ? $s->source : null, $s->campaign ? 'campaign “'.$s->campaign.'”' : null, $s->click_id_type ? $s->click_id_type.' ad click' : null])->filter()->unique()->implode(' · ');
            $items->push([
                'at' => $s->started_at,
                'kind' => 'session',
                'title' => 'New visit — '.($how ?: 'Direct'),
                'meta' => collect([
                    'landed on /'.ltrim((string) $s->landing_path, '/'),
                    $s->pageviews.' page'.($s->pageviews === 1 ? '' : 's'),
                    $s->engaged_seconds ? round($s->engaged_seconds / 60, 1).' min engaged' : null,
                    trim(ucfirst((string) $s->device_type).' · '.$s->browser.' · '.$s->os, ' ·'),
                    $s->city ? $s->city.', '.$s->country : $s->country,
                ])->filter()->implode(' · '),
            ]);
        }

        foreach ($views as $v) {
            $items->push([
                'at' => $v->created_at,
                'kind' => 'page',
                'title' => 'Viewed /'.ltrim((string) $v->path, '/'),
                'meta' => collect([
                    $v->duration_seconds !== null ? $v->duration_seconds.'s on page' : null,
                    $v->scroll_depth !== null ? 'scrolled '.$v->scroll_depth.'%' : null,
                ])->filter()->implode(' · ') ?: null,
            ]);
        }

        foreach ($events as $e) {
            $props = collect((array) $e->properties)->map(fn ($value, $key) => str_replace('_', ' ', (string) $key).': '.(is_bool($value) ? ($value ? 'yes' : 'no') : $value))->implode(' · ');
            $value = $e->value_cents !== null && $e->value_cents > 0 ? number_format($e->value_cents / 100, 2).' '.$e->currency : null;

            $items->push([
                'at' => $e->created_at,
                'kind' => in_array($e->category, ['conversion', 'feature'], true) ? $e->category : 'event',
                'title' => AnalyticsEvent::label($e->name),
                'meta' => collect([$props ?: null, $value, $e->path ? 'on /'.ltrim($e->path, '/') : null])->filter()->implode(' · ') ?: null,
            ]);
        }

        foreach ($consents as $c) {
            $items->push([
                'at' => $c->created_at,
                'kind' => 'consent',
                'title' => 'Cookie choice: '.(ConsentRecord::ACTIONS[$c->action] ?? $c->action),
                'meta' => 'analytics '.($c->analytics ? 'on' : 'off').' · advertising '.($c->marketing ? 'on' : 'off').' · policy '.$c->policy_version,
            ]);
        }

        return $items->filter(fn ($item) => $item['at'] !== null)->sortByDesc(fn ($item) => $item['at']->getTimestamp())->take($limit)->values();
    }
}
