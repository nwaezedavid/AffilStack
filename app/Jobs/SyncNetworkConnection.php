<?php

namespace App\Jobs;

use App\Models\NetworkConnection;
use App\Services\Networks\NetworkException;
use App\Services\Networks\NetworkSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Syncs one affiliate network connection (programs and commissions). A
 * failure is recorded on the connection for the customer to see; the
 * next daily run tries again, so this never retries by itself. (The
 * model isn't called $connection: Queueable uses that name for the queue
 * connection.)
 */
class SyncNetworkConnection implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 600;

    public int $uniqueFor = 900;

    public function __construct(public NetworkConnection $networkConnection) {}

    public function uniqueId(): string
    {
        return (string) $this->networkConnection->id;
    }

    public function handle(NetworkSyncService $sync): void
    {
        $connection = $this->networkConnection->fresh();

        if (! $connection || ! $connection->isActive()) {
            return;
        }

        try {
            $sync->sync($connection);
        } catch (NetworkException) {
            // Already recorded on the connection by the sync service.
        }
    }
}
