<?php

namespace Tests\Feature;

use App\Filament\Resources\AdminSubAccounts\AdminSubAccountResource;
use App\Filament\Resources\AdminSubAccounts\Pages\CreateAdminSubAccount;
use App\Filament\Resources\AdminSubAccounts\Pages\EditAdminSubAccount;
use App\Filament\Resources\Plans\PlanResource;
use App\Filament\Resources\SupportTickets\SupportTicketResource;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Filament\Panel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Admin sub-accounts: department-scoped staff logins (role "admin_sub").
 * Only super-admin can create/manage them (AdminSubAccountResource); each
 * one sees exactly the Filament nav groups/resources its granted
 * departments cover (App\Filament\Concerns\ScopedToDepartment) and nothing
 * else — full admins ('admin'/'super-admin') always bypass the scoping.
 */
class AdminSubAccountsTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
        $this->superAdmin = User::factory()->create();
        $this->superAdmin->assignRole(['admin', 'super-admin']);
    }

    protected function subAccount(array $departments = []): User
    {
        $user = User::factory()->create();
        $user->assignRole('admin_sub');
        $user->syncPermissions(collect($departments)->map(fn (string $d) => "department.{$d}")->all());

        return $user;
    }

    public function test_super_admin_can_create_a_sub_account_scoped_to_chosen_departments(): void
    {
        Livewire::actingAs($this->superAdmin)
            ->test(CreateAdminSubAccount::class)
            ->fillForm([
                'name' => 'Support Staffer',
                'email' => 'staffer@example.com',
                'password' => 'a-strong-password',
                'departments' => ['support', 'content'],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $created = User::where('email', 'staffer@example.com')->firstOrFail();
        $this->assertTrue($created->hasRole('admin_sub'));
        $this->assertTrue($created->canAccessDepartment('support'));
        $this->assertTrue($created->canAccessDepartment('content'));
        $this->assertFalse($created->canAccessDepartment('billing'));
        $this->assertFalse($created->isFullAdmin());
    }

    public function test_super_admin_can_change_a_sub_accounts_departments(): void
    {
        $sub = $this->subAccount(['support']);

        Livewire::actingAs($this->superAdmin)
            ->test(EditAdminSubAccount::class, ['record' => $sub->getKey()])
            ->fillForm(['departments' => ['billing']])
            ->call('save')
            ->assertHasNoFormErrors();

        $sub->refresh();
        $this->assertFalse($sub->canAccessDepartment('support'));
        $this->assertTrue($sub->canAccessDepartment('billing'));
    }

    public function test_a_full_admin_who_is_not_super_admin_cannot_access_the_sub_accounts_screen(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($admin);
        $this->assertFalse(AdminSubAccountResource::canAccess());
    }

    public function test_a_sub_account_can_reach_a_resource_in_its_department_but_not_one_outside_it(): void
    {
        $sub = $this->subAccount(['support']);

        $this->actingAs($sub);
        $this->assertTrue(SupportTicketResource::canAccess());
        $this->assertFalse(PlanResource::canAccess());
    }

    public function test_a_full_admin_bypasses_department_scoping_entirely(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($admin);
        $this->assertTrue(SupportTicketResource::canAccess());
        $this->assertTrue(PlanResource::canAccess());
    }

    public function test_a_sub_account_with_no_departments_cannot_reach_any_scoped_resource(): void
    {
        $sub = $this->subAccount([]);

        $this->actingAs($sub);
        $this->assertFalse(SupportTicketResource::canAccess());
    }

    public function test_a_suspended_sub_account_cannot_access_the_panel_at_all(): void
    {
        $sub = $this->subAccount(['support']);
        $sub->update(['is_suspended' => true]);

        $this->assertFalse($sub->canAccessPanel(app(Panel::class)));
    }

    public function test_the_users_resource_excludes_admin_sub_accounts(): void
    {
        $sub = $this->subAccount(['support']);
        $customer = User::factory()->create();

        $ids = UserResource::getEloquentQuery()->pluck('id');

        $this->assertFalse($ids->contains($sub->id));
        $this->assertTrue($ids->contains($customer->id));
    }

    public function test_only_super_admin_sees_the_roles_field_on_the_user_form(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $customer = User::factory()->create();

        Livewire::actingAs($this->superAdmin)
            ->test(EditUser::class, ['record' => $customer->getKey()])
            ->assertFormFieldExists('roles');

        Livewire::actingAs($admin)
            ->test(EditUser::class, ['record' => $customer->getKey()])
            ->assertFormFieldDoesNotExist('roles');
    }
}
