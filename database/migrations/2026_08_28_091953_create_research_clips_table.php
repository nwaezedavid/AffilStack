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
        Schema::create('research_clips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('offer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source_url', 2048);
            $table->string('title')->nullable();
            $table->text('selected_text')->nullable();
            $table->string('page_type')->default('other'); // product_page, competitor_ad, linkedin_post, other
            $table->timestamps();

            $table->index(['user_id', 'offer_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('research_clips');
    }
};
