<?php

namespace Tests\Feature;

use App\Filament\Resources\NotFoundLogs\Pages\ListNotFoundLogs;
use App\Filament\Resources\Redirects\Pages\CreateRedirect;
use App\Models\NotFoundLog;
use App\Models\Redirect;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * RankMath's "Redirections" + "404 Monitor" — a dead/changed URL sends
 * visitors somewhere real instead of a bare 404 (Redirect,
 * RedirectFallbackController), and every genuine 404 is logged (deduped,
 * capped) so an admin can turn a real pattern into a redirect from the
 * dashboard (NotFoundLog).
 */
class RedirectsAndNotFoundMonitorTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');
    }

    public function test_an_unmatched_path_is_logged_and_returns_a_real_404(): void
    {
        $response = $this->get('/this-page-does-not-exist');

        $response->assertNotFound();

        $log = NotFoundLog::where('path', 'this-page-does-not-exist')->first();
        $this->assertNotNull($log);
        $this->assertSame(1, $log->hits_count);
    }

    public function test_repeat_hits_to_the_same_path_increment_the_existing_row_instead_of_duplicating(): void
    {
        $this->get('/dead-link');
        $this->get('/dead-link');
        $this->get('/dead-link');

        $this->assertSame(1, NotFoundLog::where('path', 'dead-link')->count());
        $this->assertSame(3, NotFoundLog::where('path', 'dead-link')->value('hits_count'));
    }

    public function test_the_404_log_stops_growing_once_it_hits_its_cap_but_still_increments_existing_paths(): void
    {
        $now = now();
        $rows = [];
        for ($i = 0; $i < 500; $i++) {
            $rows[] = ['path' => "bot-scan-{$i}", 'hits_count' => 1, 'first_seen_at' => $now, 'last_seen_at' => $now];
        }
        NotFoundLog::insert($rows);

        $this->get('/bot-scan-0');
        $this->get('/a-brand-new-path-past-the-cap');

        $this->assertSame(2, NotFoundLog::where('path', 'bot-scan-0')->value('hits_count'));
        $this->assertSame(0, NotFoundLog::where('path', 'a-brand-new-path-past-the-cap')->count());
        $this->assertSame(500, NotFoundLog::count());
    }

    public function test_a_configured_redirect_sends_visitors_to_the_new_location_and_records_a_hit(): void
    {
        $redirect = Redirect::create(['from_path' => 'old-page', 'to_path' => '/pricing', 'status_code' => 301]);

        $response = $this->get('/old-page');

        $response->assertRedirect('/pricing');
        $response->assertStatus(301);
        $this->assertSame(1, $redirect->fresh()->hits_count);
        $this->assertNotNull($redirect->fresh()->last_hit_at);

        // A matched redirect is never treated as a 404.
        $this->assertSame(0, NotFoundLog::where('path', 'old-page')->count());
    }

    public function test_a_redirect_hit_is_never_logged_as_a_404(): void
    {
        Redirect::create(['from_path' => 'moved', 'to_path' => '/help', 'status_code' => 302]);

        $this->get('/moved')->assertRedirect('/help')->assertStatus(302);

        $this->assertSame(0, NotFoundLog::count());
    }

    public function test_the_admin_panel_path_is_never_redirect_checked_or_logged(): void
    {
        $response = $this->get('/afs-login/some-nonexistent-page');

        $response->assertNotFound();
        $this->assertSame(0, NotFoundLog::count());
    }

    public function test_api_and_webhook_prefixes_are_never_redirect_checked_or_logged(): void
    {
        $this->getJson('/api/some-nonexistent-endpoint')->assertNotFound();
        $this->get('/webhooks/some-nonexistent-provider')->assertNotFound();

        $this->assertSame(0, NotFoundLog::count());
    }

    public function test_admin_can_create_and_edit_a_redirect_from_filament(): void
    {
        Livewire::actingAs($this->admin)
            ->test(CreateRedirect::class)
            ->fillForm([
                'from_path' => '/promo-2025',
                'to_path' => '/pricing',
                'status_code' => 301,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $redirect = Redirect::where('to_path', '/pricing')->first();
        $this->assertNotNull($redirect);
        // The leading slash is stripped so it matches request()->path()'s shape.
        $this->assertSame('promo-2025', $redirect->from_path);
    }

    public function test_admin_can_turn_a_logged_404_into_a_redirect_from_the_monitor(): void
    {
        $log = NotFoundLog::create([
            'path' => 'old-campaign-link',
            'hits_count' => 4,
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);

        Livewire::actingAs($this->admin)
            ->test(ListNotFoundLogs::class)
            ->assertSuccessful()
            ->callTableAction('createRedirect', $log, data: ['to_path' => '/pricing', 'status_code' => 301]);

        $redirect = Redirect::where('from_path', 'old-campaign-link')->first();
        $this->assertNotNull($redirect);
        $this->assertSame('/pricing', $redirect->to_path);

        // The path now resolves via the redirect instead of 404ing again.
        $this->get('/old-campaign-link')->assertRedirect('/pricing');
    }

    public function test_a_non_admin_cannot_access_the_redirects_or_404_monitor_pages(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');

        Livewire::actingAs($user)
            ->test(CreateRedirect::class)
            ->assertForbidden();
    }
}
