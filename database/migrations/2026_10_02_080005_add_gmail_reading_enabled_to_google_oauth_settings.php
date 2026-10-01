<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Admin switch for Gmail API inbox reading (gmail.readonly — a restricted
 * scope). Off until Google's CASA assessment is approved; IMAP reply
 * detection works for everyone in the meantime.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('google_oauth_settings', function (Blueprint $table) {
            $table->boolean('gmail_reading_enabled')->default(false)->after('gmail_sending_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('google_oauth_settings', function (Blueprint $table) {
            $table->dropColumn('gmail_reading_enabled');
        });
    }
};
