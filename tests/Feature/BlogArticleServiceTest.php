<?php

namespace Tests\Feature;

use App\Models\Generation;
use App\Models\Offer;
use App\Models\User;
use App\Services\AI\AIProvider;
use App\Services\Modules\BlogArticleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * BlogArticleService::generate() runs inside a queue worker (RunBlogArticle,
 * $tries = 1, no failed() handler) and is documented to "always leave the
 * generation in a terminal state and never throw". Regression tests for two
 * real gaps found in an audit:
 *
 *  - Only AIGenerationException was ever caught around the AI call, so a
 *    network-level failure (timeout, DNS, connection refused — Laravel's
 *    ConnectionException, thrown by the HTTP client itself rather than
 *    returned as a failed response) propagated straight out of generate(),
 *    crashing the job silently: stuck at "queued" forever, nobody notified.
 *  - Credits were spent *after* the generation was already marked
 *    "completed" (with credits_spent recorded), so a spend failure — e.g. a
 *    concurrent generation draining the same low balance between the
 *    hasEnough() check and the final spend() — left the generation looking
 *    successful while the ledger was never actually charged.
 */
class BlogArticleServiceTest extends TestCase
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

    protected function queuedGeneration(Offer $offer, User $user): Generation
    {
        return $offer->generations()->create([
            'user_id' => $user->id,
            'module' => 'blog_article',
            'input' => ['offer_id' => $offer->id],
            'credits_spent' => 0,
            'status' => 'queued',
        ]);
    }

    public function test_generate_spends_credits_and_completes_on_success(): void
    {
        $user = User::factory()->create(['credits_balance' => 100]);
        $offer = $this->offer($user);
        $generation = $this->queuedGeneration($offer, $user);

        $this->mock(AIProvider::class, function (MockInterface $mock) {
            $mock->shouldReceive('generateJson')->once()->andReturn([
                'title' => 'Great Widget',
                'slug' => 'great-widget',
                'meta_description' => 'A great widget.',
                'target_keyword' => 'widget',
                'article_markdown' => '# Great Widget',
                'suggested_tags' => ['widget'],
            ]);
        });

        app(BlogArticleService::class)->generate($generation);

        $generation->refresh();
        $this->assertSame('completed', $generation->status);
        $this->assertSame(15, $generation->credits_spent);
        $this->assertSame('# Great Widget', $generation->output);
        $this->assertSame(100 - 15, $user->fresh()->credits_balance);
    }

    public function test_generate_fails_cleanly_instead_of_crashing_on_an_unexpected_provider_error(): void
    {
        $user = User::factory()->create(['credits_balance' => 100]);
        $offer = $this->offer($user);
        $generation = $this->queuedGeneration($offer, $user);

        // A network-level failure throws Illuminate's ConnectionException,
        // not AIGenerationException — the only exception generate() used to
        // catch. Before the fix, this call would itself throw and fail this
        // test; the real queue job has $tries = 1 and no failed() handler,
        // so the generation would be stuck at "queued" forever.
        $this->mock(AIProvider::class, function (MockInterface $mock) {
            $mock->shouldReceive('generateJson')->once()->andThrow(
                new ConnectionException('cURL error 28: Operation timed out')
            );
        });

        app(BlogArticleService::class)->generate($generation);

        $generation->refresh();
        $this->assertSame('failed', $generation->status);
        $this->assertNotNull($generation->error_message);
        $this->assertSame(100, $user->fresh()->credits_balance);
    }

    public function test_a_credit_spend_failure_leaves_the_generation_failed_not_completed(): void
    {
        $user = User::factory()->create(['credits_balance' => 15]);
        $offer = $this->offer($user);
        $generation = $this->queuedGeneration($offer, $user);

        // Simulates a second, concurrent generation spending this user's
        // last 15 credits during the AI round-trip: the hasEnough() check
        // at the top of generate() passed (balance was 15 at that instant),
        // but by the time credits->spend() runs, the balance has already
        // been taken by the other generation.
        $this->mock(AIProvider::class, function (MockInterface $mock) use ($user) {
            $mock->shouldReceive('generateJson')->once()->andReturnUsing(function () use ($user) {
                $user->update(['credits_balance' => 0]);

                return ['article_markdown' => '# Great Widget'];
            });
        });

        app(BlogArticleService::class)->generate($generation);

        $generation->refresh();
        // Before the fix: status ended up "completed" with credits_spent
        // recorded as 15 even though spend() threw InsufficientCreditsException
        // and the ledger/balance were never actually touched by this
        // generation — the article was handed out for free.
        $this->assertSame('failed', $generation->status);
        $this->assertSame(0, $generation->credits_spent);
        $this->assertNull($generation->output);
        $this->assertSame(0, $user->fresh()->credits_balance);
    }
}
