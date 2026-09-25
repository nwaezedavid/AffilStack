<?php

namespace Tests\Feature\Api\V1;

use App\Models\ApiToken;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * API roadmap item #3 — plan-aware rate limits (see AppServiceProvider's
 * 'api' RateLimiter and config('api_billing.rate_limits')). Overrides the
 * configured limits down to a handful of requests per test so these run
 * fast without waiting out an actual minute window.
 */
class PlanRateLimitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
    }

    protected function authHeaders(string $plainText): array
    {
        return ['Authorization' => "Bearer {$plainText}"];
    }

    public function test_a_starter_plan_token_is_limited_to_its_configured_rate(): void
    {
        config(['api_billing.rate_limits.starter' => 3]);
        $plan = Plan::factory()->create(['slug' => 'starter']);
        $user = User::factory()->create();
        $user->assignRole('user');
        Subscription::factory()->create(['user_id' => $user->id, 'plan_id' => $plan->id]);
        $token = ApiToken::generate($user, 'Test')['plainText'];

        for ($i = 0; $i < 3; $i++) {
            $this->getJson('/api/v1/me', $this->authHeaders($token))->assertOk();
        }

        $this->getJson('/api/v1/me', $this->authHeaders($token))->assertStatus(429);
    }

    public function test_a_higher_tier_plan_gets_a_higher_limit_than_starter(): void
    {
        config(['api_billing.rate_limits.starter' => 1, 'api_billing.rate_limits.business' => 3]);
        $plan = Plan::factory()->create(['slug' => 'business']);
        $user = User::factory()->create();
        $user->assignRole('user');
        Subscription::factory()->create(['user_id' => $user->id, 'plan_id' => $plan->id]);
        $token = ApiToken::generate($user, 'Test')['plainText'];

        $this->getJson('/api/v1/me', $this->authHeaders($token))->assertOk();
        $this->getJson('/api/v1/me', $this->authHeaders($token))->assertOk();
        $this->getJson('/api/v1/me', $this->authHeaders($token))->assertOk();
        $this->getJson('/api/v1/me', $this->authHeaders($token))->assertStatus(429);
    }

    public function test_a_token_with_no_active_subscription_gets_the_default_limit(): void
    {
        config(['api_billing.rate_limits.default' => 2]);
        $user = User::factory()->create();
        $user->assignRole('user');
        $token = ApiToken::generate($user, 'Test')['plainText'];

        $this->getJson('/api/v1/me', $this->authHeaders($token))->assertOk();
        $this->getJson('/api/v1/me', $this->authHeaders($token))->assertOk();
        $this->getJson('/api/v1/me', $this->authHeaders($token))->assertStatus(429);
    }

    public function test_two_different_tokens_on_the_same_account_do_not_share_a_rate_limit_bucket(): void
    {
        config(['api_billing.rate_limits.default' => 1]);
        $user = User::factory()->create();
        $user->assignRole('user');
        $tokenA = ApiToken::generate($user, 'A')['plainText'];
        $tokenB = ApiToken::generate($user, 'B')['plainText'];

        $this->getJson('/api/v1/me', $this->authHeaders($tokenA))->assertOk();
        $this->getJson('/api/v1/me', $this->authHeaders($tokenB))->assertOk();
    }
}
