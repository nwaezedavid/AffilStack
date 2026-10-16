<?php

namespace Tests\Feature;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\MarketplaceTestHelpers;
use Tests\TestCase;

/**
 * Feature 7: on developer.affilstack.com the portal has its own session,
 * so developers sign in through the app, which hands back a one-time token.
 * Boots the app with the three hosts set, as on the server.
 */
class DeveloperPortalSsoTest extends TestCase
{
    use MarketplaceTestHelpers;
    use RefreshDatabase;

    protected const APP = 'http://app.affilstack.test';

    protected const DEVELOPER = 'http://developer.affilstack.test';

    public function createApplication(): Application
    {
        $app = require __DIR__.'/../../bootstrap/app.php';

        $app->afterBootstrapping(LoadConfiguration::class, function (Application $app): void {
            $app['config']->set('app.url', 'http://affilstack.test');
            $app['config']->set('app.app_domain', 'app.affilstack.test');
            $app['config']->set('fortify.domain', 'app.affilstack.test');
            $app['config']->set('marketplace.developer_domain', 'developer.affilstack.test');
        });

        $app->make(Kernel::class)->bootstrap();

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedMarketplaceRoles();
    }

    public function test_the_portal_lives_on_its_own_host(): void
    {
        $this->assertSame(self::DEVELOPER.'/dashboard', route('developer.dashboard'));
        $this->get(self::DEVELOPER.'/')->assertOk()->assertSee('Build what affiliate marketers need next.');
        $this->get(self::DEVELOPER.'/dashboard')->assertRedirect(self::DEVELOPER.'/sign-in');
    }

    public function test_signing_in_goes_through_the_app_and_back(): void
    {
        $developer = $this->developer();

        $start = $this->get(self::DEVELOPER.'/sign-in');
        $location = (string) $start->headers->get('Location');
        $this->assertStringStartsWith(self::APP.'/developer-sso?state=', $location);
        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);

        $vouch = $this->actingAs($developer)->get($location);
        $callback = (string) $vouch->headers->get('Location');
        $this->assertStringStartsWith(self::DEVELOPER.'/sso/callback?', $callback);

        $this->app['auth']->forgetGuards();

        $this->get($callback)->assertRedirect(self::DEVELOPER.'/dashboard');
        $this->assertAuthenticatedAs($developer);

        // The token works once.
        $this->post(self::DEVELOPER.'/sign-out')->assertRedirect(self::DEVELOPER);
        $this->app['auth']->forgetGuards();
        $this->withSession(['developer_sso_state' => $query['state']])->get($callback)->assertRedirect(self::DEVELOPER);
    }

    public function test_a_callback_without_the_matching_state_is_refused(): void
    {
        $developer = $this->developer();

        $this->get(self::DEVELOPER.'/sign-in');
        $vouch = $this->actingAs($developer)->get(self::APP.'/developer-sso?state='.str_repeat('a', 40));
        $this->app['auth']->forgetGuards();

        // Someone else's sign-in link (a different state) doesn't sign this browser in.
        $this->get((string) $vouch->headers->get('Location'))->assertRedirect(self::DEVELOPER)->assertSessionHas('error');
        $this->assertGuest();
    }
}
