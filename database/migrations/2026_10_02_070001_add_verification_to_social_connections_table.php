<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Live connection verification for Connected Accounts: the result of the
     * last authenticated check against the provider (see
     * App\Services\Social\SocialConnectionVerifier), the profile picture and
     * permissions the provider actually granted, and when the user was last
     * told the connection broke — so the bell notifies once per break, not on
     * every daily re-check.
     */
    public function up(): void
    {
        Schema::table('social_connections', function (Blueprint $table) {
            $table->string('avatar_url', 2048)->nullable()->after('account_id');
            $table->json('granted_scopes')->nullable()->after('avatar_url');
            $table->timestamp('last_verified_at')->nullable()->after('connected_at');
            $table->string('verification_status')->nullable()->after('last_verified_at'); // verified | failed | expired
            $table->string('verification_message', 500)->nullable()->after('verification_status');
            $table->timestamp('broken_notified_at')->nullable()->after('verification_message');
        });
    }

    public function down(): void
    {
        Schema::table('social_connections', function (Blueprint $table) {
            $table->dropColumn(['avatar_url', 'granted_scopes', 'last_verified_at', 'verification_status', 'verification_message', 'broken_notified_at']);
        });
    }
};
