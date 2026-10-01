<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reply detection (IMAP, or the Gmail API once Google approves the
 * gmail.readonly scope), the user's own daily sending cap, and the
 * compliance footer (business name + postal address) every campaign email
 * carries.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('email_connections', function (Blueprint $table) {
            $table->string('preset', 40)->nullable()->after('provider');
            $table->timestamp('last_checked_at')->nullable()->after('verification_message');
            $table->unsignedInteger('daily_limit')->nullable()->after('last_checked_at');
            $table->string('footer_business_name')->nullable()->after('daily_limit');
            $table->string('footer_address', 500)->nullable()->after('footer_business_name');

            $table->text('imap_credentials')->nullable()->after('footer_address'); // encrypted:array
            $table->string('imap_status', 20)->nullable()->after('imap_credentials'); // success, failed
            $table->text('imap_message')->nullable()->after('imap_status');
            $table->timestamp('imap_verified_at')->nullable()->after('imap_message');
            $table->unsignedBigInteger('imap_uidvalidity')->nullable()->after('imap_verified_at');
            $table->unsignedBigInteger('imap_last_uid')->nullable()->after('imap_uidvalidity');
            $table->timestamp('imap_last_polled_at')->nullable()->after('imap_last_uid');

            $table->boolean('gmail_read_granted')->default(false)->after('imap_last_polled_at');
            $table->string('gmail_history_id')->nullable()->after('gmail_read_granted');
            $table->timestamp('gmail_last_polled_at')->nullable()->after('gmail_history_id');
        });
    }

    public function down(): void
    {
        Schema::table('email_connections', function (Blueprint $table) {
            $table->dropColumn([
                'preset', 'last_checked_at', 'daily_limit', 'footer_business_name', 'footer_address',
                'imap_credentials', 'imap_status', 'imap_message', 'imap_verified_at', 'imap_uidvalidity',
                'imap_last_uid', 'imap_last_polled_at', 'gmail_read_granted', 'gmail_history_id', 'gmail_last_polled_at',
            ]);
        });
    }
};
