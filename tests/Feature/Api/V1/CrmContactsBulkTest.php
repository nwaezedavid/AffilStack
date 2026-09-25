<?php

namespace Tests\Feature\Api\V1;

use App\Models\ApiToken;
use App\Models\CrmContact;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * API roadmap item #6 — POST /crm-contacts/bulk (see
 * CrmContactsController::bulkStore()). Partial-success: valid items are
 * created even when others in the same batch fail.
 */
class CrmContactsBulkTest extends TestCase
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

    public function test_it_bulk_creates_every_valid_contact(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');
        $token = ApiToken::generate($user, 'Test')['plainText'];

        $response = $this->postJson('/api/v1/crm-contacts/bulk', [
            'contacts' => [
                ['name' => 'Lead One', 'email' => 'one@example.com'],
                ['name' => 'Lead Two', 'email' => 'two@example.com'],
                ['name' => 'Lead Three'],
            ],
        ], $this->authHeaders($token));

        $response->assertOk();
        $this->assertCount(3, $response->json('created'));
        $this->assertCount(0, $response->json('failed'));
        $this->assertSame(3, CrmContact::where('user_id', $user->id)->count());
    }

    public function test_an_invalid_item_is_reported_by_index_without_blocking_the_valid_ones(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');
        $token = ApiToken::generate($user, 'Test')['plainText'];

        $response = $this->postJson('/api/v1/crm-contacts/bulk', [
            'contacts' => [
                ['name' => 'Good Lead', 'email' => 'good@example.com'],
                ['name' => 'Bad Lead', 'email' => 'not-an-email'],
            ],
        ], $this->authHeaders($token));

        $response->assertOk();
        $this->assertCount(1, $response->json('created'));
        $this->assertCount(1, $response->json('failed'));
        $this->assertSame(1, $response->json('failed.0.index'));
        $this->assertArrayHasKey('email', $response->json('failed.0.errors'));
    }

    public function test_a_batch_over_the_configured_max_is_rejected_up_front(): void
    {
        config(['api_billing.bulk_max_items' => 2]);
        $user = User::factory()->create();
        $user->assignRole('user');
        $token = ApiToken::generate($user, 'Test')['plainText'];

        $response = $this->postJson('/api/v1/crm-contacts/bulk', [
            'contacts' => [['name' => 'A'], ['name' => 'B'], ['name' => 'C']],
        ], $this->authHeaders($token));

        $response->assertStatus(422);
        $this->assertDatabaseCount('crm_contacts', 0);
    }

    public function test_hitting_the_plan_contact_limit_mid_batch_reports_the_remaining_items_as_failed(): void
    {
        $plan = Plan::factory()->create(['contact_limit' => 2]);
        $user = User::factory()->create();
        $user->assignRole('user');
        Subscription::factory()->create(['user_id' => $user->id, 'plan_id' => $plan->id]);
        $token = ApiToken::generate($user, 'Test')['plainText'];

        $response = $this->postJson('/api/v1/crm-contacts/bulk', [
            'contacts' => [['name' => 'A'], ['name' => 'B'], ['name' => 'C']],
        ], $this->authHeaders($token));

        $response->assertOk();
        $this->assertCount(2, $response->json('created'));
        $this->assertCount(1, $response->json('failed'));
        $this->assertSame(2, $response->json('failed.0.index'));
    }

    public function test_a_read_only_token_cannot_bulk_create(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');
        $token = ApiToken::generate($user, 'Read only', scope: 'read_only')['plainText'];

        $this->postJson('/api/v1/crm-contacts/bulk', ['contacts' => [['name' => 'A']]], $this->authHeaders($token))
            ->assertStatus(403);
    }

    public function test_a_sandbox_tokens_bulk_created_contacts_are_flagged_sandbox_and_ignore_the_plan_limit(): void
    {
        $plan = Plan::factory()->create(['contact_limit' => 1]);
        $user = User::factory()->create();
        $user->assignRole('user');
        Subscription::factory()->create(['user_id' => $user->id, 'plan_id' => $plan->id]);
        $token = ApiToken::generate($user, 'Sandbox', isSandbox: true)['plainText'];

        $response = $this->postJson('/api/v1/crm-contacts/bulk', [
            'contacts' => [['name' => 'A'], ['name' => 'B'], ['name' => 'C']],
        ], $this->authHeaders($token));

        $response->assertOk();
        $this->assertCount(3, $response->json('created'));
        $this->assertTrue(collect($response->json('created'))->every(fn ($c) => $c['is_sandbox'] === true));
    }
}
