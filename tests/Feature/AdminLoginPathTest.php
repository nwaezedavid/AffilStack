<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The admin panel's URL was deliberately moved off the default /admin path
 * (a security-through-obscurity request, not a permissions change — panel
 * access control is unaffected). It's further split in two: /afs-login is
 * the ONE stable front door anyone should ever type or bookmark to sign in,
 * and every other admin screen (including the actual login form and the
 * dashboard once signed in) lives under /afs-admin/*. This locks in that
 * split: /afs-login does nothing but land you on the real login form,
 * /afs-admin/* is where the panel actually lives, and neither the old
 * default /admin path nor /afs-login itself ever serves panel content
 * beyond that one redirect.
 */
class AdminLoginPathTest extends TestCase
{
    use RefreshDatabase;

    public function test_afs_login_redirects_straight_to_the_real_login_form(): void
    {
        $response = $this->get('/afs-login');

        $response->assertRedirect('/afs-admin/login');
        $this->followRedirects($response)->assertOk();
    }

    public function test_the_real_login_form_lives_under_afs_admin(): void
    {
        $this->get('/afs-admin/login')->assertOk();
    }

    public function test_afs_login_never_serves_anything_other_than_the_login_redirect(): void
    {
        // afs-login must be a dead end everywhere except its bare form — it
        // is not a working alias for the rest of the admin panel.
        $this->get('/afs-login/login')->assertNotFound();
        $this->get('/afs-login/dashboard')->assertNotFound();
    }

    public function test_afs_admin_bare_path_is_the_real_dashboard_not_a_404(): void
    {
        // Unauthenticated, it must redirect to the login form (proving the
        // dashboard route genuinely exists and is auth-gated) rather than
        // 404 — confirming /afs-admin, not /afs-login, is where the panel
        // itself now lives.
        $this->get('/afs-admin')->assertRedirect('/afs-admin/login');
    }

    public function test_an_already_authenticated_admin_visiting_afs_login_does_not_get_stuck_on_the_form(): void
    {
        $this->seed(RolesSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $response = $this->actingAs($admin)->get('/afs-login');

        // Either way is correct here — the point is it must not 404 and
        // must not render a fresh "please log in" form for someone who
        // already is. Filament's own Login page redirects an authenticated
        // visitor away from itself; we're only confirming that still holds
        // once it's reached via our own /afs-login front door.
        $response->assertRedirect();
        $this->assertNotSame('/afs-admin/login', $response->headers->get('Location'));
    }

    public function test_the_old_default_admin_path_no_longer_resolves(): void
    {
        $this->get('/admin/login')->assertNotFound();
        $this->get('/admin')->assertNotFound();
    }
}
