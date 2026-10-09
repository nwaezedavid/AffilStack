<?php

namespace App\Support;

use App\Models\SiteSetting;
use Illuminate\Support\Facades\Storage;

/**
 * The site's logo files (Admin → Site → Brand Settings). Light areas use
 * the normal colour logo; dark areas (site footer, dashboard sidebar, the
 * admin panel in dark mode, the Chrome extension header) use the white
 * versions when they have been uploaded.
 */
class Brand
{
    public static function name(): string
    {
        return (string) SiteSetting::get('site_name', 'AffilStack');
    }

    public static function logoUrl(): ?string
    {
        return self::url('logo_rectangular_path');
    }

    public static function iconUrl(): ?string
    {
        return self::url('logo_square_path');
    }

    public static function whiteLogoUrl(): ?string
    {
        return self::url('logo_rectangular_white_path');
    }

    public static function whiteIconUrl(): ?string
    {
        return self::url('logo_square_white_path');
    }

    protected static function url(string $setting): ?string
    {
        $path = SiteSetting::get($setting);

        return is_string($path) && $path !== '' ? Storage::disk('public')->url($path) : null;
    }
}
