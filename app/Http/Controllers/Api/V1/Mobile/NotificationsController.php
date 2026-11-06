<?php

namespace App\Http\Controllers\Api\V1\Mobile;

use App\Http\Controllers\Controller;
use App\Models\MobileDevice;
use App\Services\Mobile\Push\MobilePush;
use App\Services\Mobile\Push\PushCategories;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * More > Notifications in the phone app: which kinds of notification the
 * customer wants (for all their phones), and a test notification to the
 * phone in their hand. The phone's push token itself is sent with
 * PUT /v1/mobile/devices/current.
 */
class NotificationsController extends Controller
{
    public function __construct(protected MobilePush $push) {}

    public function show(Request $request): JsonResponse
    {
        return $this->preferences($request);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'categories' => ['required', 'array', function (string $attribute, mixed $value, Closure $fail): void {
                $unknown = array_diff(array_keys((array) $value), array_keys(PushCategories::ALL));

                if ($unknown !== []) {
                    $fail('Unknown notification type: '.implode(', ', $unknown).'.');
                }
            }],
            'categories.*' => ['boolean'],
        ]);

        PushCategories::update($request->user(), $validated['categories']);

        return $this->preferences($request);
    }

    public function test(Request $request): JsonResponse
    {
        /** @var MobileDevice $device */
        $device = $request->attributes->get('mobileDevice');

        $result = $this->push->sendTest($device->refresh());

        return response()->json(['message' => $result['message']], $result['success'] ? 200 : 422);
    }

    protected function preferences(Request $request): JsonResponse
    {
        /** @var MobileDevice $device */
        $device = $request->attributes->get('mobileDevice');

        return response()->json([
            'data' => [
                'push_available' => $this->push->isEnabled(),
                'this_device_registered' => $device->push_provider === 'expo' && $device->push_token_hash !== null,
                'categories' => PushCategories::forUser($request->user()->refresh()),
            ],
        ]);
    }
}
