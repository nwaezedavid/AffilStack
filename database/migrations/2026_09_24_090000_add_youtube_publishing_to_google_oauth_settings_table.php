<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Task #2 (YouTube publishing): a third consent flow on the SAME Google
     * OAuth client already used for login and Gmail sending — scope
     * youtube.upload. approval_status tracks YouTube Data API's own Audit
     * + Quota Extension process, which is separate from Gmail's CASA
     * requirement and can be granted (or not) independently — see
     * App\Services\Social\YouTubePublishingService.
     */
    public function up(): void
    {
        Schema::table('google_oauth_settings', function (Blueprint $table) {
            $table->boolean('youtube_publishing_enabled')->default(false)->after('gmail_sending_enabled');
            $table->string('youtube_approval_status')->default('not_submitted')->after('youtube_publishing_enabled');
            $table->text('youtube_approval_notes')->nullable()->after('youtube_approval_status');
        });
    }

    public function down(): void
    {
        Schema::table('google_oauth_settings', function (Blueprint $table) {
            $table->dropColumn(['youtube_publishing_enabled', 'youtube_approval_status', 'youtube_approval_notes']);
        });
    }
};
