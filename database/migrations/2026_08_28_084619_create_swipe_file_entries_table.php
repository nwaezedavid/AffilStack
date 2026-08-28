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
        Schema::create('swipe_file_entries', function (Blueprint $table) {
            $table->id();
            $table->string('type'); // hook, subject_line, thumbnail_style
            $table->string('niche');
            $table->string('title');
            $table->text('content'); // the actual hook/subject line text, or a thumbnail style description
            $table->text('notes')->nullable(); // why it works
            $table->json('tags')->nullable();
            $table->boolean('is_published')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['type', 'niche']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('swipe_file_entries');
    }
};
