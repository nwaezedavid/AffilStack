<?php

namespace Tests\Feature;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "http://www.affilstack.com and https://www.affilstack.com is showing
 * error 404 instead of redirecting to the main url https://affilstack.com."
 *
 * Boots with the production host split (APP_URL + APP_DOMAIN), where the
 * website routes are bound to the APP_URL host only.
 */
class WwwRedirectTest extends TestCase
{
    use RefreshDatabase;

    public function createApplication(): Application
    {
        $app = require __DIR__.'/../../bootstrap/app.php';

        $app->afterBootstrapping(LoadConfiguration::class, function (Application $app): void {
            $app['config']->set('app.url', 'https://affilstack.test');
            $app['config']->set('app.app_domain', 'app.affilstack.test');
            $app['config']->set('fortify.domain', 'app.affilstack.test');
        });

        $app->make(Kernel::class)->bootstrap();

        return $app;
    }

    public function test_www_home_page_redirects_permanently_to_the_main_address(): void
    {
        $this->get('https://www.affilstack.test/')
            ->assertStatus(301)
            ->assertRedirect('https://affilstack.test/');
    }

    public function test_plain_http_www_also_lands_on_the_https_main_address(): void
    {
        $this->get('http://www.affilstack.test/')
            ->assertStatus(301)
            ->assertRedirect('https://affilstack.test/');
    }

    public function test_path_and_query_string_are_kept(): void
    {
        $this->get('https://www.affilstack.test/pricing?ref=abc&utm_source=x')
            ->assertStatus(301)
            ->assertRedirect('https://affilstack.test/pricing?ref=abc&utm_source=x');

        $this->get('https://www.affilstack.test/fr/pricing')
            ->assertRedirect('https://affilstack.test/fr/pricing');
    }

    public function test_form_posts_keep_their_method_with_a_308(): void
    {
        $this->post('https://www.affilstack.test/contact')
            ->assertStatus(308)
            ->assertRedirect('https://affilstack.test/contact');
    }

    public function test_main_address_and_app_subdomain_are_not_redirected(): void
    {
        $this->get('https://affilstack.test/')->assertOk();

        $response = $this->get('https://app.affilstack.test/login');
        $response->assertOk();
    }

    public function test_other_hosts_are_left_alone(): void
    {
        $this->get('https://www.other-site.test/')->assertStatus(404);
    }
}
