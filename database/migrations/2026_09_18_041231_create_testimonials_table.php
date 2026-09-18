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
        // "The testimonial section will appear as soon as I have a minimum
        // of 3 updated in the admin dashboard area." See
        // Testimonial::published() for that 3-minimum gate and
        // marketing/home.blade.php for the section it drives.
        Schema::create('testimonials', function (Blueprint $table) {
            $table->id();
            $table->string('author_name');
            $table->string('author_role')->nullable(); // e.g. "Affiliate marketer" or a company name
            $table->string('avatar_path')->nullable();
            $table->text('quote');
            $table->unsignedTinyInteger('rating')->nullable(); // 1-5 stars, optional
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_published')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('testimonials');
    }
};
