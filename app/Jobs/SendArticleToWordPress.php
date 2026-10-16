<?php

namespace App\Jobs;

use App\Models\WordpressDelivery;
use App\Services\WordPress\WordPressPusher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Sends one article to one WordPress site as a draft. The pusher records
 * success or a readable failure on the delivery itself, so this never
 * throws and never retries a request that may already have created a
 * draft (the plugin updates the same draft if the customer sends again).
 */
class SendArticleToWordPress implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 120;

    public function __construct(public WordpressDelivery $delivery) {}

    public function handle(WordPressPusher $pusher): void
    {
        $this->delivery->update(['status' => 'sending']);

        $pusher->deliver($this->delivery->fresh());
    }
}
