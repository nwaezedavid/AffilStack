<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Brings SitePage up to the same SEO controls as the route-based pages
     * in page_seo: secondary keywords, tags, canonical override, nofollow,
     * separate Open Graph / X card copy and image, schema type and a custom
     * JSON-LD block.
     */
    public function up(): void
    {
        Schema::table('site_pages', function (Blueprint $table) {
            $table->json('secondary_keywords')->nullable()->after('focus_keyword');
            $table->json('tags')->nullable()->after('secondary_keywords');
            $table->string('canonical_url')->nullable()->after('tags');
            $table->boolean('no_follow')->default(false)->after('no_index');
            $table->string('og_title')->nullable()->after('canonical_url');
            $table->string('og_description', 320)->nullable()->after('og_title');
            $table->string('twitter_title')->nullable()->after('og_description');
            $table->string('twitter_description', 320)->nullable()->after('twitter_title');
            $table->string('twitter_image_path')->nullable()->after('twitter_description');
            $table->string('schema_type', 40)->nullable()->after('twitter_image_path');
            $table->text('custom_schema')->nullable()->after('schema_type');
        });
    }

    public function down(): void
    {
        Schema::table('site_pages', function (Blueprint $table) {
            $table->dropColumn([
                'secondary_keywords', 'tags', 'canonical_url', 'no_follow', 'og_title', 'og_description',
                'twitter_title', 'twitter_description', 'twitter_image_path', 'schema_type', 'custom_schema',
            ]);
        });
    }
};
