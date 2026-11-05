<?php

namespace App\Http\Resources\Mobile;

use App\Models\MobileDevice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A signed-in phone, as listed on the app's "Signed-in devices" screen.
 * Never includes refresh or push tokens.
 *
 * @mixin MobileDevice
 */
class MobileDeviceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'platform' => $this->platform,
            'app_version' => $this->app_version,
            'os_version' => $this->os_version,
            'has_push' => $this->push_token !== null,
            'is_current' => $request->attributes->get('mobileDevice')?->id === $this->id,
            'signed_in_at' => $this->authenticated_at?->toIso8601String(),
            'last_seen_at' => $this->last_seen_at?->toIso8601String(),
        ];
    }
}
