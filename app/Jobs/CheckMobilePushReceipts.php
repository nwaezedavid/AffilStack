<?php

namespace App\Jobs;

use App\Models\MobileDevice;
use App\Services\Mobile\Push\ExpoPushClient;
use App\Services\Mobile\Push\MobilePush;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * A quarter of an hour after sending, asks Expo what Apple and Google said
 * about each message. A phone that no longer accepts notifications (app
 * deleted, permission revoked) loses its push token, as Expo asks senders
 * to do.
 */
class CheckMobilePushReceipts implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 2;

    /**
     * @param  array<string, int>  $ticketDevices  Expo ticket id => mobile_devices.id
     */
    public function __construct(public array $ticketDevices) {}

    public function handle(ExpoPushClient $expo, MobilePush $push): void
    {
        foreach ($expo->receipts(array_map('strval', array_keys($this->ticketDevices))) as $ticketId => $receipt) {
            if (($receipt['status'] ?? null) !== 'error' || data_get($receipt, 'details.error') !== 'DeviceNotRegistered') {
                continue;
            }

            $device = MobileDevice::query()->find($this->ticketDevices[$ticketId] ?? null);

            if ($device) {
                $push->forget($device);
            }
        }
    }
}
