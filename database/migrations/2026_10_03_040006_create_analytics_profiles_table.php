<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Agent 04: the interest & intent profile — one row per signed-in user
     * (merging every device they used) or per still-anonymous visitor,
     * rebuilt on a schedule by AnalyticsProfileBuilder. Only people who
     * allowed analytics cookies get a profile. Advertising segments are
     * WHERE clauses over this table, which keeps their live counts cheap.
     */
    public function up(): void
    {
        Schema::create('analytics_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('visitor_id')->nullable()->unique()->constrained('analytics_visitors')->cascadeOnDelete();
            $table->string('lifecycle_stage', 20)->index();
            $table->unsignedTinyInteger('engagement_score')->default(0)->index();
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_seen_at')->nullable()->index();
            $table->unsignedInteger('sessions_count')->default(0);
            $table->unsignedInteger('pageviews_count')->default(0);
            $table->unsignedInteger('feature_events_30d')->default(0);
            $table->timestamp('viewed_pricing_at')->nullable();
            $table->timestamp('checkout_started_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->boolean('has_active_subscription')->default(false);
            $table->string('country', 2)->nullable();
            $table->string('device_type', 20)->nullable();
            $table->string('ft_channel', 40)->nullable();
            $table->string('ft_source', 100)->nullable();
            $table->string('ft_campaign', 150)->nullable();
            $table->string('lt_channel', 40)->nullable();
            $table->string('lt_source', 100)->nullable();
            $table->string('lt_campaign', 150)->nullable();
            $table->json('interests')->nullable();
            $table->boolean('marketing_consent')->default(false);
            $table->timestamp('computed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_profiles');
    }
};
