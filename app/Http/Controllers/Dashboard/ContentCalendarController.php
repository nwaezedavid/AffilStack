<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Generation;
use App\Services\Calendar\ContentCalendarService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ContentCalendarController extends Controller
{
    public function index(ContentCalendarService $calendar): View
    {
        $user = auth()->user();

        $entries = $calendar->entriesFor($user);
        $reminders = $calendar->upcomingReminders($user);

        return view('dashboard.calendar.index', compact('entries', 'reminders'));
    }

    /**
     * Updates a publishable generation's calendar status/target date. When
     * moved to "published" for the first time, published_at is stamped
     * automatically — that's what blog-refresh reminders anchor to, so
     * there's no separate "mark published" step for the user to remember.
     */
    public function update(Request $request, Generation $generation): RedirectResponse
    {
        abort_unless($generation->isAccessibleBy(auth()->user()), 403);

        $validated = $request->validate([
            'calendar_status' => 'required|in:draft,scheduled,published,skipped',
            'scheduled_for' => 'nullable|date',
        ]);

        // Team seats (item 10) are "draft only, no publish" — they can plan
        // and schedule, but only the account owner can mark something as
        // actually published.
        abort_if(
            auth()->user()->isSeat() && $validated['calendar_status'] === 'published',
            403,
            'Team members can\'t mark content as published — ask the account owner to do that.',
        );

        if ($validated['calendar_status'] === 'published' && ! $generation->published_at) {
            $validated['published_at'] = now();
        }

        $generation->update($validated);

        return back()->with('success', 'Calendar entry updated.');
    }

    /**
     * Stamps published_at on a linkedin_dm_sequence generation — the "I
     * actually started sending this" marker the follow-up reminders anchor
     * to, since a DM sequence isn't a single dated publish the way a blog
     * post or a social caption is. Treated the same as "publish" for team
     * seats (item 10) — it's a real-world action, not a draft — so only the
     * account owner can confirm it.
     */
    public function markSequenceStarted(Generation $generation): RedirectResponse
    {
        abort_unless($generation->isAccessibleBy(auth()->user()), 403);
        abort_unless($generation->module === 'linkedin_dm_sequence', 404);
        abort_if(auth()->user()->isSeat(), 403, 'Team members can\'t mark a sequence as started — ask the account owner to do that.');

        $generation->update(['published_at' => $generation->published_at ?? now()]);

        return back()->with('success', 'Marked as started — follow-up reminders will show on your content calendar.');
    }
}
