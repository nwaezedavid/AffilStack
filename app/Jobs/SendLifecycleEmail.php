<?php

namespace App\Jobs;

use App\Models\LifecycleEmailLog;
use App\Services\Agents\SamAgentService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Throwable;

/**
 * Delivers one claimed lifecycle email (see LifecycleEmailDispatcher::
 * claim()). Runs on the database queue, so it works with Hostinger's
 * cron-driven `queue:work --stop-when-empty`. Sam owns the actual
 * delivery (SamAgentService::deliverLifecycleEmail) — every outbound
 * user communication goes through Sam — and re-checks at send time that
 * the email is still wanted, so a re-engagement nudge queued just before
 * the user came back is dropped rather than sent.
 */
class SendLifecycleEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    public int $backoff = 120;

    public function __construct(public int $logId) {}

    public function handle(SamAgentService $sam): void
    {
        $log = LifecycleEmailLog::find($this->logId);

        if ($log) {
            $sam->deliverLifecycleEmail($log);
        }
    }

    public function failed(?Throwable $exception): void
    {
        LifecycleEmailLog::whereKey($this->logId)
            ->where('status', LifecycleEmailLog::STATUS_QUEUED)
            ->update([
                'status' => LifecycleEmailLog::STATUS_FAILED,
                'error' => mb_substr((string) $exception?->getMessage(), 0, 2000),
                'updated_at' => now(),
            ]);
    }
}
