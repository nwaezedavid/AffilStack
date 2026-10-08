<?php

use App\Support\Features\FeatureCatalog;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Ships the default feature pages with the release (production deploys
     * run `migrate`, not `db:seed`). Only inserts slugs that don't exist,
     * so it never overwrites an admin's edits.
     */
    public function up(): void
    {
        FeatureCatalog::seedMissing();
    }

    public function down(): void
    {
        //
    }
};
