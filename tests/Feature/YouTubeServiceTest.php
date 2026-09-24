<?php

namespace Tests\Feature;

use App\Models\Generation;
use App\Models\Offer;
use App\Models\User;
use App\Services\AI\AIProvider;
use App\Services\Modules\YouTubeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * YouTubeService::run() (shared by script() and metadata()) runs inside a
 * queue worker (RunYouTubeGeneration, $tries = 1, no failed() handler) and
 * is documented to "always leave the generation in a terminal state and
 * never throw". See BlogArticleServiceTest for the same bug class found
 * across every generation module in this audit. Exercised here through
 * script() — metadata() shares the exact same run() method, so it shares
 * the same fix.
 */
class YouTubeServiceTest extends TestCase
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
            'module' => 'youtube_script',
            'input' => ['offer_id' => $offer->id],
            'credits_spent' => 0,
            'status' => 'queued',
        ]);
    }

    public function test_script_spends_credits_and_completes_on_success(): void
    {
        $user = User::factory()->create(['credits_balance' => 100]);
        $offer = $this->offer($user);
        $generation = $this->queuedGeneration($offer, $user);

        $this->mock(AIProvider::class, function (MockInterface $mock) {
            $mock->shouldReceive('generateJson')->once()->andReturn([
                'working_title' => 'x', 'hook' => 'y', 'script_markdown' => '**[00:00]** Hook.',
                'b_roll_suggestions' => [], 'estimated_length_minutes' => 5,
            ]);
        });

        app(YouTubeService::class)->script($generation);

        $generation->refresh();
        $this->assertSame('completed', $generation->status);
        $this->assertSame('**[00:00]** Hook.', $generation->output);
        $this->assertSame(15, $generation->credits_spent);
        $this->assertSame(100 - 15, $user->fresh()->credits_balance);
    }

    public function test_script_fails_cleanly_instead_of_crashing_on_an_unexpected_provider_error(): void
    {
        $user = User::factory()->create(['credits_balance' => 100]);
        $offer = $this->offer($user);
        $generation = $this->queuedGeneration($offer, $user);

        $this->mock(AIProvider::class, function (MockInterface $mock) {
            $mock->shouldReceive('generateJson')->once()->andThrow(
                new ConnectionException('cURL error 28: Operation timed out')
            );
        });

        app(YouTubeService::class)->script($generation);

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

        $this->mock(AIProvider::class, function (MockInterface $mock) use ($user) {
            $mock->shouldReceive('generateJson')->once()->andReturnUsing(function () use ($user) {
                $user->update(['credits_balance' => 0]);

                return ['script_markdown' => 'x'];
            });
        });

        app(YouTubeService::class)->script($generation);

        $generation->refresh();
        $this->assertSame('failed', $generation->status);
        $this->assertSame(0, $generation->credits_spent);
        $this->assertNull($generation->output);
        $this->assertSame(0, $user->fresh()->credits_balance);
    }
}
