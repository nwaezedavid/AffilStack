<?php

namespace Tests\Feature;

use App\Filament\Resources\NotFoundLogs\NotFoundLogResource;
use App\Filament\Resources\NotFoundLogs\Pages\ListNotFoundLogs;
use App\Filament\Resources\NotFoundLogs\Pages\ViewNotFoundLog;
use App\Models\NotFoundLog;
use App\Models\Redirect;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The 404 Monitor's dedicated detail page (ViewNotFoundLog), added
 * alongside the rest of the statistics work: "when expanded, it should
 * have its own page where I can see details of each 404 error and also be
 * able to redirect the link to any page I prefer." The list's own quick
 * action (NotFoundLogsTable) is covered implicitly here since both share
 * CreateRedirectAction.
 */
class NotFoundLogAdminUiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
    }

    public function test_a_department_scoped_sub_account_without_site_access_cannot_reach_it(): void
    {
        $subAccount = User::factory()->create();
        $subAccount->assignRole('admin_sub');
        $subAccount->syncPermissions([]);

        $this->actingAs($subAccount);
        $this->assertFalse(NotFoundLogResource::canAccess());

        $subAccount->syncPermissions(['department.site']);
        $this->assertTrue(NotFoundLogResource::canAccess());
    }

    public function test_admin_can_view_the_detail_page_for_a_logged_404(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $log = NotFoundLog::create([
            'path' => 'old-page',
            'referer' => 'https://example.com/',
            'hits_count' => 5,
            'first_seen_at' => now()->subDay(),
            'last_seen_at' => now(),
        ]);

        Livewire::actingAs($admin)
            ->test(ViewNotFoundLog::class, ['record' => $log->getKey()])
            ->assertSuccessful()
            ->assertSee('old-page')
            ->assertSee('5');
    }

    public function test_creating_a_redirect_from_the_detail_page_creates_the_redirect(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $log = NotFoundLog::create([
            'path' => 'old-page',
            'referer' => null,
            'hits_count' => 1,
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);

        Livewire::actingAs($admin)
            ->test(ViewNotFoundLog::class, ['record' => $log->getKey()])
            ->callAction('createRedirect', data: ['to_path' => '/pricing', 'status_code' => 301])
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('redirects', [
            'from_path' => 'old-page',
            'to_path' => '/pricing',
            'status_code' => 301,
        ]);
    }

    public function test_creating_a_redirect_from_the_list_table_still_works(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $log = NotFoundLog::create([
            'path' => 'legacy',
            'referer' => null,
            'hits_count' => 1,
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);

        Livewire::actingAs($admin)
            ->test(ListNotFoundLogs::class)
            ->callTableAction('createRedirect', $log, data: ['to_path' => '/help', 'status_code' => 302])
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('redirects', [
            'from_path' => 'legacy',
            'to_path' => '/help',
            'status_code' => 302,
        ]);
    }

    public function test_a_cyclical_redirect_is_rejected(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        Redirect::create(['from_path' => 'b', 'to_path' => '/a', 'status_code' => 301]);
        $log = NotFoundLog::create([
            'path' => 'a',
            'referer' => null,
            'hits_count' => 1,
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);

        Livewire::actingAs($admin)
            ->test(ViewNotFoundLog::class, ['record' => $log->getKey()])
            ->callAction('createRedirect', data: ['to_path' => '/b', 'status_code' => 301]);

        $this->assertDatabaseMissing('redirects', ['from_path' => 'a']);
    }
}
