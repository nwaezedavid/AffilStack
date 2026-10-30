<?php

namespace App\Jobs;

use App\Models\Offer;
use App\Notifications\GenerationCompleted;
use App\Services\Leads\LeadFinder;
use App\Services\Leads\LeadFinderException;
use App\Services\Modules\OfferResearchService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RunOfferResearch implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * A failed AI generation already records itself as "failed" inside the
     * service — an automatic retry would just fail the same way again and
     * delay the user's failure notification, so this runs once.
     */
    public int $tries = 1;

    /**
     * Above OpenAI's own 90s request timeout (config/ai.php) so the worker
     * never kills a generation mid-call and leaves it stuck "processing".
     */
    public int $timeout = 180;

    public function __construct(public Offer $offer) {}

    public function handle(OfferResearchService $service): void
    {
        $service->research($this->offer);

        $this->offer->refresh();

        $this->offer->user->notify(new GenerationCompleted(
            module: 'research',
            title: $this->offer->product_name,
            success: $this->offer->status === 'ready',
            url: route('offers.show', $this->offer),
            errorMessage: $this->offer->generations()->where('module', 'research')->latest()->value('error_message'),
        ));

        // "Also find buyers" was ticked: the research is the buyer profile the lead search needs.
        if ($this->offer->status === 'ready' && $this->offer->find_leads) {
            try {
                app(LeadFinder::class)->queue($this->offer->user, $this->offer, (int) config('leads.default_run_size'), 'research');
            } catch (LeadFinderException) {
                // No allowance, credits or source right now — the product page offers a "Find leads" button.
            }
        }
    }
}
