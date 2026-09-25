<?php

namespace Tests\Feature\Api\V1;

use App\Models\ApiToken;
use App\Models\CrmContact;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * API roadmap item #2 — read-only scoped tokens (see EnsureTokenScope).
 */
class TokenScopeTest extends TestCase
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

    public function test_a_read_only_token_can_still_make_get_requests(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');
        $token = ApiToken::generate($user, 'Read only', scope: 'read_only')['plainText'];

        $this->getJson('/api/v1/me', $this->authHeaders($token))->assertOk();
    }

    public function test_a_read_only_token_cannot_create_a_crm_contact(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');
        $token = ApiToken::generate($user, 'Read only', scope: 'read_only')['plainText'];

        $this->postJson('/api/v1/crm-contacts', ['name' => 'Lead'], $this->authHeaders($token))
            ->assertStatus(403)
            ->assertJson(['message' => 'This token is read-only and cannot make write requests.']);

        $this->assertDatabaseCount('crm_contacts', 0);
    }

    public function test_a_read_only_token_cannot_create_an_offer_and_is_never_charged(): void
    {
        $user = User::factory()->create(['credits_balance' => 1000, 'api_wallet_balance_cents' => 1000]);
        $user->assignRole('user');
        $token = ApiToken::generate($user, 'Read only', scope: 'read_only')['plainText'];

        $this->postJson('/api/v1/offers', [
            'product_name' => 'A', 'product_url' => 'https://a.example', 'affiliate_network' => 'ShareASale',
        ], $this->authHeaders($token))->assertStatus(403);

        $this->assertSame(1000, $user->fresh()->api_wallet_balance_cents);
    }

    public function test_a_read_only_token_cannot_update_a_contact(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');
        $contact = CrmContact::create(['user_id' => $user->id, 'name' => 'Existing', 'source' => 'manual', 'status' => 'new']);
        $token = ApiToken::generate($user, 'Read only', scope: 'read_only')['plainText'];

        $this->patchJson("/api/v1/crm-contacts/{$contact->id}", ['status' => 'contacted'], $this->authHeaders($token))
            ->assertStatus(403);
    }

    public function test_a_full_scope_token_is_unaffected(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');
        $token = ApiToken::generate($user, 'Full')['plainText'];

        $this->postJson('/api/v1/crm-contacts', ['name' => 'Lead'], $this->authHeaders($token))->assertStatus(201);
    }

    public function test_a_token_created_before_this_feature_defaults_to_full_scope(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');
        // Simulates a pre-migration row: generate() always sets scope now,
        // so force it back to a bare insert the way the old code did.
        $plainText = 'aff_legacytoken1234567890123456789012345678';
        ApiToken::query()->insert([
            'user_id' => $user->id, 'type' => 'user', 'name' => 'Legacy',
            'token_hash' => hash('sha256', $plainText), 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->postJson('/api/v1/crm-contacts', ['name' => 'Lead'], $this->authHeaders($plainText))->assertStatus(201);
    }
}
