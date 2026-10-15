<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Posts written in AffilStack's admin and sent to the WordPress blog
     * (Admin → Website → Blog posts). The wp_* columns remember the copy on
     * WordPress so later edits update it instead of creating a new post.
     */
    public function up(): void
    {
        Schema::create('blog_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->string('slug')->nullable();
            $table->text('excerpt')->nullable();
            $table->longText('content')->nullable();
            $table->string('featured_image_path')->nullable();
            $table->json('categories')->nullable();
            $table->json('tags')->nullable();
            $table->string('focus_keyword')->nullable();
            $table->string('seo_title')->nullable();
            $table->string('seo_description', 500)->nullable();
            $table->unsignedBigInteger('wp_post_id')->nullable();
            $table->string('wp_status')->nullable();
            $table->string('wp_url', 2048)->nullable();
            $table->unsignedBigInteger('wp_featured_media_id')->nullable();
            $table->string('wp_featured_image_path')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_posts');
    }
};
