<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_funding_settings', function (Blueprint $table) {
            $table->id();

            // HeyGen — the one integration here with a real, live-checkable
            // balance endpoint (GET /v2/user/remaining_quota). See
            // HeyGenClient::checkBalance().
            $table->unsignedInteger('heygen_low_balance_threshold')->default(100);
            $table->unsignedInteger('heygen_balance_value')->nullable();
            $table->timestamp('heygen_balance_checked_at')->nullable();
            $table->timestamp('heygen_low_balance_notified_at')->nullable();

            // Anthropic (Brain's copywriting) and Meta ad spend (Brain's
            // live campaigns) have no API a stored key alone can query for
            // a balance — see FundingHealthChecker's docblock — so these
            // are manual reminder cadences an admin resets after topping up.
            $table->unsignedInteger('anthropic_reminder_days')->default(14);
            $table->timestamp('anthropic_reminder_last_acknowledged_at')->nullable();
            $table->timestamp('anthropic_reminder_notified_at')->nullable();
            $table->unsignedInteger('meta_ads_reminder_days')->default(14);
            $table->timestamp('meta_ads_reminder_last_acknowledged_at')->nullable();
            $table->timestamp('meta_ads_reminder_notified_at')->nullable();

            // Payout wallet (App\Services\Referrals\PayoutWalletService) —
            // needs no external API at all, since its balance is always the
            // sum of WalletTransaction; a per-currency low-balance
            // threshold, and which currencies are currently below it (so a
            // drop below is notified once, not on every check).
            $table->json('wallet_thresholds_cents')->nullable();
            $table->json('wallet_currencies_notified')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_funding_settings');
    }
};
