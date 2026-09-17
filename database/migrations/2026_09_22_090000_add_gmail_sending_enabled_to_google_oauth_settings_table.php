<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Task #1 (Gmail/SMTP for CRM nurture sending): the existing "Continue
     * with Google" OAuth client (GoogleOauthSetting) can also power a
     * per-user "Connect Gmail" flow for a separate, sensitive scope
     * (gmail.send) — same client id/secret, just a different consent
     * screen and redirect. This is a distinct admin toggle rather than
     * reusing is_enabled because a real Google Cloud OAuth consent screen
     * has to pass Google's security assessment before gmail.send actually
     * works for anyone outside test users — an admin may have login
     * working long before that's true.
     */
    public function up(): void
    {
        Schema::table('google_oauth_settings', function (Blueprint $table) {
            $table->boolean('gmail_sending_enabled')->default(false)->after('is_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('google_oauth_settings', function (Blueprint $table) {
            $table->dropColumn('gmail_sending_enabled');
        });
    }
};
