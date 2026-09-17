<?php

namespace Tests\Feature;

use App\Filament\Resources\ApiTokens\ApiTokenResource;
use App\Filament\Resources\ApiTokens\Pages\ListApiTokens;
use App\Models\ApiToken;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Task #6: the admin oversight side of API tokens — read-only observation
 * of every issued token, plus the one place a full admin can mint a
 * separate 'admin'-type token for /api/v1/admin/*. See EnsureAdminApiToken
 * for why the UI guard here is defense-in-depth rather than the only line
 * of protection.
 */
class ApiTokenResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
    }

    public function test_a_full_admin_can_generate_an_admin_token_from_the_list_page(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        Livewire::actingAs($admin)
            ->test(ListApiTokens::class)
            ->callAction('generateAdminToken', data: ['name' => 'Internal reporting script']);

        $token = ApiToken::where('name', 'Internal reporting script')->firstOrFail();
        $this->assertSame('admin', $token->type);
        $this->assertSame($admin->id, $token->user_id);
    }

    public function test_the_generate_admin_token_action_is_hidden_from_a_department_scoped_sub_account(): void
    {
        $subAccount = User::factory()->create();
        $subAccount->assignRole('admin_sub');
        $subAccount->syncPermissions(['department.system']);

        Livewire::actingAs($subAccount)
            ->test(ListApiTokens::class)
            ->assertActionHidden('generateAdminToken');
    }

    public function test_the_table_lists_tokens_and_can_revoke_one(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $user = User::factory()->create(['name' => 'Casey']);
        $user->assignRole('user');
        $token = ApiToken::generate($user, 'Zapier')['token'];

        Livewire::actingAs($admin)
            ->test(ListApiTokens::class)
            ->assertCanSeeTableRecords([$token])
            ->callTableAction('delete', $token);

        $this->assertDatabaseMissing('api_tokens', ['id' => $token->id]);
    }

    public function test_a_department_scoped_sub_account_without_system_access_cannot_reach_the_resource(): void
    {
        $subAccount = User::factory()->create();
        $subAccount->assignRole('admin_sub');
        $subAccount->syncPermissions([]);

        $this->actingAs($subAccount);
        $this->assertFalse(ApiTokenResource::canAccess());

        $subAccount->syncPermissions(['department.system']);
        $this->assertTrue(ApiTokenResource::canAccess());
    }
}
