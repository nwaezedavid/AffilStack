<?php

namespace App\Services\Video;

use App\Models\Offer;
use App\Support\GuardedHttp;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * The product picture Higgsfield builds a clip around. A customer upload
 * wins; otherwise AffilStack reads the product page's share image
 * (og:image / twitter:image) and keeps its own copy, so Higgsfield always
 * fetches a known-good image from AffilStack's public storage. The result
 * is remembered on the product (research_data.product_image_path).
 */
class ProductImageResolver
{
    public const DIRECTORY = 'ugc-product-images';

    public const MAX_BYTES = 8 * 1024 * 1024;

    protected const TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

    public function storeUpload(Offer $offer, UploadedFile $file): string
    {
        $path = $file->storeAs(self::DIRECTORY, $offer->id.'-'.Str::random(24).'.'.($file->extension() ?: 'jpg'), 'public');
        $this->remember($offer, $path);

        return $path;
    }

    /**
     * The stored path of the product's picture, finding one on the product
     * page when none is saved yet. Null when there's no usable picture.
     */
    public function resolve(Offer $offer): ?string
    {
        $saved = $offer->research_data['product_image_path'] ?? null;

        if (is_string($saved) && Storage::disk('public')->exists($saved)) {
            return $saved;
        }

        $imageUrl = $this->findOnPage((string) $offer->product_url);

        if ($imageUrl === null) {
            return null;
        }

        $path = $this->download($imageUrl, $offer->id);

        if ($path !== null) {
            $this->remember($offer, $path);
        }

        return $path;
    }

    public function publicUrl(string $path): string
    {
        return Storage::disk('public')->url($path);
    }

    public function findOnPage(string $pageUrl): ?string
    {
        if ($pageUrl === '') {
            return null;
        }

        $response = GuardedHttp::get($pageUrl);

        if ($response === null) {
            return null;
        }

        $html = substr($response->body(), 0, 800_000);

        foreach (['og:image:secure_url', 'og:image', 'twitter:image', 'twitter:image:src'] as $property) {
            $pattern = '/<meta[^>]+(?:property|name)=["\']'.preg_quote($property, '/').'["\'][^>]*content=["\']([^"\']+)["\']/i';
            $reverse = '/<meta[^>]+content=["\']([^"\']+)["\'][^>]*(?:property|name)=["\']'.preg_quote($property, '/').'["\']/i';

            if (preg_match($pattern, $html, $match) || preg_match($reverse, $html, $match)) {
                return GuardedHttp::absolute($pageUrl, html_entity_decode(trim($match[1]), ENT_QUOTES));
            }
        }

        return null;
    }

    protected function download(string $imageUrl, int $offerId): ?string
    {
        $response = GuardedHttp::get($imageUrl, 15);

        if ($response === null || strlen($response->body()) > self::MAX_BYTES || strlen($response->body()) < 1024) {
            return null;
        }

        $type = strtolower(trim(explode(';', (string) $response->header('Content-Type'))[0]));
        $extension = self::TYPES[$type] ?? null;

        // Trust the bytes, not just the header.
        $info = @getimagesizefromstring($response->body());

        if ($extension === null || $info === false || ($info[0] ?? 0) < 200 || ($info[1] ?? 0) < 200) {
            return null;
        }

        $path = self::DIRECTORY.'/'.$offerId.'-'.Str::random(24).'.'.$extension;
        Storage::disk('public')->put($path, $response->body());

        return $path;
    }

    protected function remember(Offer $offer, string $path): void
    {
        $offer->forceFill(['research_data' => array_merge((array) $offer->research_data, ['product_image_path' => $path])])->save();
    }
}
