<?php

namespace App\Services\Calendar;

use App\Models\Generation;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Feature 5 (Phase 3 backlog, item 5): one view of every publishable piece
 * of content across every channel, plus reminders computed from real data
 * already in the system — see config/calendar.php for why this isn't an
 * actual auto-posting scheduler.
 */
class ContentCalendarService
{
    public function __construct(protected ?int $blogRefreshDays = null, protected ?int $lookaheadDays = null)
    {
        $this->blogRefreshDays ??= (int) config('calendar.blog_refresh_days');
        $this->lookaheadDays ??= (int) config('calendar.reminder_lookahead_days');
    }

    /**
     * @return Collection<int, Generation>
     */
    public function entriesFor(User $user): Collection
    {
        return $user->visibleGenerations()
            ->whereIn('module', config('calendar.publishable_modules'))
            ->with('offer')
            ->get()
            ->sortBy(fn (Generation $g) => $g->scheduled_for?->timestamp ?? $g->created_at->timestamp)
            ->values();
    }

    /**
     * @return array<int, array{type: string, due: Carbon, overdue: bool, title: string, detail: string, offer_id: int, offer_name: string}>
     */
    public function upcomingReminders(User $user): array
    {
        $reminders = collect()
            ->concat($this->blogRefreshReminders($user))
            ->concat($this->linkedinFollowUpReminders($user));

        return $reminders->sortBy('due')->values()->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function blogRefreshReminders(User $user): array
    {
        $cutoff = now()->addDays($this->lookaheadDays);

        return $user->visibleGenerations()
            ->where('module', 'blog_article')
            ->where('calendar_status', 'published')
            ->whereNotNull('published_at')
            ->with('offer')
            ->get()
            ->map(function (Generation $g) {
                $due = $g->published_at->copy()->addDays($this->blogRefreshDays);

                return [
                    'type' => 'blog_refresh',
                    'due' => $due,
                    'overdue' => $due->isPast(),
                    'title' => $g->calendarTitle(),
                    'detail' => 'Published '.$g->published_at->diffForHumans().' — refresh stats/examples to protect its SEO ranking.',
                    'offer_id' => $g->offer_id,
                    'offer_name' => $g->offer?->product_name ?? 'Untitled offer',
                ];
            })
            ->filter(fn (array $r) => $r['due']->lessThanOrEqualTo($cutoff))
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function linkedinFollowUpReminders(User $user): array
    {
        $cutoff = now()->addDays($this->lookaheadDays);

        $reminders = [];

        $sequences = $user->visibleGenerations()
            ->where('module', 'linkedin_dm_sequence')
            ->where('status', 'completed')
            ->whereNotNull('published_at')
            ->with('offer')
            ->get();

        foreach ($sequences as $sequence) {
            foreach ($sequence->output_meta['messages'] ?? [] as $message) {
                $dayNumber = $this->parseDayNumber($message['send_timing'] ?? '');

                if ($dayNumber === null) {
                    continue;
                }

                $due = $sequence->published_at->copy()->addDays($dayNumber);

                if ($due->greaterThan($cutoff)) {
                    continue;
                }

                $reminders[] = [
                    'type' => 'linkedin_followup',
                    'due' => $due,
                    'overdue' => $due->isPast(),
                    'title' => 'Step '.($message['step'] ?? '?').' — '.($message['goal'] ?? 'follow-up'),
                    'detail' => $message['send_timing'] ?? '',
                    'offer_id' => $sequence->offer_id,
                    'offer_name' => $sequence->offer?->product_name ?? 'Untitled offer',
                ];
            }
        }

        return $reminders;
    }

    /**
     * Extracts a leading day count from AI-written free text like "Day 3
     * if no reply" or "Day 0 — right after connecting". Best-effort: the
     * text is model output, not a structured field, so a sequence that
     * doesn't start with "Day N" just produces no reminder rather than a
     * wrong one.
     */
    protected function parseDayNumber(string $sendTiming): ?int
    {
        if (preg_match('/day\s+(\d+)/i', $sendTiming, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }
}
