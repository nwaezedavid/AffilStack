<?php

namespace Tests\Feature\Api\V1;

use App\Models\ApiToken;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Task #6: /api/v1/admin/* is gated by EnsureAdminApiToken, which requires
 * BOTH the token's own type === 'admin' AND a live isFullAdmin() check on
 * the token's owner — see the middleware's docblock for why the live check
 * matters (a demoted admin's old admin token must stop working immediately).
 */
class AdminApiTest extends TestCase
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

    public function test_an_admin_type_token_belonging_to_a_full_admin_can_reach_the_admin_api(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $plainText = ApiToken::generate($admin, 'Reporting script', 'admin')['plainText'];

        $this->getJson('/api/v1/admin/revenue', $this->authHeaders($plainText))->assertOk();
    }

    public function test_a_user_type_token_cannot_reach_the_admin_api_even_if_its_owner_is_a_full_admin(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $plainText = ApiToken::generate($admin, 'Ordinary token', 'user')['plainText'];

        $this->getJson('/api/v1/admin/revenue', $this->authHeaders($plainText))->assertStatus(403);
    }

    public function test_an_admin_type_token_belonging_to_a_non_admin_is_rejected(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');
        // Simulates a mislabeled/tampered row — the live isFullAdmin() check
        // on the middleware neutralizes this regardless of the token's type.
        $plainText = ApiToken::generate($user, 'Suspicious token', 'admin')['plainText'];

        $this->getJson('/api/v1/admin/revenue', $this->authHeaders($plainText))->assertStatus(403);
    }

    public function test_an_admin_demoted_after_minting_a_token_immediately_loses_admin_api_access(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $plainText = ApiToken::generate($admin, 'Reporting script', 'admin')['plainText'];

        $this->getJson('/api/v1/admin/revenue', $this->authHeaders($plainText))->assertOk();

        $admin->syncRoles([]);

        $this->getJson('/api/v1/admin/revenue', $this->authHeaders($plainText))->assertStatus(403);
    }

    public function test_admin_endpoints_return_expected_shapes(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $token = $this->authHeaders(ApiToken::generate($admin, 'Reporting script', 'admin')['plainText']);

        $this->getJson('/api/v1/admin/users', $token)->assertOk();
        $this->getJson('/api/v1/admin/plans', $token)->assertOk();
        $this->getJson('/api/v1/admin/referral-payouts', $token)->assertOk();
        $this->getJson('/api/v1/admin/revenue', $token)->assertOk()->assertJsonStructure([
            'mrr_cents', 'revenue_this_month_cents', 'active_subscriptions', 'total_users',
        ]);
    }
}
