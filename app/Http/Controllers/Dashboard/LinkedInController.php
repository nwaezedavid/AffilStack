<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Generation;
use App\Models\Offer;
use App\Services\Credits\InsufficientCreditsException;
use App\Services\Modules\LinkedInService;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

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

    /**
     * Task #3: download a generated post/article as a plain-text file,
     * ready to paste into LinkedIn — gated behind having connected a
     * LinkedIn identity first (Social Connections page), so exported
     * content is always tied to a real, named account rather than an
     * anonymous download. Never posts anything itself — see
     * App\Services\Social\LinkedInOAuthService's docblock.
     */
    public function export(Generation $generation): RedirectResponse|Response
    {
        abort_unless($generation->isAccessibleBy(auth()->user()), 403);
        abort_unless(in_array($generation->module, ['linkedin_post', 'linkedin_article'], true), 404);

        if (! auth()->user()->socialConnections()->where('provider', 'linkedin')->exists()) {
            return back()->with('error', 'Connect your LinkedIn account first — see Connected Accounts.');
        }

        $offer = $generation->offer;

        if ($generation->module === 'linkedin_post') {
            $body = $offer->cloak($generation->output_meta['post_text'] ?? '', $generation->module);
            $filename = "linkedin-post-{$generation->id}.txt";
        } else {
            $headline = $generation->output_meta['headline'] ?? '';
            $article = $offer->cloak($generation->output_meta['article_markdown'] ?? '', $generation->module);
            $body = "{$headline}\n\n{$article}";
            $filename = "linkedin-article-{$generation->id}.txt";
        }

        return response($body, 200, [
            'Content-Type' => 'text/plain',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
