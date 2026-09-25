<?php

namespace Tests\Feature\Api\V1;

use App\Models\ApiToken;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * API roadmap item #7 (per-token usage analytics) — LogApiRequest logs
 * every /v1/* call reaching a resolved token; cost_cents reflects the NET
 * amount kept, not the gross charge, so a refunded call logs 0.
 */
class ApiRequestLogTest extends TestCase
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

    public function test_a_free_get_request_is_logged_with_zero_cost(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');
        $token = ApiToken::generate($user, 'Test');

        $this->getJson('/api/v1/me', $this->authHeaders($token['plainText']))->assertOk();

        $this->assertDatabaseHas('api_request_logs', [
            'api_token_id' => $token['token']->id,
            'route' => 'api.v1.me',
            'method' => 'GET',
            'status_code' => 200,
            'cost_cents' => 0,
        ]);
    }

    public function test_a_metered_and_successful_call_logs_its_cost(): void
    {
        Queue::fake();
        $user = User::factory()->create(['credits_balance' => 1000, 'api_wallet_balance_cents' => 1000]);
        $user->assignRole('user');
        $token = ApiToken::generate($user, 'Test');

        $this->postJson('/api/v1/offers', [
            'product_name' => 'A', 'product_url' => 'https://a.example', 'affiliate_network' => 'ShareASale',
        ], $this->authHeaders($token['plainText']))->assertStatus(201);

        $this->assertDatabaseHas('api_request_logs', [
            'api_token_id' => $token['token']->id,
            'route' => 'api.v1.offers.store',
            'status_code' => 201,
            'cost_cents' => 75,
        ]);
    }

    public function test_a_refunded_failed_call_logs_zero_cost_not_the_reverted_charge(): void
    {
        $user = User::factory()->create(['credits_balance' => 0, 'api_wallet_balance_cents' => 1000]);
        $user->assignRole('user');
        $token = ApiToken::generate($user, 'Test');

        $this->postJson('/api/v1/offers', [
            'product_name' => 'A', 'product_url' => 'https://a.example', 'affiliate_network' => 'ShareASale',
        ], $this->authHeaders($token['plainText']))->assertStatus(402);

        $this->assertDatabaseHas('api_request_logs', [
            'api_token_id' => $token['token']->id,
            'status_code' => 402,
            'cost_cents' => 0,
        ]);
    }

    public function test_a_rejected_read_only_write_is_still_logged(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');
        $token = ApiToken::generate($user, 'Read only', scope: 'read_only');

        $this->postJson('/api/v1/crm-contacts', ['name' => 'Lead'], $this->authHeaders($token['plainText']))
            ->assertStatus(403);

        $this->assertDatabaseHas('api_request_logs', [
            'api_token_id' => $token['token']->id,
            'status_code' => 403,
        ]);
    }

    public function test_the_api_access_page_shows_calls_and_spend_for_each_token(): void
    {
        Queue::fake();
        $user = User::factory()->create(['credits_balance' => 1000, 'api_wallet_balance_cents' => 1000]);
        $user->assignRole('user');
        $token = ApiToken::generate($user, 'My Integration');

        $this->postJson('/api/v1/offers', [
            'product_name' => 'A', 'product_url' => 'https://a.example', 'affiliate_network' => 'ShareASale',
        ], $this->authHeaders($token['plainText']))->assertStatus(201);
        $this->getJson('/api/v1/me', $this->authHeaders($token['plainText']))->assertOk();

        $this->actingAs($user)->get(route('api-access.index'))
            ->assertOk()
            ->assertSee('My Integration')
            ->assertSee('2 calls')
            ->assertSee('$0.75');
    }
}
