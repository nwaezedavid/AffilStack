<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Offer;
use App\Services\Credits\InsufficientCreditsException;
use App\Services\Modules\LinkedInService;
use Illuminate\Http\RedirectResponse;

class LinkedInController extends Controller
{
    protected function run(Offer $offer, string $method, LinkedInService $service): RedirectResponse
    {
        abort_unless($offer->isAccessibleBy(auth()->user()), 403);

        try {
            $service->queue(auth()->user(), $offer, $method);
        } catch (InsufficientCreditsException $e) {
            return back()->with('error', 'Not enough credits for that LinkedIn generation. Upgrade your plan or buy a credit top-up.');
        }

        return redirect()->route('offers.show', $offer)->with('success', "LinkedIn content queued — we'll notify you the moment it's ready.");
    }

    public function keywords(Offer $offer, LinkedInService $service): RedirectResponse
    {
        return $this->run($offer, 'keywords', $service);
    }

    public function dmSequence(Offer $offer, LinkedInService $service): RedirectResponse
    {
        return $this->run($offer, 'dmSequence', $service);
    }

    public function post(Offer $offer, LinkedInService $service): RedirectResponse
    {
        return $this->run($offer, 'post', $service);
    }

    public function article(Offer $offer, LinkedInService $service): RedirectResponse
    {
        return $this->run($offer, 'article', $service);
    }
}
