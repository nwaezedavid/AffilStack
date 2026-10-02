<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Daily per-affiliate, per-banner impression counters for the iframe/JS
 * banner embeds — one row per (affiliate, banner, day), never one row per
 * view, and no visitor data at all.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('affiliate_banner_impressions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('banner_key', 80);
            $table->date('day');
            $table->unsignedInteger('impressions')->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'banner_key', 'day']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('affiliate_banner_impressions');
    }
};
