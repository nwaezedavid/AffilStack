<?php

namespace App\Jobs;

use App\Models\LeadRun;
use App\Services\Leads\LeadFinder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Runs one B2B lead search in the background — see LeadFinder::run().
 */
class FindLeads implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * A retry would pay the lead providers twice for the same people, and
     * LeadFinder::run() always records its own failure, so this runs once.
     */
    public int $tries = 1;

    public int $timeout = 900;

    public function __construct(public LeadRun $run) {}

    public function handle(LeadFinder $finder): void
    {
        $finder->run($this->run);
    }
}
