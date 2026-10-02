<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which marketing-kit creative a referral click came from ("b" on /r/{code},
 * e.g. "commissions-300x250") and which channel (utm_medium: banner, email,
 * social, text_link) — so the Marketing Kit can show per-banner clicks.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('referral_clicks', function (Blueprint $table) {
            $table->string('banner_key', 80)->nullable()->after('user_agent');
            $table->string('source', 20)->nullable()->after('banner_key');
            $table->index(['user_id', 'banner_key']);
        });
    }

    public function down(): void
    {
        Schema::table('referral_clicks', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'banner_key']);
            $table->dropColumn(['banner_key', 'source']);
        });
    }
};
