<?php

namespace Tests\Feature;

use App\Jobs\RunLocalizationGeneration;
use App\Models\Generation;
use App\Models\Offer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * The localization feature's HTTP entry point — the offer-ownership check,
 * that a valid request hands off to LocalizationService::queue() rather
 * than duplicating its validation, and that the queue()-time credit check
 * blocks the request (and the job dispatch) before any AI spend, not just
 * after. See App\Http\Controllers\Dashboard\LocalizationController.
 */
class LocalizationControllerTest extends TestCase
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
            'output_meta' => ['title' => 'Acme Widget Review', 'body' => 'Buy it today. {{AFFILIATE_LINK}}'],
            'credits_spent' => 15,
            'status' => 'completed',
        ]);
    }

    public function test_a_valid_request_queues_a_localization(): void
    {
        Queue::fake();
        $user = User::factory()->create(['credits_balance' => 100]);
        $offer = $this->offer($user);
        $source = $this->completedSource($offer, $user);

        $response = $this->actingAs($user)->post(route('generations.localize', $source), [
            'target_market' => 'GH',
        ]);

        $response->assertRedirect()->assertSessionHas('success', 'Localizing for Ghana — running in the background.');
        Queue::assertPushed(RunLocalizationGeneration::class);
        $this->assertDatabaseHas('generations', [
            'localized_from_id' => $source->id,
            'target_market' => 'GH',
            'module' => 'blog_article',
            'status' => 'queued',
            'credits_spent' => 0,
        ]);
    }

    public function test_an_unknown_target_market_fails_validation_without_queuing(): void
    {
        Queue::fake();
        $user = User::factory()->create(['credits_balance' => 100]);
        $offer = $this->offer($user);
        $source = $this->completedSource($offer, $user);

        $response = $this->actingAs($user)->post(route('generations.localize', $source), [
            'target_market' => 'ZZ',
        ]);

        $response->assertSessionHasErrors('target_market');
        Queue::assertNothingPushed();
        $this->assertSame(0, Generation::where('localized_from_id', $source->id)->count());
    }

    public function test_insufficient_credits_redirects_with_an_error_instead_of_queuing(): void
    {
        Queue::fake();
        $user = User::factory()->create(['credits_balance' => 5]);
        $offer = $this->offer($user);
        $source = $this->completedSource($offer, $user);

        $response = $this->actingAs($user)->post(route('generations.localize', $source), [
            'target_market' => 'NG',
        ]);

        $response->assertRedirect()->assertSessionHas('error');
        Queue::assertNothingPushed();
        $this->assertSame(5, $user->fresh()->credits_balance);
        $this->assertSame(0, Generation::where('localized_from_id', $source->id)->count());
    }

    public function test_a_module_that_cannot_be_localized_shows_an_error_instead_of_queuing(): void
    {
        Queue::fake();
        $user = User::factory()->create(['credits_balance' => 100]);
        $offer = $this->offer($user);
        $source = $this->completedSource($offer, $user, module: 'research');

        $response = $this->actingAs($user)->post(route('generations.localize', $source), [
            'target_market' => 'NG',
        ]);

        $response->assertRedirect()->assertSessionHas('error');
        Queue::assertNothingPushed();
    }

    public function test_a_user_cannot_localize_someone_elses_generation(): void
    {
        Queue::fake();
        $owner = User::factory()->create(['credits_balance' => 100]);
        $offer = $this->offer($owner);
        $source = $this->completedSource($offer, $owner);

        $intruder = User::factory()->create(['credits_balance' => 100]);

        $response = $this->actingAs($intruder)->post(route('generations.localize', $source), [
            'target_market' => 'NG',
        ]);

        $response->assertForbidden();
        Queue::assertNothingPushed();
    }

    public function test_a_guest_is_redirected_to_login(): void
    {
        Queue::fake();
        $user = User::factory()->create(['credits_balance' => 100]);
        $offer = $this->offer($user);
        $source = $this->completedSource($offer, $user);

        $response = $this->post(route('generations.localize', $source), [
            'target_market' => 'NG',
        ]);

        $response->assertRedirect(route('login'));
        Queue::assertNothingPushed();
    }
}
