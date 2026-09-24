<?php

namespace Tests\Feature;

use App\Filament\Pages\GitHubSyncSettings;
use App\Models\GitHubSyncRun;
use App\Models\GitHubSyncSetting;
use App\Models\User;
use App\Services\GitHub\GitHubSyncService;
use App\Services\Settings\ConnectionAdvisor;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

/**
 * Pushing code to a PAT-controlled GitHub repo is one of the most sensitive
 * things this admin dashboard can do, so it's gated at least as tightly as
 * AdminSubAccountResource (owner/super-admin only, always — see
 * GitHubSyncSettings::canAccess()) rather than the general "system"
 * department a regular admin sub-account could otherwise be granted.
 *
 * These tests never touch git or the real GitHub API — GitHubSyncService
 * itself is covered end-to-end (including the throwaway-repo push and the
 * .env-leak guard) by GitHubSyncServiceTest; here it's swapped for a mock
 * so these tests focus purely on the page's own form/gating/wiring.
 */
class GitHubSyncSettingsPageTest extends TestCase
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

    public function test_a_regular_admin_cannot_access_the_page_but_a_super_admin_can(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($admin);
        $this->assertFalse(GitHubSyncSettings::canAccess());

        $this->actingAs($this->superAdmin);
        $this->assertTrue(GitHubSyncSettings::canAccess());
    }

    public function test_a_department_scoped_sub_account_with_every_department_still_cannot_access_the_page(): void
    {
        $sub = User::factory()->create();
        $sub->assignRole('admin_sub');
        $sub->syncPermissions(collect(array_keys(config('admin.departments')))->map(fn (string $d) => "department.{$d}")->all());

        $this->actingAs($sub);
        $this->assertFalse(GitHubSyncSettings::canAccess());
    }

    public function test_the_personal_access_token_field_never_repopulates_the_stored_token_on_page_load(): void
    {
        GitHubSyncSetting::current()->update([
            'repo_owner' => 'acme',
            'repo_name' => 'affilistack',
            'credentials' => ['personal_access_token' => 'ghp_ExistingRawToken'],
        ]);

        Livewire::actingAs($this->superAdmin)
            ->test(GitHubSyncSettings::class)
            ->assertSet('data.personal_access_token', null)
            ->assertSet('data.repo_owner', 'acme')
            ->assertSet('data.repo_name', 'affilistack');
    }

    public function test_saving_a_new_token_is_encrypted_at_rest_and_readable_via_the_credential_accessor(): void
    {
        Livewire::actingAs($this->superAdmin)
            ->test(GitHubSyncSettings::class)
            ->fillForm([
                'is_enabled' => true,
                'repo_owner' => 'acme',
                'repo_name' => 'affilistack',
                'branch' => 'main',
                'personal_access_token' => 'ghp_BrandNewSuperSecretToken',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $settings = GitHubSyncSetting::current();
        $this->assertTrue($settings->is_enabled);
        $this->assertSame('acme', $settings->repo_owner);
        $this->assertSame('ghp_BrandNewSuperSecretToken', $settings->credential('personal_access_token'));

        $raw = \DB::table('github_sync_settings')->where('id', 1)->value('credentials');
        $this->assertStringNotContainsString('ghp_BrandNewSuperSecretToken', (string) $raw);
    }

    public function test_saving_with_a_blank_token_field_keeps_the_existing_token_but_still_updates_other_fields(): void
    {
        GitHubSyncSetting::current()->update([
            'repo_owner' => 'acme',
            'repo_name' => 'old-repo',
            'branch' => 'main',
            'credentials' => ['personal_access_token' => 'ghp_KeepMeAroundToken'],
        ]);

        Livewire::actingAs($this->superAdmin)
            ->test(GitHubSyncSettings::class)
            ->fillForm([
                'repo_name' => 'new-repo',
                'personal_access_token' => '',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $settings = GitHubSyncSetting::current();
        $this->assertSame('new-repo', $settings->repo_name);
        $this->assertSame('ghp_KeepMeAroundToken', $settings->credential('personal_access_token'));
    }

    public function test_verify_connection_action_persists_the_services_result(): void
    {
        $mock = Mockery::mock(GitHubSyncService::class);
        $mock->shouldReceive('verifyConnection')->once()->andReturn([
            'success' => true,
            'message' => 'Connected to acme/affilistack (default branch: main) with push access.',
        ]);
        $this->app->instance(GitHubSyncService::class, $mock);

        Livewire::actingAs($this->superAdmin)
            ->test(GitHubSyncSettings::class)
            ->fillForm(['repo_owner' => 'acme', 'repo_name' => 'affilistack'])
            ->call('verifyConnection', app(GitHubSyncService::class));

        $settings = GitHubSyncSetting::current();
        $this->assertSame('success', $settings->last_sync_status);
        $this->assertStringContainsString('push access', $settings->last_sync_message);
    }

    public function test_sync_now_action_runs_a_sync_and_shows_its_result(): void
    {
        $run = new GitHubSyncRun([
            'status' => GitHubSyncRun::STATUS_SUCCESS,
            'trigger' => GitHubSyncRun::TRIGGER_MANUAL,
            'message' => 'Synced local changes to GitHub.',
            'commit_sha' => 'abc1234',
        ]);

        $mock = Mockery::mock(GitHubSyncService::class);
        $mock->shouldReceive('sync')->once()->with(Mockery::type(GitHubSyncSetting::class), GitHubSyncRun::TRIGGER_MANUAL)->andReturn($run);
        $this->app->instance(GitHubSyncService::class, $mock);

        Livewire::actingAs($this->superAdmin)
            ->test(GitHubSyncSettings::class)
            ->call('syncNow', app(GitHubSyncService::class));

        // The mock never persisted anything itself (it's a mock) — this
        // just confirms the page called through to the service exactly
        // once with the manual trigger, which Mockery's expectation above
        // already verifies; a real sync's persistence is covered by
        // GitHubSyncServiceTest.
        $this->assertTrue(true);
    }

    public function test_explain_action_calls_the_connection_advisor_after_a_failed_sync(): void
    {
        GitHubSyncSetting::current()->update([
            'last_sync_status' => 'failed',
            'last_sync_at' => now(),
            'last_sync_message' => 'GitHub rejected this token (401 Unauthorized).',
        ]);

        $mock = Mockery::mock(ConnectionAdvisor::class);
        $mock->shouldReceive('explain')
            ->once()
            ->with('GitHub Sync', Mockery::type('string'), 'GitHub rejected this token (401 Unauthorized).')
            ->andReturn('Your token looks invalid or expired — generate a new one and paste it in.');
        $this->app->instance(ConnectionAdvisor::class, $mock);

        Livewire::actingAs($this->superAdmin)
            ->test(GitHubSyncSettings::class)
            ->call('explain', app(ConnectionAdvisor::class))
            ->assertSet('aiExplanation', 'Your token looks invalid or expired — generate a new one and paste it in.');
    }

    public function test_explain_action_does_nothing_when_the_last_sync_was_not_failed_or_blocked(): void
    {
        GitHubSyncSetting::current()->update(['last_sync_status' => 'success']);

        $mock = Mockery::mock(ConnectionAdvisor::class);
        $mock->shouldNotReceive('explain');
        $this->app->instance(ConnectionAdvisor::class, $mock);

        Livewire::actingAs($this->superAdmin)
            ->test(GitHubSyncSettings::class)
            ->call('explain', app(ConnectionAdvisor::class))
            ->assertSet('aiExplanation', null);
    }
}
