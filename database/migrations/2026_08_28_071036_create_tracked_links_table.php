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
        Schema::create('tracked_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('offer_id')->constrained()->cascadeOnDelete();
            // Which content piece this link is embedded in — e.g. "blog_article",
            // "linkedin_post", "youtube_script" — so click analytics can be broken
            // down per channel later (feature 2, the conversion & earnings tracker).
            // "general" covers a link created outside any specific generation.
            $table->string('module')->default('general');
            $table->string('code', 16)->unique();
            $table->text('destination_url');
            $table->unsignedInteger('clicks_count')->default(0);
            $table->timestamps();

            // One link per offer per content channel — regenerating the same
            // module's content reuses its existing link instead of fragmenting
            // click history across a new one every time.
            $table->unique(['offer_id', 'module']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tracked_links');
    }
};
