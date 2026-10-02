<?php

namespace App\Models;

use Database\Factories\AffiliateBannerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * An admin-uploaded banner creative (Billing → Affiliate Banners). Shown in
 * every affiliate's Marketing Kit ahead of the built-in designs, and
 * preferred by the rotating iframe/JS embed for its size. Its banner key —
 * what the click/impression stats are grouped by — is "upload{id}-{size}".
 */
#[Fillable(['title', 'size', 'image_path', 'alt_text', 'is_active', 'sort_order'])]
class AffiliateBanner extends Model
{
    /** @use HasFactory<AffiliateBannerFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function bannerKey(): string
    {
        return 'upload'.$this->id.'-'.$this->size;
    }

    public function imageUrl(): string
    {
        return Storage::disk('public')->url($this->image_path);
    }

    public function width(): int
    {
        return (int) config("affiliate_kit.sizes.{$this->size}.width", 300);
    }

    public function height(): int
    {
        return (int) config("affiliate_kit.sizes.{$this->size}.height", 250);
    }
}
