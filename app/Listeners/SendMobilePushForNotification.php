<?php

namespace App\Listeners;

use App\Models\User;
use App\Services\Mobile\Push\MobilePush;
use App\Services\Mobile\Push\PushMessageFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Events\NotificationSent;
use Throwable;

/**
 * Every time one of AffilStack's notifications lands in a customer's
 * dashboard bell, the same news can go to their phone (see
 * PushMessageFactory for which ones and what they say). Hooked onto the
 * "database" channel so no existing notification class had to change, and
 * it never throws: a phone problem must not break the notification, the
 * job or the request that sent it.
 *
 * Registered by Laravel's listener discovery (like RecordUserLogin) — do
 * not also add an Event::listen() for it, or every push is sent twice.
 */
class SendMobilePushForNotification
{
    public function __construct(protected MobilePush $push, protected PushMessageFactory $messages) {}

    public function handle(NotificationSent $event): void
    {
        if ($event->channel !== 'database' || ! $event->notifiable instanceof User) {
            return;
        }

        try {
            $data = $event->response instanceof Model && is_array($event->response->getAttribute('data'))
                ? $event->response->getAttribute('data')
                : (array) $event->notification->toArray($event->notifiable);

            $message = $this->messages->make($event->notification, $data);

            if ($message) {
                $this->push->queue($event->notifiable, $message);
            }
        } catch (Throwable $e) {
            report($e);
        }
    }
}
