<?php

namespace App\Services\Video;

use App\Models\HeyGenSetting;
use Illuminate\Support\Facades\Cache;

/**
 * HeyGen's stock avatars and voices for the UGC "choose your character"
 * step, cached for six hours (both lists change rarely and every page view
 * would otherwise cost two HeyGen API calls). Every method degrades to an
 * empty list when HeyGen isn't connected or the call fails, so the UGC page
 * keeps working for script generation either way.
 */
class HeyGenCatalog
{
    public function isReady(): bool
    {
        return HeyGenSetting::current()->isReady();
    }

    /**
     * @return array<int, array{id: string, name: string, gender: ?string, preview_image_url: ?string}>
     */
    public function avatars(): array
    {
        return $this->remember('heygen:avatars', fn (HeyGenClient $client) => $client->listAvatars());
    }

    /**
     * @return array<int, array{id: string, name: string, language: ?string, gender: ?string}>
     */
    public function voices(): array
    {
        return $this->remember('heygen:voices', fn (HeyGenClient $client) => $client->listVoices());
    }

    /**
     * @param  callable(HeyGenClient): array<int, array<string, mixed>>  $load
     * @return array<int, array<string, mixed>>
     */
    protected function remember(string $key, callable $load): array
    {
        if (! $this->isReady()) {
            return [];
        }

        try {
            return Cache::remember($key, now()->addHours(6), fn () => $load(new HeyGenClient(
                (string) HeyGenSetting::current()->credential('api_key'),
            )));
        } catch (VideoGenerationException) {
            return [];
        }
    }
}
