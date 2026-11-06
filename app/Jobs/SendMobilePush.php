<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\Mobile\Push\MobilePush;
use App\Services\Mobile\Push\PushCategories;
use App\Services\Mobile\Push\PushMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Delivers one notification to a customer's phones. Re-checks everything
 * when it runs (push still on, the customer still wants this kind, the
 * account still active), because it may wait in the queue for a while.
 */
class SendMobilePush implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    /**
     * @var array<int, int>
     */
    public array $backoff = [30, 120];

    /**
     * @param  array{category: string, title: string, body: string, url?: string|null}  $message
     */
    public function __construct(public int $userId, public array $message) {}

    public function handle(MobilePush $push): void
    {
        $user = User::query()->find($this->userId);
        $message = PushMessage::fromArray($this->message);

        if (! $user || $user->is_suspended || ! $push->isEnabled() || ! PushCategories::allows($user, $message->category)) {
            return;
        }

        $push->send($push->devicesFor($user)->get(), $message);
    }
}
