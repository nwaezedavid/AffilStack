<?php

namespace Tests\Feature;

use App\Models\CrmContact;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Audit gap #3: the CRM Contacts list had no search or filter at all.
 */
class CrmSearchAndFilterTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
        $this->user = User::factory()->create();
        $this->user->assignRole('user');
    }

    public function test_searching_by_name_narrows_the_list(): void
    {
        CrmContact::create(['user_id' => $this->user->id, 'name' => 'Jordan Smith', 'source' => 'manual', 'status' => 'new']);
        CrmContact::create(['user_id' => $this->user->id, 'name' => 'Alex Rivera', 'source' => 'manual', 'status' => 'new']);

        $response = $this->actingAs($this->user)->get(route('crm.index', ['q' => 'jordan']));

        $response->assertOk()->assertSee('Jordan Smith')->assertDontSee('Alex Rivera');
    }

    public function test_searching_by_company_or_email_also_matches(): void
    {
        CrmContact::create(['user_id' => $this->user->id, 'name' => 'Jordan Smith', 'company' => 'Acme Corp', 'source' => 'manual', 'status' => 'new']);
        CrmContact::create(['user_id' => $this->user->id, 'name' => 'Alex Rivera', 'email' => 'alex@zenith.example', 'source' => 'manual', 'status' => 'new']);

        $this->actingAs($this->user)->get(route('crm.index', ['q' => 'acme']))->assertOk()->assertSee('Jordan Smith');
        $this->actingAs($this->user)->get(route('crm.index', ['q' => 'zenith']))->assertOk()->assertSee('Alex Rivera');
    }

    public function test_filtering_by_status_narrows_the_list(): void
    {
        CrmContact::create(['user_id' => $this->user->id, 'name' => 'Jordan Smith', 'source' => 'manual', 'status' => 'customer']);
        CrmContact::create(['user_id' => $this->user->id, 'name' => 'Alex Rivera', 'source' => 'manual', 'status' => 'new']);

        $response = $this->actingAs($this->user)->get(route('crm.index', ['status' => 'customer']));

        $response->assertOk()->assertSee('Jordan Smith')->assertDontSee('Alex Rivera');
    }

    public function test_the_pipeline_stats_header_still_reflects_the_whole_pipeline_when_filtered(): void
    {
        CrmContact::create(['user_id' => $this->user->id, 'name' => 'Jordan Smith', 'source' => 'manual', 'status' => 'customer']);
        CrmContact::create(['user_id' => $this->user->id, 'name' => 'Alex Rivera', 'source' => 'manual', 'status' => 'new']);

        $response = $this->actingAs($this->user)->get(route('crm.index', ['status' => 'customer']));

        $response->assertOk();
        $response->assertViewHas('stats', fn ($stats) => $stats['total'] === 2);
        $response->assertViewHas('contacts', fn ($contacts) => $contacts->total() === 1);
    }

    public function test_search_never_returns_another_users_contacts(): void
    {
        $other = User::factory()->create();
        $other->assignRole('user');
        CrmContact::create(['user_id' => $other->id, 'name' => 'Jordan Smith', 'source' => 'manual', 'status' => 'new']);

        $this->actingAs($this->user)
            ->get(route('crm.index', ['q' => 'jordan']))
            ->assertOk()
            ->assertSee('No contacts match your search');
    }
}
