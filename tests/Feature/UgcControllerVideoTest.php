<?php

namespace Tests\Feature;

use App\Jobs\RunUgcVideoGeneration;
use App\Models\HeyGenSetting;
use App\Models\Offer;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * The UGC video overhaul's HTTP entry point — plan gating (channel "ugc"),
 * the offer-ownership check, and that a valid request hands off to
 * UgcVideoService::queue() rather than duplicating its validation.
 */
class UgcControllerVideoTest extends TestCase
{
    use RefreshDatabase;

    protected function planWithUgc(): Plan
    {
        return Plan::create([
            'name' => 'Growth', 'slug' => 'growth', 'description' => 'Test plan',
            'price_monthly_cents' => 6700, 'price_yearly_cents' => 67000, 'currency' => 'USD',
            'credits_per_month' => 600, 'active_products_limit' => 5, 'contact_limit' => 5000,
            'team_seats' => 1, 'channels' => ['ugc'], 'is_active' => true, 'sort_order' => 1,
        ]);
    }

    protected function planWithoutUgc(): Plan
    {
        return Plan::create([
            'name' => 'Starter', 'slug' => 'starter', 'description' => 'Test plan',
            'price_monthly_cents' => 2700, 'price_yearly_cents' => 27000, 'currency' => 'USD',
            'credits_per_month' => 150, 'active_products_limit' => 2, 'contact_limit' => 500,
            'team_seats' => 1, 'channels' => ['research'], 'is_active' => true, 'sort_order' => 0,
        ]);
    }

    protected function subscribe(User $user, Plan $plan): void
    {
        Subscription::create([
            'user_id' => $user->id, 'plan_id' => $plan->id, 'status' => 'active',
            'billing_cycle' => 'monthly', 'gateway' => 'stripe',
            'gateway_customer_id' => 'cus_1', 'gateway_subscription_id' => 'sub_1',
            'current_period_start' => now()->subDay(), 'current_period_end' => now()->addMonth(),
        ]);
    }

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

    public function test_video_generation_is_blocked_for_a_plan_without_the_ugc_channel(): void
    {
        Queue::fake();
        $user = User::factory()->create(['credits_balance' => 100]);
        $this->subscribe($user, $this->planWithoutUgc());
        $offer = $this->offer($user);
        $content = $offer->generations()->create([
            'user_id' => $user->id, 'module' => 'ugc_content', 'status' => 'completed',
            'output_meta' => ['video_script' => 'Hey there.'],
        ]);
        HeyGenSetting::current()->update(['is_enabled' => true, 'credentials' => ['api_key' => 'k'], 'verification_status' => 'success']);

        $response = $this->actingAs($user)->post(route('offers.ugc.video', $offer), [
            'content_generation_id' => $content->id,
            'avatar_id' => 'avatar_1',
            'voice_id' => 'voice_1',
        ]);

        $response->assertRedirect()->assertSessionHas('error');
        Queue::assertNothingPushed();
        $this->assertDatabaseMissing('generations', ['offer_id' => $offer->id, 'module' => 'ugc_video']);
    }

    public function test_a_valid_request_queues_a_video_generation(): void
    {
        Queue::fake();
        $user = User::factory()->create(['credits_balance' => 100]);
        $this->subscribe($user, $this->planWithUgc());
        $offer = $this->offer($user);
        $content = $offer->generations()->create([
            'user_id' => $user->id, 'module' => 'ugc_content', 'status' => 'completed',
            'output_meta' => ['video_script' => 'Hey there.'],
        ]);
        HeyGenSetting::current()->update(['is_enabled' => true, 'credentials' => ['api_key' => 'k'], 'verification_status' => 'success']);

        $response = $this->actingAs($user)->post(route('offers.ugc.video', $offer), [
            'content_generation_id' => $content->id,
            'avatar_id' => 'avatar_1',
            'voice_id' => 'voice_1',
        ]);

        $response->assertRedirect(route('offers.show', $offer))->assertSessionHas('success');
        Queue::assertPushed(RunUgcVideoGeneration::class);
        $this->assertDatabaseHas('generations', ['offer_id' => $offer->id, 'module' => 'ugc_video', 'status' => 'queued']);
    }

    public function test_a_user_cannot_queue_a_video_for_someone_elses_offer(): void
    {
        Queue::fake();
        $plan = $this->planWithUgc();
        $owner = User::factory()->create(['credits_balance' => 100]);
        $this->subscribe($owner, $plan);
        $offer = $this->offer($owner);
        $content = $offer->generations()->create([
            'user_id' => $owner->id, 'module' => 'ugc_content', 'status' => 'completed',
            'output_meta' => ['video_script' => 'Hey there.'],
        ]);
        HeyGenSetting::current()->update(['is_enabled' => true, 'credentials' => ['api_key' => 'k'], 'verification_status' => 'success']);

        $intruder = User::factory()->create(['credits_balance' => 100]);
        $this->subscribe($intruder, $plan);

        $response = $this->actingAs($intruder)->post(route('offers.ugc.video', $offer), [
            'content_generation_id' => $content->id,
            'avatar_id' => 'avatar_1',
            'voice_id' => 'voice_1',
        ]);

        $response->assertForbidden();
        Queue::assertNothingPushed();
    }

    public function test_insufficient_credits_redirects_with_an_error_instead_of_queuing(): void
    {
        Queue::fake();
        $user = User::factory()->create(['credits_balance' => 5]);
        $this->subscribe($user, $this->planWithUgc());
        $offer = $this->offer($user);
        $content = $offer->generations()->create([
            'user_id' => $user->id, 'module' => 'ugc_content', 'status' => 'completed',
            'output_meta' => ['video_script' => 'Hey there.'],
        ]);
        HeyGenSetting::current()->update(['is_enabled' => true, 'credentials' => ['api_key' => 'k'], 'verification_status' => 'success']);

        $response = $this->actingAs($user)->post(route('offers.ugc.video', $offer), [
            'content_generation_id' => $content->id,
            'avatar_id' => 'avatar_1',
            'voice_id' => 'voice_1',
        ]);

        $response->assertRedirect()->assertSessionHas('error');
        Queue::assertNothingPushed();
    }
}
