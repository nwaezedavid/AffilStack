<?php

namespace Tests\Feature\Api\V1;

use App\Models\ApiToken;
use App\Models\CrmContact;
use App\Models\Offer;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * API roadmap item #1 — Idempotency-Key on POST /offers and POST
 * /crm-contacts (see EnsureIdempotency). No header at all is a complete
 * no-op — see ApiV1EndpointsTest for that unaffected default behavior.
 */
class IdempotencyKeyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
    }

    protected function tokenFor(User $user): string
    {
        return ApiToken::generate($user, 'Test token')['plainText'];
    }

    protected function authHeaders(string $plainText, array $extra = []): array
    {
        return ['Authorization' => "Bearer {$plainText}"] + $extra;
    }

    public function test_replaying_a_create_offer_call_with_the_same_key_returns_the_same_response_without_a_second_offer(): void
    {
        Queue::fake();
        $user = User::factory()->create(['credits_balance' => 1000, 'api_wallet_balance_cents' => 1000]);
        $user->assignRole('user');
        $headers = $this->authHeaders($this->tokenFor($user), ['Idempotency-Key' => 'key-1']);
        $payload = ['product_name' => 'API Offer', 'product_url' => 'https://api.example', 'affiliate_network' => 'ShareASale'];

        $first = $this->postJson('/api/v1/offers', $payload, $headers);
        $second = $this->postJson('/api/v1/offers', $payload, $headers);

        $first->assertStatus(201);
        $second->assertStatus(201)->assertJson(['id' => $first->json('id')]);
        $this->assertSame(1, Offer::count());
    }

    public function test_replaying_a_create_offer_call_does_not_charge_the_wallet_twice(): void
    {
        Queue::fake();
        $user = User::factory()->create(['credits_balance' => 1000, 'api_wallet_balance_cents' => 1000]);
        $user->assignRole('user');
        $headers = $this->authHeaders($this->tokenFor($user), ['Idempotency-Key' => 'key-1']);
        $payload = ['product_name' => 'API Offer', 'product_url' => 'https://api.example', 'affiliate_network' => 'ShareASale'];

        $this->postJson('/api/v1/offers', $payload, $headers);
        $this->postJson('/api/v1/offers', $payload, $headers);

        $this->assertSame(1000 - 75, $user->fresh()->api_wallet_balance_cents);
    }

    public function test_reusing_a_key_with_a_different_body_returns_422(): void
    {
        Queue::fake();
        $user = User::factory()->create(['credits_balance' => 1000, 'api_wallet_balance_cents' => 1000]);
        $user->assignRole('user');
        $headers = $this->authHeaders($this->tokenFor($user), ['Idempotency-Key' => 'key-1']);

        $this->postJson('/api/v1/offers', ['product_name' => 'A', 'product_url' => 'https://a.example', 'affiliate_network' => 'ShareASale'], $headers)
            ->assertStatus(201);

        $this->postJson('/api/v1/offers', ['product_name' => 'B', 'product_url' => 'https://b.example', 'affiliate_network' => 'ShareASale'], $headers)
            ->assertStatus(422);
    }

    public function test_a_different_key_creates_a_second_offer(): void
    {
        Queue::fake();
        $user = User::factory()->create(['credits_balance' => 1000, 'api_wallet_balance_cents' => 1000]);
        $user->assignRole('user');
        $payload = ['product_name' => 'API Offer', 'product_url' => 'https://api.example', 'affiliate_network' => 'ShareASale'];

        $this->postJson('/api/v1/offers', $payload, $this->authHeaders($this->tokenFor($user), ['Idempotency-Key' => 'key-1']))->assertStatus(201);
        $this->postJson('/api/v1/offers', $payload, $this->authHeaders($this->tokenFor($user), ['Idempotency-Key' => 'key-2']))->assertStatus(201);

        $this->assertSame(2, Offer::count());
    }

    public function test_a_failed_first_attempt_is_not_cached_and_can_be_retried_under_the_same_key(): void
    {
        Queue::fake();
        // No credits at all — the first attempt 402s and is refunded by
        // MeterApiUsage; nothing should be cached for a non-2xx response.
        $user = User::factory()->create(['credits_balance' => 0, 'api_wallet_balance_cents' => 1000]);
        $user->assignRole('user');
        $headers = $this->authHeaders($this->tokenFor($user), ['Idempotency-Key' => 'key-1']);
        $payload = ['product_name' => 'API Offer', 'product_url' => 'https://api.example', 'affiliate_network' => 'ShareASale'];

        $this->postJson('/api/v1/offers', $payload, $headers)->assertStatus(402);

        $user->update(['credits_balance' => 1000]);

        $this->postJson('/api/v1/offers', $payload, $headers)->assertStatus(201);
    }

    public function test_replaying_a_create_crm_contact_call_does_not_create_a_duplicate(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');
        $headers = $this->authHeaders($this->tokenFor($user), ['Idempotency-Key' => 'contact-key']);
        $payload = ['name' => 'New Lead', 'email' => 'lead@example.com'];

        $this->postJson('/api/v1/crm-contacts', $payload, $headers)->assertStatus(201);
        $this->postJson('/api/v1/crm-contacts', $payload, $headers)->assertStatus(201);

        $this->assertSame(1, CrmContact::count());
    }

    public function test_no_idempotency_key_header_behaves_exactly_as_before(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');
        $headers = $this->authHeaders($this->tokenFor($user));
        $payload = ['name' => 'New Lead'];

        $this->postJson('/api/v1/crm-contacts', $payload, $headers)->assertStatus(201);
        $this->postJson('/api/v1/crm-contacts', $payload, $headers)->assertStatus(201);

        $this->assertSame(2, CrmContact::count());
    }
}
