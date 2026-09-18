<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Splits the single "logo" upload into two admin-managed variants
 * (rectangular for the header/login/signup, square for the favicon and
 * anywhere a square/circle mark fits better — see BrandSettings). Existing
 * installs already have a logo_path/favicon_path row in site_settings; this
 * copies those into the new keys once so nothing already uploaded is lost.
 * A fresh install has neither row yet, so this is a no-op for it.
 */
return new class extends Migration
{
    public function up(): void
    {
        $logo = DB::table('site_settings')->where('key', 'logo_path')->value('value');
        $favicon = DB::table('site_settings')->where('key', 'favicon_path')->value('value');

        if ($logo !== null && ! DB::table('site_settings')->where('key', 'logo_rectangular_path')->exists()) {
            DB::table('site_settings')->insert([
                'key' => 'logo_rectangular_path',
                'value' => $logo,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        if ($favicon !== null && ! DB::table('site_settings')->where('key', 'logo_square_path')->exists()) {
            DB::table('site_settings')->insert([
                'key' => 'logo_square_path',
                'value' => $favicon,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('site_settings')->whereIn('key', ['logo_rectangular_path', 'logo_square_path'])->delete();
    }
};
