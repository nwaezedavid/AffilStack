<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-page SEO for the public pages that are rendered from a route and
     * a Blade view rather than a SitePage row (home, pricing, help, learn,
     * contact, about, affiliate landing) — keyed by route name so a URL
     * change never orphans the settings.
     */
    public function up(): void
    {
        Schema::create('page_seo', function (Blueprint $table) {
            $table->id();
            $table->string('route_name')->unique();
            $table->string('seo_title')->nullable();
            $table->string('meta_description', 320)->nullable();
            $table->string('focus_keyword')->nullable();
            $table->json('secondary_keywords')->nullable();
            $table->json('tags')->nullable();
            $table->string('canonical_url')->nullable();
            $table->boolean('robots_index')->default(true);
            $table->boolean('robots_follow')->default(true);
            $table->string('og_title')->nullable();
            $table->string('og_description', 320)->nullable();
            $table->string('og_image_path')->nullable();
            $table->string('twitter_title')->nullable();
            $table->string('twitter_description', 320)->nullable();
            $table->string('twitter_image_path')->nullable();
            $table->string('schema_type', 40)->nullable();
            $table->text('custom_schema')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_seo');
    }
};
