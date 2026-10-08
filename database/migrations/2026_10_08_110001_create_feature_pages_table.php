<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One public marketing page per product feature (/features/{slug}),
     * also the source of the header's "Tools" mega-menu. The copy is
     * seeded from App\Support\Features\FeatureCatalog by the next
     * migration; every field stays editable in Website → Features.
     */
    public function up(): void
    {
        Schema::create('feature_pages', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('icon')->default('sparkles');
            $table->string('menu_group')->default('grow');
            $table->string('menu_description')->nullable();
            $table->string('hero_eyebrow')->nullable();
            $table->string('hero_headline');
            $table->text('hero_subheadline')->nullable();
            $table->string('hero_image_path')->nullable();
            $table->string('hero_default_image')->nullable();
            $table->string('hero_image_alt')->nullable();
            $table->string('hero_video_url')->nullable();
            $table->json('sections')->nullable();
            $table->json('steps')->nullable();
            $table->json('faqs')->nullable();
            $table->string('cta_heading')->nullable();
            $table->string('cta_text')->nullable();
            $table->string('cta_label')->nullable();
            $table->string('cta_url')->nullable();
            $table->string('seo_title')->nullable();
            $table->string('meta_description', 320)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('show_in_menu')->default(true);
            $table->boolean('is_published')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feature_pages');
    }
};
