<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The admin panel's URL was deliberately moved off the default /admin path
 * to /afs-login (a security-through-obscurity request, not a permissions
 * change — panel access control is unaffected). This just locks in that the
 * new path resolves and the old one doesn't leak the panel anymore.
 */
class AdminLoginPathTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_admin_panel_is_reachable_at_the_new_afs_login_path(): void
    {
        $this->get('/afs-login/login')->assertOk();
    }

    public function test_the_old_default_admin_path_no_longer_resolves(): void
    {
        $this->get('/admin/login')->assertNotFound();
        $this->get('/admin')->assertNotFound();
    }
}
