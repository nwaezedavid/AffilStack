<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * API roadmap item #4 — the published OpenAPI spec (see
 * OpenApiSpecController and resources/openapi/openapi.yaml).
 */
class OpenApiSpecTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
    }

    public function test_the_spec_is_publicly_reachable_without_a_token(): void
    {
        $response = $this->get('/api/v1/openapi.yaml');

        $response->assertOk();
        $this->assertStringContainsString('application/yaml', $response->headers->get('Content-Type'));
    }

    public function test_the_spec_describes_the_real_v1_routes(): void
    {
        $body = $this->get('/api/v1/openapi.yaml')->getContent();

        $this->assertStringContainsString('openapi:', $body);
        $this->assertStringContainsString('/v1/offers/{offer}:', $body);
        $this->assertStringContainsString('/v1/crm-contacts/bulk:', $body);
        $this->assertStringContainsString('Idempotency-Key', $body);
        $this->assertStringContainsString('bearerAuth', $body);
    }

    public function test_the_dashboard_links_to_the_spec(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');

        $this->actingAs($user)->get(route('api-access.index'))
            ->assertOk()
            ->assertSee('/api/v1/openapi.yaml', false);
    }
}
