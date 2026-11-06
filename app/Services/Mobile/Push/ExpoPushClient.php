<?php

namespace App\Services\Mobile\Push;

use App\Models\MobileIntegrationSetting;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Expo's push service, which hands each message to Apple (APNs) or Google
 * (FCM) for us — raw Http calls, no new Composer dependency. The access
 * token is optional: it is only needed once "Enhanced push security" is
 * switched on in the Expo account, and comes from admin (Site > App Push &
 * Links), encrypted at rest.
 */
class ExpoPushClient
{
    public const SEND_ENDPOINT = 'https://exp.host/--/api/v2/push/send';

    public const RECEIPTS_ENDPOINT = 'https://exp.host/--/api/v2/push/getReceipts';

    /** Expo accepts at most 100 messages per request. */
    public const BATCH_SIZE = 100;

    /**
     * Sends the messages and returns Expo's "ticket" for each, in the same
     * order: ['status' => 'ok', 'id' => ...] or ['status' => 'error',
     * 'message' => ..., 'details' => ['error' => 'DeviceNotRegistered']].
     *
     * @param  array<int, array<string, mixed>>  $messages
     * @return array<int, array<string, mixed>>
     */
    public function send(array $messages): array
    {
        $tickets = [];

        foreach (array_chunk($messages, self::BATCH_SIZE) as $batch) {
            $response = $this->request()->post(self::SEND_ENDPOINT, $batch);

            if ($response->failed() || ! is_array($response->json('data'))) {
                throw new RuntimeException('Expo push request failed ('.$response->status().'): '.mb_substr($response->body(), 0, 300));
            }

            foreach (array_values($response->json('data')) as $ticket) {
                $tickets[] = is_array($ticket) ? $ticket : ['status' => 'error', 'message' => 'Unexpected answer from Expo.'];
            }
        }

        return $tickets;
    }

    /**
     * Expo's delivery receipts for earlier tickets, keyed by ticket id.
     *
     * @param  array<int, string>  $ticketIds
     * @return array<string, array<string, mixed>>
     */
    public function receipts(array $ticketIds): array
    {
        $receipts = [];

        foreach (array_chunk($ticketIds, 1000) as $batch) {
            $response = $this->request()->post(self::RECEIPTS_ENDPOINT, ['ids' => $batch]);

            if ($response->failed()) {
                throw new RuntimeException('Expo receipts request failed ('.$response->status().').');
            }

            foreach ((array) $response->json('data', []) as $id => $receipt) {
                if (is_array($receipt)) {
                    $receipts[(string) $id] = $receipt;
                }
            }
        }

        return $receipts;
    }

    protected function request(): PendingRequest
    {
        $request = Http::acceptJson()->asJson()->timeout(15)->retry(2, 500, throw: false);
        $token = trim((string) MobileIntegrationSetting::query()->orderBy('id')->first()?->credential('expo_access_token'));

        return $token !== '' ? $request->withToken($token) : $request;
    }
}
