<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One ad campaign Brain (the Marketing Agent) has drafted for an offer
     * — see App\Models\MarketingCampaign and App\Services\Agents\
     * BrainAgentService. `brief` is Brain's draft (copy variants,
     * targeting, budget suggestion, image prompts); `status` moves
     * draft -> approved -> running -> optimizing (or failed at any step).
     * Launching requires an explicit admin approval click — Brain never
     * spends real ad budget without that one human step — but once
     * running, `agents:marketing-optimize-campaigns` adjusts it on its own.
     */
    public function up(): void
    {
        Schema::create('marketing_campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('offer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by_id')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->string('goal')->nullable();
            $table->string('status')->default('draft'); // draft, approved, running, optimizing, failed, paused
            $table->json('brief')->nullable();
            $table->json('image_urls')->nullable();
            $table->string('meta_campaign_ref')->nullable();
            $table->longText('launch_transcript')->nullable();
            $table->foreignId('approved_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('last_optimized_at')->nullable();
            $table->text('last_optimization_summary')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_campaigns');
    }
};
