<?php

use App\Support\Lifecycle\LifecycleEmailPresets;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Installs the preset birthday, festive-season and re-engagement
     * emails (only the keys that don't exist yet, so it never overwrites
     * an admin's edits). `php artisan lifecycle:sync-presets` does the
     * same on demand.
     */
    public function up(): void
    {
        LifecycleEmailPresets::install();
    }

    public function down(): void
    {
        //
    }
};
