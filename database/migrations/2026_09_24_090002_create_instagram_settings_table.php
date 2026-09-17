<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Task #2 (Instagram publishing): single-row (id=1) admin configuration
     * for a Meta app (Facebook Login for Business — app_id/app_secret).
     * approval_status tracks Meta's App Review for the instagram_content_publish
     * permission plus having a verified Business Manager, both required
     * before real publishing works — see
     * App\Services\Social\InstagramPublishingService.
     */
    public function up(): void
    {
        Schema::create('instagram_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('is_enabled')->default(false);
            $table->json('credentials')->nullable();
            $table->string('approval_status')->default('not_submitted');
            $table->text('approval_notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('instagram_settings');
    }
};
