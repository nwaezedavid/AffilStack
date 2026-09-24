<?php

namespace Tests\Feature;

use App\Jobs\RunLocalizationGeneration;
use App\Models\Generation;
use App\Models\Offer;
use App\Models\User;
use App\Services\AI\AIGenerationException;
use App\Services\AI\AIProvider;
use App\Services\Credits\InsufficientCreditsException;
use App\Services\Modules\LocalizationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use InvalidArgumentException;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * Multi-market localization (Phase 3, item 8): adapts an already-generated
 * piece of content for a different market through AIProvider — a genuine,
 * context-aware rewrite (English-speaking markets) or full translation
 * (non-English markets), never a literal word-for-word substitution. See
 * App\Services\Modules\LocalizationService.
 */
class LocalizationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function offer(User $user): Offer
    {
        return Offer::create([
            'user_id' => $user->id,
            'product_name' => 'Acme Widget',
            'product_url' => 'https://example.com',
            'affiliate_network' => 'ShareASale',
            'status' => 'ready',
        ]);
    }

    protected function completedSource(Offer $offer, User $user, string $module = 'blog_article'): Generation
    {
        return $offer->generations()->create([
            'user_id' => $user->id,
            'module' => $module,
            'input' => ['topic' => 'Acme Widget review'],
            'output' => json_encode(['title' => 'Acme Widget Review', 'body' => 'Buy it today for $49. {{AFFILIATE_LINK}}']),
            'output_meta' => ['title' => 'Acme Widget Review', 'body' => 'Buy it today for $49. {{AFFILIATE_LINK}}'],
            'credits_spent' => 15,
            'status' => 'completed',
        ]);
    }

    protected function queuedLocalization(Offer $offer, User $user, Generation $source, string $market): Generation
    {
        return $offer->generations()->create([
            'user_id' => $user->id,
            'module' => $source->module,
            'input' => $source->input,
            'target_market' => $market,
            'localized_from_id' => $source->id,
            'credits_spent' => 0,
            'status' => 'queued',
        ]);
    }

    // --- queue(): validation and the pre-spend credit gate ------------------

    public function test_queue_rejects_an_unknown_target_market(): void
    {
        $user = User::factory()->create(['credits_balance' => 100]);
        $offer = $this->offer($user);
        $source = $this->completedSource($offer, $user);

        $this->expectException(InvalidArgumentException::class);

        app(LocalizationService::class)->queue($user, $source, 'ZZ');
    }

    public function test_queue_rejects_a_module_that_cannot_be_localized(): void
    {
        $user = User::factory()->create(['credits_balance' => 100]);
        $offer = $this->offer($user);
        $source = $this->completedSource($offer, $user, module: 'research');

        $this->expectException(InvalidArgumentException::class);

        app(LocalizationService::class)->queue($user, $source, 'NG');
    }

    public function test_queue_rejects_a_source_that_is_not_completed(): void
    {
        $user = User::factory()->create(['credits_balance' => 100]);
        $offer = $this->offer($user);
        $source = $this->completedSource($offer, $user);
        $source->update(['status' => 'queued']);

        $this->expectException(InvalidArgumentException::class);

        app(LocalizationService::class)->queue($user, $source, 'NG');
    }

    /**
     * The task's "notify before spend" requirement is only meaningful if the
     * block genuinely happens before any money-costing AI call — not after
     * one that then fails to charge. shouldNotReceive() fails the test if
     * generateJson() is invoked at all.
     */
    public function test_queue_blocks_when_credits_are_insufficient_and_never_calls_the_ai(): void
    {
        $user = User::factory()->create(['credits_balance' => 5]);
        $offer = $this->offer($user);
        $source = $this->completedSource($offer, $user);

        $this->mock(AIProvider::class, function (MockInterface $mock) {
            $mock->shouldNotReceive('generateJson');
        });

        try {
            app(LocalizationService::class)->queue($user, $source, 'NG');
            $this->fail('Expected InsufficientCreditsException was not thrown.');
        } catch (InsufficientCreditsException $e) {
            $this->assertStringContainsString('8 credits', $e->getMessage());
        }

        $this->assertSame(5, $user->fresh()->credits_balance);
        $this->assertSame(0, Generation::where('localized_from_id', $source->id)->count());
    }

    public function test_queue_creates_a_pending_generation_and_dispatches_the_job(): void
    {
        Queue::fake();
        $user = User::factory()->create(['credits_balance' => 100]);
        $offer = $this->offer($user);
        $source = $this->completedSource($offer, $user);

        $localized = app(LocalizationService::class)->queue($user, $source, 'FR');

        $this->assertSame('blog_article', $localized->module);
        $this->assertSame('FR', $localized->target_market);
        $this->assertSame($source->id, $localized->localized_from_id);
        $this->assertSame('queued', $localized->status);
        $this->assertSame(0, $localized->credits_spent);
        Queue::assertPushed(RunLocalizationGeneration::class, fn ($job) => $job->generation->is($localized));
    }

    // --- localize()/run(): the actual AI-driven adaptation -------------------

    public function test_localize_rewrites_for_an_english_speaking_market_without_translating(): void
    {
        $this->assertSame(8, config('credits.costs.localization'), 'credits.costs.localization drifted from the documented 8-credit cost');

        $user = User::factory()->create(['credits_balance' => 100]);
        $offer = $this->offer($user);
        $source = $this->completedSource($offer, $user);
        $localized = $this->queuedLocalization($offer, $user, $source, 'NG');

        $translated = [
            'title' => 'Acme Widget Review for Nigerian Shoppers',
            'body' => 'Get it today for ₦45,000 via bank transfer or USSD. {{AFFILIATE_LINK}}',
        ];

        $captured = [];
        $this->mock(AIProvider::class, function (MockInterface $mock) use (&$captured, $translated) {
            $mock->shouldReceive('generateJson')->once()
                ->andReturnUsing(function (string $system, string $userPrompt) use (&$captured, $translated) {
                    $captured['system'] = $system;
                    $captured['userPrompt'] = $userPrompt;

                    return $translated;
                });
        });

        app(LocalizationService::class)->localize($localized);

        $localized->refresh();
        $this->assertSame('completed', $localized->status);
        $this->assertSame($translated, $localized->output_meta);
        $this->assertSame(json_encode($translated), $localized->output);
        $this->assertSame(8, $localized->credits_spent);
        $this->assertSame(100 - 8, $user->fresh()->credits_balance);

        // English-market target: adapt currency/culture, but the system
        // prompt must still tell the model this is not a mere word-swap.
        $this->assertStringContainsString('not a superficial find-and-replace', $captured['system']);
        $this->assertStringContainsString('light word-swap', $captured['system']);
        $this->assertStringNotContainsString('Translate the content fully', $captured['system']);
        $this->assertStringContainsString('Nigeria', $captured['userPrompt']);
        $this->assertStringContainsString('{{AFFILIATE_LINK}}', $captured['userPrompt']);
    }

    public function test_localize_genuinely_translates_for_a_non_english_market(): void
    {
        $user = User::factory()->create(['credits_balance' => 100]);
        $offer = $this->offer($user);
        $source = $this->completedSource($offer, $user);
        $localized = $this->queuedLocalization($offer, $user, $source, 'FR');

        $translated = [
            'title' => 'Avis sur le Widget Acme',
            'body' => "Achetez-le aujourd'hui pour 45 € via carte bancaire. {{AFFILIATE_LINK}}",
        ];

        $captured = [];
        $this->mock(AIProvider::class, function (MockInterface $mock) use (&$captured, $translated) {
            $mock->shouldReceive('generateJson')->once()
                ->andReturnUsing(function (string $system, string $userPrompt) use (&$captured, $translated) {
                    $captured['system'] = $system;

                    return $translated;
                });
        });

        app(LocalizationService::class)->localize($localized);

        $localized->refresh();
        $this->assertSame('completed', $localized->status);
        $this->assertSame($translated, $localized->output_meta);
        $this->assertSame(8, $localized->credits_spent);
        $this->assertSame(100 - 8, $user->fresh()->credits_balance);

        // Non-English target: this must be a genuine translation, and the
        // prompt must explicitly rule out a literal word-for-word pass.
        $this->assertStringContainsString('Translate the content fully into French', $captured['system']);
        $this->assertStringContainsString('not a literal word-for-word translation', $captured['system']);
    }

    public function test_localize_marks_the_generation_failed_and_charges_nothing_when_the_ai_fails(): void
    {
        $user = User::factory()->create(['credits_balance' => 100]);
        $offer = $this->offer($user);
        $source = $this->completedSource($offer, $user);
        $localized = $this->queuedLocalization($offer, $user, $source, 'NG');

        $this->mock(AIProvider::class, function (MockInterface $mock) {
            $mock->shouldReceive('generateJson')->once()->andThrow(new AIGenerationException('Provider timed out.'));
        });

        app(LocalizationService::class)->localize($localized);

        $localized->refresh();
        $this->assertSame('failed', $localized->status);
        $this->assertSame('Provider timed out.', $localized->error_message);
        $this->assertSame(0, $localized->credits_spent);
        $this->assertSame(100, $user->fresh()->credits_balance);
    }

    /**
     * A second, independent guard at job-run time (distinct from queue()'s
     * check) for the race where credits were spent elsewhere between the
     * request being queued and the worker actually picking it up.
     */
    public function test_run_blocks_at_job_time_if_credits_were_spent_elsewhere_before_the_job_ran(): void
    {
        $user = User::factory()->create(['credits_balance' => 3]);
        $offer = $this->offer($user);
        $source = $this->completedSource($offer, $user);
        $localized = $this->queuedLocalization($offer, $user, $source, 'NG');

        $this->mock(AIProvider::class, function (MockInterface $mock) {
            $mock->shouldNotReceive('generateJson');
        });

        app(LocalizationService::class)->localize($localized);

        $localized->refresh();
        $this->assertSame('failed', $localized->status);
        $this->assertSame('Insufficient credits at processing time.', $localized->error_message);
        $this->assertSame(3, $user->fresh()->credits_balance);
    }

    public function test_localize_fails_gracefully_when_the_source_generation_is_gone(): void
    {
        $user = User::factory()->create(['credits_balance' => 100]);
        $offer = $this->offer($user);
        $localized = $offer->generations()->create([
            'user_id' => $user->id,
            'module' => 'blog_article',
            'target_market' => 'NG',
            'localized_from_id' => null,
            'credits_spent' => 0,
            'status' => 'queued',
        ]);

        $this->mock(AIProvider::class, function (MockInterface $mock) {
            $mock->shouldNotReceive('generateJson');
        });

        app(LocalizationService::class)->localize($localized);

        $localized->refresh();
        $this->assertSame('failed', $localized->status);
        $this->assertSame('Localization source or target market is missing.', $localized->error_message);
        $this->assertSame(100, $user->fresh()->credits_balance);
    }
}
