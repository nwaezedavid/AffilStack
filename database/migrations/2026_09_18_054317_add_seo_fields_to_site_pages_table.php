<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('site_pages', function (Blueprint $table) {
            // Falls back to `title` when blank — lets an admin write a
            // punchier <title>/OG title without changing the on-page H1.
            $table->string('seo_title')->nullable()->after('title');
            $table->string('og_image_path')->nullable()->after('meta_description');
            // Not rendered anywhere — purely drives the on-page SEO
            // checklist shown on the edit form (title/description length,
            // whether the keyword actually appears).
            $table->string('focus_keyword')->nullable()->after('og_image_path');
            $table->boolean('no_index')->default(false)->after('is_published');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('site_pages', function (Blueprint $table) {
            $table->dropColumn(['seo_title', 'og_image_path', 'focus_keyword', 'no_index']);
        });
    }
};
