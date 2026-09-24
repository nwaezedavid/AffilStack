<?php

namespace Tests\Feature;

use App\Models\Offer;
use App\Models\User;
use App\Services\AI\AIProvider;
use App\Services\Modules\OfferResearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * OfferResearchService::research() runs inside a queue worker (RunOfferResearch,
 * $tries = 1, no failed() handler) and is documented to "always leave the
 * offer in a terminal state (ready/failed) and never throw". Regression
 * tests for two real gaps found in an audit — see BlogArticleServiceTest
 * for the same bug class in every other generation module:
 *
 *  - Only AIGenerationException was ever caught around the AI call, so a
 *    network-level failure (Laravel's ConnectionException) propagated
 *    straight out of research() and crashed the job silently.
 *  - The offer was updated to "ready" and its completed Generation row
 *    created *before* credits->spend() ran, so a spend failure (e.g. a
 *    concurrent generation draining the same low balance) left the offer
 *    showing real research data and a "completed" generation while the
 *    ledger was never actually charged.
 */
class OfferResearchServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function queuedOffer(User $user): Offer
    {
        return Offer::create([
            'user_id' => $user->id,
            'product_name' => 'Acme Widget',
            'product_url' => 'https://example.com',
            'affiliate_network' => 'ShareASale',
            'status' => 'queued',
        ]);
    }

    protected function validResult(): array
    {
        return [
            'ideal_customer_summary' => 'Busy parents.',
            'pain_points' => ['no time'],
            'where_to_find' => ['r/parenting'],
            'recommended_channel' => 'blog',
            'recommended_channel_reason' => 'They read blogs.',
            'recommended_angle' => 'Save time.',
            'secondary_channel' => 'pinterest',
        ];
    }

    public function test_research_spends_credits_and_marks_the_offer_ready_on_success(): void
    {
        $user = User::factory()->create(['credits_balance' => 100]);
        $offer = $this->queuedOffer($user);

        $this->mock(AIProvider::class, function (MockInterface $mock) {
            $mock->shouldReceive('generateJson')->once()->andReturn($this->validResult());
        });

        app(OfferResearchService::class)->research($offer);

        $offer->refresh();
        $this->assertSame('ready', $offer->status);
        $this->assertSame('Save time.', $offer->recommended_angle);
        $this->assertSame(100 - 5, $user->fresh()->credits_balance);

        $generation = $offer->generations()->where('module', 'research')->first();
        $this->assertSame('completed', $generation->status);
        $this->assertSame(5, $generation->credits_spent);
    }

    public function test_research_fails_cleanly_instead_of_crashing_on_an_unexpected_provider_error(): void
    {
        $user = User::factory()->create(['credits_balance' => 100]);
        $offer = $this->queuedOffer($user);

        $this->mock(AIProvider::class, function (MockInterface $mock) {
            $mock->shouldReceive('generateJson')->once()->andThrow(
                new ConnectionException('cURL error 28: Operation timed out')
            );
        });

        app(OfferResearchService::class)->research($offer);

        $offer->refresh();
        $this->assertSame('failed', $offer->status);
        $this->assertSame(100, $user->fresh()->credits_balance);

        $generation = $offer->generations()->where('module', 'research')->first();
        $this->assertSame('failed', $generation->status);
        $this->assertNotNull($generation->error_message);
    }

    public function test_a_credit_spend_failure_leaves_the_offer_failed_not_ready(): void
    {
        $user = User::factory()->create(['credits_balance' => 5]);
        $offer = $this->queuedOffer($user);

        // Simulates a second, concurrent generation spending this user's
        // last 5 credits during the AI round-trip: the hasEnough() check at
        // the top of research() passed (balance was 5), but by the time
        // credits->spend() runs, the balance has already been taken.
        $this->mock(AIProvider::class, function (MockInterface $mock) use ($user) {
            $mock->shouldReceive('generateJson')->once()->andReturnUsing(function () use ($user) {
                $user->update(['credits_balance' => 0]);

                return $this->validResult();
            });
        });

        app(OfferResearchService::class)->research($offer);

        $offer->refresh();
        // Before the fix: the offer ended up "ready" with real research data
        // and a "completed" generation (credits_spent recorded as 5) even
        // though spend() threw InsufficientCreditsException and the ledger/
        // balance were never actually touched by this generation.
        $this->assertSame('failed', $offer->status);
        $this->assertNull($offer->recommended_angle);
        $this->assertSame(0, $user->fresh()->credits_balance);

        $generation = $offer->generations()->where('module', 'research')->first();
        $this->assertSame('failed', $generation->status);
        $this->assertSame(0, $generation->credits_spent);
    }
}
