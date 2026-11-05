<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * While Google reviews the Gmail and YouTube scopes, the admin can limit
 * "Connect Gmail" / "Connect YouTube" to a few test accounts, so real
 * customers never hit the unverified-app warning or use up the 100-user cap.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('google_oauth_settings', function (Blueprint $table) {
            $table->boolean('limit_to_review_testers')->default(false)->after('youtube_approval_notes');
            $table->text('review_testers')->nullable()->after('limit_to_review_testers');
        });
    }

    public function down(): void
    {
        Schema::table('google_oauth_settings', function (Blueprint $table) {
            $table->dropColumn(['limit_to_review_testers', 'review_testers']);
        });
    }
};
