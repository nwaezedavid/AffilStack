<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Offer;
use App\Services\Credits\InsufficientCreditsException;
use App\Services\Modules\YouTubeService;
use Illuminate\Http\RedirectResponse;
use RuntimeException;

class YouTubeController extends Controller
{
    protected function run(Offer $offer, string $method, YouTubeService $service): RedirectResponse
    {
        abort_unless($offer->user_id === auth()->id(), 403);

        if (! auth()->user()->canUseChannel('youtube')) {
            return back()->with('error', 'The YouTube module isn\'t included in your current plan — upgrade to unlock it.');
        }

        try {
            $service->queue(auth()->user(), $offer, $method);
        } catch (InsufficientCreditsException $e) {
            return back()->with('error', 'Not enough credits for that YouTube generation. Upgrade your plan or buy a credit top-up.');
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('offers.show', $offer)->with('success', "YouTube content queued — we'll notify you the moment it's ready.");
    }

    public function script(Offer $offer, YouTubeService $service): RedirectResponse
    {
        return $this->run($offer, 'script', $service);
    }

    public function metadata(Offer $offer, YouTubeService $service): RedirectResponse
    {
        return $this->run($offer, 'metadata', $service);
    }
}
