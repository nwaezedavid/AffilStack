<?php

namespace App\Services\Agents;

use App\Models\BrainAgentSetting;
use App\Models\MarketingCampaign;
use App\Models\Offer;
use App\Models\User;
use App\Services\AI\AIGenerationException;
use App\Services\AI\AIProvider;
use App\Services\AI\AnthropicClient;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Brain, the Marketing Agent (AI agents phase, agent #3 of 4): drafts ad
 * copy/targeting/budget/promotional images for an offer (via the admin's
 * own Anthropic/Claude key — see AnthropicClient), then, once an admin
 * approves with one click, hands the approved brief to Claude together
 * with the admin's own Meta Ads MCP server so Claude can actually create
 * and run the campaign — no Meta API integration lives in this app itself.
 * After launch, a daily scheduled pass (agents:marketing-optimize-
 * campaigns) lets Brain keep reviewing and adjusting a live campaign on
 * its own, without asking again.
 *
 * Like Sam, none of this touches AffilStack's own codebase or
 * infrastructure, so it doesn't need the super-admin AgentTask approval
 * gate that Tom/Tony require — any admin can draft and approve a campaign.
 */
class BrainAgentService
{
    public function __construct(protected AIProvider $imageAi) {}

    public function draftCampaign(Offer $offer, User $admin, ?string $goal = null): MarketingCampaign
    {
        $settings = BrainAgentSetting::current();

        if (! $settings->hasAnthropicKey()) {
            throw new RuntimeException('Connect an Anthropic (Claude) API key in AI Agents > Brain Settings before drafting a campaign.');
        }

        $brief = $this->anthropicFor($settings)->generateJson(
            $this->draftSystemPrompt(),
            $this->draftUserPrompt($offer, $goal),
        );

        return MarketingCampaign::create([
            'offer_id' => $offer->id,
            'created_by_id' => $admin->id,
            'title' => (string) ($brief['campaign_title'] ?? "{$offer->product_name} campaign"),
            'goal' => $goal,
            'status' => MarketingCampaign::STATUS_DRAFT,
            'brief' => $brief,
            'image_urls' => $this->generateImages($brief),
        ]);
    }

    /**
     * The one required human step: nothing gets created on Meta until an
     * admin clicks this. After it succeeds, subsequent adjustments happen
     * automatically via optimizeRunningCampaigns().
     */
    public function approveAndLaunch(MarketingCampaign $campaign, User $admin): void
    {
        // Real ad spend, like every other AI agent's consequential action
        // (Tom's fixes, Tony's publishes), requires super-admin — enforced
        // here, not just hidden in the UI, since an admin sub-account
        // granted the "ai_agents" department can otherwise see this table.
        if (! $admin->isSuperAdmin()) {
            throw new RuntimeException('Only super-admin can approve and launch a live campaign.');
        }

        if (! $campaign->isDraft()) {
            throw new InvalidArgumentException('Only a draft campaign can be approved and launched.');
        }

        $settings = BrainAgentSetting::current();

        if (! $settings->canLaunchLiveCampaigns()) {
            throw new RuntimeException('Brain cannot launch live campaigns yet — verify both the Anthropic and Meta Ads MCP connections in AI Agents > Brain Settings first.');
        }

        // Claimed atomically before any money can move: a double-click, a
        // second admin, or a retry after the request was cut off finds it
        // no longer a draft instead of creating a second live campaign.
        $claimed = MarketingCampaign::whereKey($campaign->id)
            ->where('status', MarketingCampaign::STATUS_DRAFT)
            ->update([
                'status' => MarketingCampaign::STATUS_APPROVED,
                'approved_by_id' => $admin->id,
                'approved_at' => now(),
            ]);

        if ($claimed !== 1) {
            throw new InvalidArgumentException('This campaign is already being launched.');
        }

        $campaign->refresh();

        $result = $this->anthropicFor($settings)->runAgenticCampaignAction(
            systemPrompt: $this->launchSystemPrompt(),
            userPrompt: $this->launchUserPrompt($campaign),
            mcpUrl: (string) $settings->credential('meta_mcp_url'),
            mcpToken: $settings->credential('meta_mcp_token'),
        );

        $campaign->update([
            'status' => $result['success'] ? MarketingCampaign::STATUS_RUNNING : MarketingCampaign::STATUS_FAILED,
            'launch_transcript' => $result['transcript'],
            // A failed or timed-out call may still have created objects on
            // Meta before it stopped — say so, so nobody relaunches blind.
            'failure_reason' => $result['success'] ? null : 'Launch not confirmed — check Meta Ads Manager for anything already created before trying again. '.$result['message'],
            'meta_campaign_ref' => $result['success']
                ? ($this->extractCampaignRef($result['transcript']) ?? $campaign->meta_campaign_ref)
                : $campaign->meta_campaign_ref,
        ]);
    }

    /**
     * "Gathers market insights/traffic/activity data to improve its own
     * strategies": every running/optimizing campaign is reviewed again,
     * armed with the offer's real click volume from this platform on top
     * of whatever performance data the Meta Ads MCP tools themselves
     * report — never asks for approval again once a campaign is live.
     *
     * @return int number of campaigns processed
     */
    public function optimizeRunningCampaigns(): int
    {
        $settings = BrainAgentSetting::current();

        if (! $settings->canLaunchLiveCampaigns()) {
            return 0;
        }

        $anthropic = $this->anthropicFor($settings);
        $processed = 0;

        MarketingCampaign::query()
            ->whereIn('status', MarketingCampaign::OPTIMIZABLE_STATUSES)
            ->with('offer.trackedLinks')
            ->chunkById(50, function ($campaigns) use ($anthropic, $settings, &$processed): void {
                foreach ($campaigns as $campaign) {
                    try {
                        $result = $anthropic->runAgenticCampaignAction(
                            systemPrompt: $this->optimizeSystemPrompt(),
                            userPrompt: $this->optimizeUserPrompt($campaign),
                            mcpUrl: (string) $settings->credential('meta_mcp_url'),
                            mcpToken: $settings->credential('meta_mcp_token'),
                        );

                        $campaign->update([
                            'status' => $result['success'] ? MarketingCampaign::STATUS_OPTIMIZING : $campaign->status,
                            'last_optimized_at' => now(),
                            'last_optimization_summary' => $result['message'],
                        ]);
                        $processed++;
                    } catch (Throwable $e) {
                        Log::error('Brain: optimization pass failed for a campaign.', ['campaign_id' => $campaign->id, 'error' => $e->getMessage()]);
                    }
                }
            });

        return $processed;
    }

    /**
     * @return array<int, string>
     */
    protected function generateImages(array $brief): array
    {
        $urls = [];

        foreach (array_slice((array) ($brief['image_prompts'] ?? []), 0, 2) as $prompt) {
            try {
                $url = $this->imageAi->generateImage((string) $prompt);

                if ($url !== '') {
                    $urls[] = $url;
                }
            } catch (AIGenerationException $e) {
                Log::warning('Brain: promotional image generation failed — draft still created without it.', ['error' => $e->getMessage()]);
            }
        }

        return $urls;
    }

    protected function anthropicFor(BrainAgentSetting $settings): AnthropicClient
    {
        return new AnthropicClient(
            apiKey: (string) $settings->credential('anthropic_api_key'),
            model: (string) ($settings->credential('anthropic_model') ?: 'claude-sonnet-5'),
        );
    }

    /**
     * Best-effort extraction of a Meta campaign/ad-account id from the
     * launch transcript, for display only — the source of truth for
     * what's actually running lives on Meta, reachable through the MCP
     * server itself; this is just a convenience the admin sees on the
     * campaign record.
     */
    protected function extractCampaignRef(string $transcript): ?string
    {
        return preg_match('/\b(act_\d+|\d{10,20})\b/', $transcript, $m) ? $m[1] : null;
    }

    protected function draftSystemPrompt(): string
    {
        return <<<'PROMPT'
            You are Brain, the marketing agent for AffilStack, an all-in-one SaaS
            for affiliate marketers. An admin has asked you to draft a Meta
            (Facebook/Instagram) ad campaign for one of their offers.

            Produce genuinely distinct ad copy variants (not trivial rewordings),
            practical targeting suggestions, a realistic daily budget range in
            USD, and 1-2 prompts for a text-to-image generator to create
            promotional creative. Favor cost-efficient, specific targeting over
            broad reach.

            Respond with ONLY a JSON object shaped exactly like this:
            {"campaign_title": string, "ad_copy_variants": [{"headline": string,
            "primary_text": string, "description": string}], "targeting":
            {"age_range": string, "interests": [string], "geos": [string]},
            "budget_suggestion": {"daily_min": number, "daily_max": number,
            "currency": "USD"}, "image_prompts": [string]}
            PROMPT;
    }

    protected function draftUserPrompt(Offer $offer, ?string $goal): string
    {
        $goalLine = $goal ? "Campaign goal: {$goal}." : 'No specific goal given — optimize for conversions.';

        return <<<PROMPT
            Product: {$offer->product_name}
            Product URL: {$offer->product_url}
            Affiliate network: {$offer->affiliate_network}
            Ideal customer: {$offer->ideal_customer_summary}
            Recommended angle: {$offer->recommended_angle}
            {$goalLine}
            PROMPT;
    }

    protected function launchSystemPrompt(): string
    {
        return <<<'PROMPT'
            You are Brain, the marketing agent for AffilStack. You have access to
            a Meta Ads MCP server's tools. An admin has approved the campaign
            brief below — use the connected tools to actually create the
            campaign, ad set, and ads on Meta now, using the best available
            targeting and bidding strategy to get the most results for the
            budget. Explain in plain text what you created and why, including
            any ids the tools return.
            PROMPT;
    }

    protected function launchUserPrompt(MarketingCampaign $campaign): string
    {
        $brief = json_encode($campaign->brief, JSON_PRETTY_PRINT);

        return "Approved campaign brief for \"{$campaign->title}\":\n\n{$brief}";
    }

    protected function optimizeSystemPrompt(): string
    {
        return <<<'PROMPT'
            You are Brain, the marketing agent for AffilStack, reviewing a
            campaign you already launched through the connected Meta Ads MCP
            server. Check its current performance with the available tools and
            adjust targeting, budget, or creative if that would improve results
            or lower cost per result — you do not need to ask for approval for
            these adjustments, they were already authorized when the campaign
            was launched. If performance is already good, make no changes.
            Summarize what you found and did in a few plain-text sentences.
            PROMPT;
    }

    protected function optimizeUserPrompt(MarketingCampaign $campaign): string
    {
        $platformClicks = $campaign->offer->trackedLinks->sum('clicks_count');

        return <<<PROMPT
            Campaign: "{$campaign->title}" (our reference: {$campaign->meta_campaign_ref})
            On-platform click volume for this offer's tracked links so far: {$platformClicks}
            Original brief:
            {$this->launchUserPrompt($campaign)}
            PROMPT;
    }
}
