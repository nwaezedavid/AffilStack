<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Agent 04: one row per anonymous visitor id (the afs_vid cookie, only
     * ever set after analytics consent). Holds first- and last-touch
     * attribution and the device/location seen on the first visit. user_id
     * is filled in once the visitor logs in or completes a purchase
     * (identity stitching). email_sha256 (and its Google-normalised twin) is a
     * SHA-256 of the email typed at checkout — never the raw address.
     */
    public function up(): void
    {
        Schema::create('analytics_visitors', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->char('email_sha256', 64)->nullable()->index();
            // Google Customer Match also drops dots from gmail.com local
            // parts before hashing, so its hash can differ from Meta's.
            $table->char('email_sha256_google', 64)->nullable();
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_seen_at')->nullable()->index();
            $table->timestamp('identified_at')->nullable();

            foreach (['ft', 'lt'] as $touch) {
                $table->string("{$touch}_channel", 40)->nullable();
                $table->string("{$touch}_source", 100)->nullable();
                $table->string("{$touch}_medium", 100)->nullable();
                $table->string("{$touch}_campaign", 150)->nullable();
                $table->string("{$touch}_term", 150)->nullable();
                $table->string("{$touch}_content", 150)->nullable();
                $table->string("{$touch}_referrer_host", 255)->nullable();
                $table->string("{$touch}_landing_path", 200)->nullable();
                $table->string("{$touch}_click_id_type", 10)->nullable();
                // Advertising click ids (gclid/fbclid/ttclid) are kept only
                // when the visitor also allowed marketing cookies.
                $table->string("{$touch}_click_id", 255)->nullable();
                $table->timestamp("{$touch}_at")->nullable();
            }

            $table->string('device_type', 20)->nullable();
            $table->string('browser', 40)->nullable();
            $table->string('os', 40)->nullable();
            $table->string('language', 20)->nullable();
            $table->string('country', 2)->nullable();
            $table->string('city', 100)->nullable();

            $table->unsignedInteger('sessions_count')->default(0);
            $table->unsignedInteger('pageviews_count')->default(0);
            $table->unsignedInteger('events_count')->default(0);

            $table->index(['user_id', 'last_seen_at']);
            $table->index('ft_channel');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_visitors');
    }
};
