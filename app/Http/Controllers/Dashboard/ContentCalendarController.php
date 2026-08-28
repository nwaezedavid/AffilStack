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
        abort_unless($generation->user_id === auth()->id(), 403);

        $validated = $request->validate([
            'calendar_status' => 'required|in:draft,scheduled,published,skipped',
            'scheduled_for' => 'nullable|date',
        ]);

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
     * post or a social caption is.
     */
    public function markSequenceStarted(Generation $generation): RedirectResponse
    {
        abort_unless($generation->user_id === auth()->id(), 403);
        abort_unless($generation->module === 'linkedin_dm_sequence', 404);

        $generation->update(['published_at' => $generation->published_at ?? now()]);

        return back()->with('success', 'Marked as started — follow-up reminders will show on your content calendar.');
    }
}
