<?php

namespace App\Jobs;

use App\Models\Generation;
use App\Notifications\GenerationCompleted;
use App\Services\Modules\QuickVideoService;
use App\Support\OfferFeatures;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * A one-click video: writes the script, then renders it (HeyGen styles
 * finish here; Higgsfield styles hand over to CheckHiggsfieldVideo).
 */
class RunQuickVideo implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 840; // AI script + up to 8 min of HeyGen polling + download (< queue retry_after 900)

    public function __construct(public Generation $generation) {}

    public function handle(QuickVideoService $service): void
    {
        $service->run($this->generation);

        self::notifyIfFinished($this->generation->refresh());
    }

    public static function notifyIfFinished(Generation $generation): void
    {
        if ($generation->status === 'queued') {
            return;
        }

        $generation->user->notify(new GenerationCompleted(
            module: 'ugc_video',
            title: $generation->offer->product_name,
            success: $generation->status === 'completed',
            url: OfferFeatures::urlForGeneration($generation),
            errorMessage: $generation->error_message,
        ));
    }
}
