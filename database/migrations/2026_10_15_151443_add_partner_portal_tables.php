<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The Partner Portal on affiliate.affilstack.com: labelled campaign links
 * (and the attribution columns that carry a campaign from click to
 * commission), admin announcements, auto-updating banner ads, leaderboard
 * visibility, and the amount actually sent when a payout was converted
 * (USD commission paid into a naira bank account).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('show_on_partner_leaderboard')->default(true);
            $table->timestamp('partner_news_seen_at')->nullable();
        });

        Schema::create('referral_campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug', 60);
            $table->string('channel', 30);
            $table->string('landing_path')->default('/pricing');
            $table->timestamps();

            $table->unique(['user_id', 'slug']);
        });

        Schema::table('referral_clicks', function (Blueprint $table) {
            $table->foreignId('referral_campaign_id')->nullable()->constrained()->nullOnDelete();
        });

        Schema::table('pending_signups', function (Blueprint $table) {
            $table->foreignId('referral_campaign_id')->nullable()->constrained()->nullOnDelete();
        });

        Schema::table('referrals', function (Blueprint $table) {
            $table->foreignId('referral_campaign_id')->nullable()->constrained()->nullOnDelete();
        });

        Schema::table('referral_payouts', function (Blueprint $table) {
            $table->bigInteger('sent_amount_cents')->nullable();
            $table->string('sent_currency', 3)->nullable();
        });

        Schema::create('partner_announcements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->text('body');
            $table->boolean('is_pinned')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->timestamp('emailed_at')->nullable();
            $table->timestamps();

            $table->index('published_at');
        });

        Schema::create('banner_ads', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('headline', 80);
            $table->string('message', 160)->nullable();
            $table->string('cta_label', 30)->default('Get started');
            $table->string('discount_badge', 30)->nullable();
            $table->string('discount_note', 80)->nullable();
            $table->string('image_path')->nullable();
            $table->string('theme', 20)->default('navy');
            $table->string('landing_path')->default('/pricing');
            $table->json('sizes')->nullable();
            $table->unsignedTinyInteger('weight')->default(5);
            $table->boolean('is_active')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('banner_ads');
        Schema::dropIfExists('partner_announcements');

        Schema::table('referral_payouts', function (Blueprint $table) {
            $table->dropColumn(['sent_amount_cents', 'sent_currency']);
        });

        foreach (['referrals', 'pending_signups', 'referral_clicks'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropConstrainedForeignId('referral_campaign_id');
            });
        }

        Schema::dropIfExists('referral_campaigns');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['show_on_partner_leaderboard', 'partner_news_seen_at']);
        });
    }
};
