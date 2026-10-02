<?php

use Database\Seeders\AffiliateSwipeSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ready-to-use promo copy for affiliates (email swipes, social posts, text
 * links), admin-editable in Filament. The preset copy is inserted here as
 * well as by the seeder so a production deploy that only runs `migrate`
 * still ships a full Marketing Kit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('affiliate_swipes', function (Blueprint $table) {
            $table->id();
            $table->string('preset_key', 60)->nullable()->unique();
            $table->string('type', 20);
            $table->string('title');
            $table->string('subject')->nullable();
            $table->text('body');
            $table->string('platform', 20)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['type', 'is_active']);
        });

        (new AffiliateSwipeSeeder)->run();
    }

    public function down(): void
    {
        Schema::dropIfExists('affiliate_swipes');
    }
};
