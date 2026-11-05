<?php

namespace App\Jobs;

use App\Models\Generation;
use App\Services\Modules\QuickVideoService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Follows a Higgsfield render without holding a queue worker: checks once,
 * then schedules itself again in 30 seconds until the video is finished,
 * failed or past QuickVideoService::HIGGSFIELD_TIMEOUT_MINUTES.
 */
class CheckHiggsfieldVideo implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 300;

    public function __construct(public Generation $generation) {}

    public function handle(QuickVideoService $service): void
    {
        // Already finished (e.g. by the webhook, which sent the notification).
        if ($this->generation->refresh()->status !== 'queued') {
            return;
        }

        if ($service->checkHiggsfield($this->generation)) {
            RunQuickVideo::notifyIfFinished($this->generation->refresh());

            return;
        }

        self::dispatch($this->generation)->delay(now()->addSeconds(30));
    }
}
