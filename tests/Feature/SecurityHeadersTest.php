<?php

namespace Tests\Feature;

use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use ReflectionMethod;
use Tests\TestCase;

/**
 * SecurityHeaders is global middleware (bootstrap/app.php) applied to every
 * request, web and api, before routing — see its own docblock. This covers
 * the dotfile-blocking half of it: isDotfileRequest() used to only match the
 * exact paths HawkScan happened to probe (.env, .htaccess, .git*), which
 * left .env.backup and .env.production (both real, gitignored files — see
 * .gitignore) reachable through this PHP-level guard on any front-end that
 * doesn't already block dotfiles itself the way public/.htaccess does for
 * Apache.
 */
class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    public function test_dot_env_is_blocked(): void
    {
        $this->get('/.env')->assertNotFound();
    }

    public function test_dot_env_backup_is_blocked(): void
    {
        $this->get('/.env.backup')->assertNotFound();
    }

    public function test_dot_env_production_is_blocked(): void
    {
        $this->get('/.env.production')->assertNotFound();
    }

    public function test_dot_htaccess_is_blocked(): void
    {
        $this->get('/.htaccess')->assertNotFound();
    }

    public function test_dot_git_config_is_blocked(): void
    {
        $this->get('/.git/config')->assertNotFound();
    }

    public function test_a_dotfile_nested_under_a_real_looking_path_is_blocked(): void
    {
        $this->get('/storage/.env')->assertNotFound();
    }

    public function test_a_legitimate_route_is_not_blocked(): void
    {
        $this->get('/')->assertOk();
    }

    /**
     * An end-to-end 404 can't tell "blocked by the dotfile guard" apart
     * from "no such route exists" — both look identical over HTTP. This
     * exercises the guard's own decision directly: a dot inside a real
     * filename (main.abc123.js) must NOT be treated as a dotfile — only a
     * dot at the START of a path segment (.env, .git/config, foo/.env) may.
     */
    public function test_is_dotfile_request_is_false_for_a_dot_that_is_not_leading_a_segment(): void
    {
        $this->assertFalse($this->isDotfileRequest('/build/assets/app.abc123.js'));
        $this->assertFalse($this->isDotfileRequest('/'));
        $this->assertFalse($this->isDotfileRequest('/dashboard'));
    }

    public function test_is_dotfile_request_is_true_for_a_dot_leading_any_segment(): void
    {
        $this->assertTrue($this->isDotfileRequest('/.env'));
        $this->assertTrue($this->isDotfileRequest('/storage/.env'));
        $this->assertTrue($this->isDotfileRequest('/.git/config'));
    }

    private function isDotfileRequest(string $path): bool
    {
        $method = new ReflectionMethod(SecurityHeaders::class, 'isDotfileRequest');

        return $method->invoke(new SecurityHeaders, Request::create($path));
    }
}
