<?php

namespace App\Http\Controllers\Api\V1\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Resources\Mobile\MobileDeviceResource;
use App\Models\MobileDevice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/**
 * The signed-in phone itself: what it reports about itself (app version,
 * language, push token for notifications), the list of the customer's
 * signed-in phones, and signing one out.
 */
class DevicesController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        return MobileDeviceResource::collection(
            MobileDevice::query()->where('user_id', $request->user()->id)->active()->latest('last_seen_at')->get()
        );
    }

    public function updateCurrent(Request $request): MobileDeviceResource
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'nullable', 'string', 'max:120'],
            'platform' => ['sometimes', Rule::in(MobileDevice::PLATFORMS)],
            'app_version' => ['sometimes', 'nullable', 'string', 'max:32'],
            'os_version' => ['sometimes', 'nullable', 'string', 'max:32'],
            'locale' => ['sometimes', 'nullable', 'string', 'max:12'],
            'push_provider' => ['sometimes', 'nullable', Rule::in(['expo', 'fcm', 'apns']), 'required_with:push_token'],
            'push_token' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        /** @var MobileDevice $device */
        $device = $request->attributes->get('mobileDevice');

        // A push token belongs to one phone: if it moved to this account
        // (someone else signed out and you signed in), the old row must
        // stop receiving this account's notifications.
        if (! empty($validated['push_token'])) {
            MobileDevice::releasePushToken($validated['push_token'], except: $device);
        }

        $device->forceFill($validated)->save();

        return new MobileDeviceResource($device);
    }

    public function destroy(Request $request, MobileDevice $device): JsonResponse
    {
        abort_unless($device->user_id === $request->user()->id && $device->isActive(), 404);

        $device->revoke();

        return response()->json(['message' => 'That device has been signed out.']);
    }
}
