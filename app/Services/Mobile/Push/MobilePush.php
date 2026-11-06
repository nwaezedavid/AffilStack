<?php

namespace App\Services\Mobile\Push;

use App\Jobs\CheckMobilePushReceipts;
use App\Jobs\SendMobilePush;
use App\Models\MobileDevice;
use App\Models\MobileIntegrationSetting;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Sends notifications to the customer's signed-in phones. Push is off
 * until it is switched on in admin (Site > App Push & Links); after that
 * every phone that allowed notifications gets the messages the customer
 * hasn't switched off. A phone Apple or Google no longer recognises (app
 * deleted, notifications revoked) loses its push token so it isn't tried
 * again.
 */
class MobilePush
{
    public function __construct(protected ExpoPushClient $expo) {}

    /**
     * Read without creating the settings row: this runs for every
     * dashboard notification.
     */
    public function isEnabled(): bool
    {
        return (bool) MobileIntegrationSetting::query()->orderBy('id')->value('push_enabled');
    }

    /**
     * The phones that can receive notifications for $user.
     *
     * @return Builder<MobileDevice>
     */
    public function devicesFor(User $user): Builder
    {
        return MobileDevice::query()
            ->where('user_id', $user->id)
            ->active()
            ->where('push_provider', 'expo')
            ->whereNotNull('push_token_hash');
    }

    /**
     * Queues $message for $user's phones — a no-op when push is off, the
     * customer switched this kind off or has no phone to send it to.
     */
    public function queue(User $user, PushMessage $message): void
    {
        if (! $this->isEnabled() || ! PushCategories::allows($user, $message->category) || ! $this->devicesFor($user)->exists()) {
            return;
        }

        SendMobilePush::dispatch($user->id, $message->toArray());
    }

    /**
     * Sends $message to $devices now.
     *
     * @param  Collection<int, MobileDevice>  $devices
     * @return array{sent: int, failed: int, errors: array<int, string>}
     */
    public function send(Collection $devices, PushMessage $message): array
    {
        $devices = $devices->filter(fn (MobileDevice $device): bool => filled($device->push_token))->values();

        if ($devices->isEmpty()) {
            return ['sent' => 0, 'failed' => 0, 'errors' => []];
        }

        $tickets = $this->expo->send($devices->map(fn (MobileDevice $device): array => $message->forExpo((string) $device->push_token))->all());

        $result = ['sent' => 0, 'failed' => 0, 'errors' => []];
        $pending = [];

        foreach ($devices as $index => $device) {
            $ticket = $tickets[$index] ?? ['status' => 'error', 'message' => 'No answer from Expo.'];

            if (($ticket['status'] ?? null) === 'ok') {
                $result['sent']++;

                if (is_string($ticket['id'] ?? null)) {
                    $pending[$ticket['id']] = $device->id;
                }

                continue;
            }

            $result['failed']++;
            $result['errors'][] = (string) ($ticket['message'] ?? 'Expo refused the message.');

            if (data_get($ticket, 'details.error') === 'DeviceNotRegistered') {
                $this->forget($device);
            }
        }

        // Apple and Google answer Expo a little later; check then whether
        // any phone has gone away since.
        if ($pending !== []) {
            CheckMobilePushReceipts::dispatch($pending)->delay(now()->addMinutes(15));
        }

        return $result;
    }

    /**
     * "Send a test notification" — from the app's Notifications screen or
     * the admin devices list. Never throws: the answer is shown to a person.
     *
     * @return array{success: bool, message: string}
     */
    public function sendTest(MobileDevice $device): array
    {
        if (! $this->isEnabled()) {
            return ['success' => false, 'message' => 'Phone notifications are switched off for AffilStack right now.'];
        }

        if (! $device->isActive() || $device->push_provider !== 'expo' || blank($device->push_token)) {
            return ['success' => false, 'message' => 'This phone hasn\'t allowed AffilStack notifications yet. Turn them on in the phone\'s settings, then open the app again.'];
        }

        try {
            $result = $this->send(collect([$device]), new PushMessage('test', 'AffilStack notifications work 🎉', 'This is what you\'ll see when content is ready or a buyer replies.', null));
        } catch (Throwable $e) {
            report($e);

            return ['success' => false, 'message' => 'The notification service didn\'t answer. Please try again in a minute.'];
        }

        return $result['sent'] === 1
            ? ['success' => true, 'message' => 'Test notification sent. It should arrive within a few seconds.']
            : ['success' => false, 'message' => 'The phone couldn\'t be reached: '.($result['errors'][0] ?? 'unknown error').'.'];
    }

    public function forget(MobileDevice $device): void
    {
        $device->forceFill(['push_token' => null])->save();
    }
}
