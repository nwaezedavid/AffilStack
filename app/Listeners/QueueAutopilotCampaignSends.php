<?php

namespace App\Listeners;

use App\Events\CrmContactAddedToList;

class QueueAutopilotCampaignSends
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(CrmContactAddedToList $event): void
    {
        //
    }
}
