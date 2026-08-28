<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Offer;
use App\Services\Credits\InsufficientCreditsException;
use App\Services\Modules\BlogArticleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BlogController extends Controller
{
    public function store(Request $request, Offer $offer, BlogArticleService $service): RedirectResponse
    {
        abort_unless($offer->isAccessibleBy(auth()->user()), 403);

        $validated = $request->validate([
            'target_keyword' => 'nullable|string|max:255',
        ]);

        try {
            $service->queue(auth()->user(), $offer, $validated['target_keyword'] ?? null);
        } catch (InsufficientCreditsException $e) {
            return back()->with('error', 'Not enough credits for a blog article. Upgrade your plan or buy a credit top-up.');
        }

        return redirect()->route('offers.show', $offer)->with('success', "Blog article queued — we'll notify you the moment it's ready.");
    }
}
