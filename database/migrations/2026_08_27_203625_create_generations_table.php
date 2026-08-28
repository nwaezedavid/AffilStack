<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('generations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('offer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('module'); // research, blog_article, linkedin_keywords, linkedin_dm, linkedin_post, linkedin_article
            $table->json('input')->nullable();
            $table->longText('output')->nullable();
            $table->json('output_meta')->nullable(); // e.g. title/description/tags for structured outputs
            $table->unsignedInteger('credits_spent')->default(0);
            $table->string('status')->default('completed'); // pending, completed, failed
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'module']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('generations');
    }
};
