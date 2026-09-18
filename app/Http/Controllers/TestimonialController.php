<?php

namespace App\Http\Controllers;

use App\Models\Testimonial;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * "I want users to be able to submit their review from their dashboard and
 * it will then appear in the admin dashboard area where I can review, edit
 * and approve it. Once approved, it will become ready to be displayed in
 * the frontend." One testimonial per account — a re-submission always goes
 * back to pending (see update()) so an already-live quote can't be quietly
 * swapped for something else without another look from an admin. See
 * Testimonial::STATUS_* and TestimonialsTable's Approve/Decline actions for
 * the admin side of this.
 */
class TestimonialController extends Controller
{
    public function edit(Request $request): View
    {
        $testimonial = Testimonial::where('user_id', $request->user()->id)->first();

        return view('dashboard.testimonial.edit', [
            'testimonial' => $testimonial,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'author_name' => ['required', 'string', 'max:255'],
            'author_role' => ['nullable', 'string', 'max:255'],
            'quote' => ['required', 'string', 'max:1000'],
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
        ]);

        Testimonial::updateOrCreate(
            ['user_id' => $request->user()->id],
            [
                ...$validated,
                'status' => Testimonial::STATUS_PENDING,
                'is_published' => false,
            ],
        );

        return back()->with('success', 'Thanks! We\'ll review your testimonial and it\'ll appear on the homepage once approved.');
    }
}
