<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Admin-uploaded banner creatives (PNG/JPG/GIF) shown in every affiliate's
 * Marketing Kit ahead of the built-in designs. See AffiliateBannerResource.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('affiliate_banners', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('size', 20);
            $table->string('image_path');
            $table->string('alt_text')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'size']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('affiliate_banners');
    }
};
