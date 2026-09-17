<?php

namespace Tests\Feature;

use App\Models\Generation;
use App\Models\IntelligenceCentreReport;
use App\Models\Offer;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\AI\AIGenerationException;
use App\Services\AI\AIProvider;
use App\Services\Credits\InsufficientCreditsException;
use App\Services\Intelligence\IntelligenceCentreService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * Task #7 ("Intelligence Centre"): the AI self-assessment that reviews a
 * user's own account numbers (offers, generation success rate, credit
 * usage, plan-channel usage, CRM/referral activity) and produces a health
 * score, next steps, and a plan-upgrade recommendation — see
 * App\Services\Intelligence\IntelligenceCentreService.
 */
class IntelligenceCentreServiceTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Plan $plan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['credits_balance' => 1000]);
        $this->plan = Plan::factory()->create([
            'slug' => 'growth', 'sort_order' => 2, 'credits_per_month' => 600,
            'active_products_limit' => 5, 'contact_limit' => 5000, 'team_seats' => 1,
            'channels' => ['research', 'blog', 'linkedin', 'youtube', 'ugc'],
        ]);
        Subscription::factory()->create(['user_id' => $this->user->id, 'plan_id' => $this->plan->id, 'status' => 'active']);
    }

    public function test_it_refuses_without_enough_credits_and_calls_no_ai(): void
    {
        $this->user->update(['credits_balance' => 0]);
        $this->mock(AIProvider::class, function (MockInterface $mock) {
            $mock->shouldNotReceive('generateJson');
        });

        $this->expectException(InsufficientCreditsException::class);

        app(IntelligenceCentreService::class)->generate($this->user);

        $this->assertSame(0, IntelligenceCentreReport::count());
    }

    public function test_it_builds_metrics_and_stores_the_ai_assessment(): void
    {
        $offer = Offer::create(['user_id' => $this->user->id, 'product_name' => 'Acme Widget', 'product_url' => 'https://acme.example', 'affiliate_network' => 'ShareASale', 'status' => 'ready']);
        Generation::create(['user_id' => $this->user->id, 'offer_id' => $offer->id, 'module' => 'blog_article', 'status' => 'completed']);
        Generation::create(['user_id' => $this->user->id, 'offer_id' => $offer->id, 'module' => 'blog_article', 'status' => 'failed']);

        $this->mock(AIProvider::class, function (MockInterface $mock) {
            $mock->shouldReceive('generateJson')->once()->andReturn([
                'health_score' => 62,
                'headline' => 'Solid start with room to diversify channels.',
                'strengths' => ['Consistent blog output'],
                'gaps' => ['YouTube and UGC channels unused'],
                'next_steps' => ['Try a YouTube script', 'Try a UGC video'],
                'upgrade_recommended' => false,
                'upgrade_reason' => null,
                'suggested_plan_slug' => null,
            ]);
        });

        $report = app(IntelligenceCentreService::class)->generate($this->user);

        $this->assertSame($this->user->id, $report->user_id);
        $this->assertSame(62, $report->healthScore());
        $this->assertFalse($report->upgradeRecommended());
        $this->assertSame(1, $report->metrics['generations']['completed']);
        $this->assertSame(1, $report->metrics['generations']['failed']);
        $this->assertSame(50, $report->metrics['generations']['success_rate_percent']);
        $this->assertSame(['blog'], $report->metrics['channels']['used']);
        $this->assertContains('youtube', $report->metrics['channels']['unused']);
        $this->assertContains('ugc', $report->metrics['channels']['unused']);
        $this->assertSame(1, $report->metrics['offers']['total']);

        $this->assertSame(1000 - config('credits.costs.intelligence_centre'), $this->user->fresh()->credits_balance);
    }

    public function test_running_it_again_replaces_the_previous_report_rather_than_adding_a_new_row(): void
    {
        $this->mock(AIProvider::class, function (MockInterface $mock) {
            $mock->shouldReceive('generateJson')->twice()->andReturn(['health_score' => 40, 'headline' => 'x', 'strengths' => [], 'gaps' => [], 'next_steps' => [], 'upgrade_recommended' => false]);
        });

        app(IntelligenceCentreService::class)->generate($this->user);
        app(IntelligenceCentreService::class)->generate($this->user);

        $this->assertSame(1, IntelligenceCentreReport::count());
    }

    public function test_an_ai_failure_propagates_and_spends_no_credits(): void
    {
        $this->mock(AIProvider::class, function (MockInterface $mock) {
            $mock->shouldReceive('generateJson')->once()->andThrow(new AIGenerationException('Provider unavailable'));
        });

        $this->expectException(AIGenerationException::class);

        app(IntelligenceCentreService::class)->generate($this->user);

        $this->assertSame(1000, $this->user->fresh()->credits_balance);
        $this->assertSame(0, IntelligenceCentreReport::count());
    }

    public function test_it_recommends_an_upgrade_and_resolves_the_suggested_plan(): void
    {
        $pro = Plan::factory()->create(['slug' => 'pro', 'sort_order' => 3, 'is_active' => true]);

        $this->mock(AIProvider::class, function (MockInterface $mock) use ($pro) {
            $mock->shouldReceive('generateJson')->once()->andReturn([
                'health_score' => 55,
                'headline' => 'You are close to your offer limit.',
                'strengths' => [],
                'gaps' => ['At your active product limit'],
                'next_steps' => ['Upgrade to add more offers'],
                'upgrade_recommended' => true,
                'upgrade_reason' => 'You are at your plan\'s offer limit.',
                'suggested_plan_slug' => $pro->slug,
            ]);
        });

        $report = app(IntelligenceCentreService::class)->generate($this->user);

        $this->assertTrue($report->upgradeRecommended());
        $this->assertSame($pro->id, $report->suggestedPlan()->id);
    }

    public function test_candidate_upgrade_plans_sent_to_the_ai_only_include_higher_tiers(): void
    {
        Plan::factory()->create(['slug' => 'starter-tier', 'sort_order' => 1, 'is_active' => true]);
        $pro = Plan::factory()->create(['slug' => 'pro-tier', 'sort_order' => 3, 'is_active' => true]);
        $agency = Plan::factory()->create(['slug' => 'agency-tier', 'sort_order' => 4, 'is_active' => true]);

        $captured = null;
        $this->mock(AIProvider::class, function (MockInterface $mock) use (&$captured) {
            $mock->shouldReceive('generateJson')->once()->andReturnUsing(function ($system, $userPrompt) use (&$captured) {
                $captured = json_decode($userPrompt, true);

                return ['health_score' => 50, 'headline' => 'x', 'strengths' => [], 'gaps' => [], 'next_steps' => [], 'upgrade_recommended' => false];
            });
        });

        app(IntelligenceCentreService::class)->generate($this->user);

        $slugs = collect($captured['candidate_upgrade_plans'])->pluck('slug');
        $this->assertTrue($slugs->contains($pro->slug));
        $this->assertTrue($slugs->contains($agency->slug));
        $this->assertFalse($slugs->contains('starter-tier'));
    }
}
