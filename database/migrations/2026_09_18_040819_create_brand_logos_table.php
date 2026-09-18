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
        // "That design that popular websites use to show brands they've
        // worked with (it's constantly moving in a loop)" — an
        // admin-managed, unlimited list of client/partner logos for the
        // homepage marquee. See BrandLogo::activePublicList() and the
        // marketing/home.blade.php section that renders the loop.
        Schema::create('brand_logos', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('logo_path');
            $table->string('url')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('brand_logos');
    }
};
