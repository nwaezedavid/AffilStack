<?php

namespace Tests\Feature;

use App\Models\Offer;
use App\Models\TrackedLink;
use App\Models\User;
use App\Support\SignedInHint;
use App\Support\SiteDomains;
use Database\Seeders\RolesSeeder;
use Filament\Facades\Filament;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * "We can use app.affilstack.com for the user dashboard … linked to the
 * main domain so that when a logged-in user visits the home page or any
 * page, it will still be connected to their dashboard."
 *
 * Boots the app with APP_DOMAIN set, so the routes are registered for two
 * hosts exactly as in production.
 */
class AppSubdomainTest extends TestCase
{
    use RefreshDatabase;

    protected const MAIN = 'http://affilstack.test';

    protected const APP = 'http://app.affilstack.test';

    /**
     * Boot with APP_URL / APP_DOMAIN as on the server: the config is set
     * right after it loads, before routes, Fortify and Filament register.
     */
    public function createApplication(): Application
    {
        $app = require __DIR__.'/../../bootstrap/app.php';

        $app->afterBootstrapping(LoadConfiguration::class, function (Application $app): void {
            $app['config']->set('app.url', self::MAIN);
            $app['config']->set('app.app_domain', 'app.affilstack.test');
            $app['config']->set('fortify.domain', 'app.affilstack.test');
        });

        $app->make(Kernel::class)->bootstrap();

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
    }

    public function test_links_point_at_the_right_host(): void
    {
        $this->assertTrue(SiteDomains::isSplit());
        $this->assertSame(self::APP.'/dashboard', route('dashboard'));
        $this->assertSame(self::APP.'/login', route('login'));
        $this->assertStringStartsWith(self::APP.'/get-started/', route('registration.form', 1));
        $this->assertSame(self::MAIN, route('home'));
        $this->assertSame(self::MAIN.'/pricing', route('registration.pricing'));
        $this->assertSame(self::APP.'/afs-admin/login', Filament::getPanel('admin')->getLoginUrl());
    }

    public function test_each_host_only_answers_for_its_own_pages(): void
    {
        $this->get(self::MAIN.'/')->assertOk();
        $this->get(self::MAIN.'/pricing')->assertOk();
        $this->get(self::APP.'/login')->assertOk();
        $this->get(self::APP.'/afs-admin/login')->assertOk();

        $this->get(self::MAIN.'/login')->assertNotFound();
        $this->get(self::MAIN.'/dashboard')->assertNotFound();
        $this->get(self::MAIN.'/afs-admin/login')->assertNotFound();
        $this->get(self::APP.'/pricing')->assertNotFound();
        $this->get(self::APP.'/features')->assertNotFound();
    }

    public function test_the_app_host_root_goes_to_the_dashboard_or_login(): void
    {
        $this->get(self::APP.'/')->assertRedirect(self::APP.'/dashboard');
        $this->get(self::APP.'/dashboard')->assertRedirect(self::APP.'/login');
    }

    public function test_shared_links_work_on_both_hosts(): void
    {
        $owner = User::factory()->create();
        $offer = Offer::create(['user_id' => $owner->id, 'product_name' => 'Notion', 'product_url' => 'https://notion.so', 'affiliate_network' => 'Impact', 'status' => 'ready']);
        $link = TrackedLink::create([
            'user_id' => $owner->id,
            'offer_id' => $offer->id,
            'module' => 'blog',
            'code' => 'abc123',
            'destination_url' => 'https://example.com/offer',
        ]);

        $this->get(self::MAIN.'/go/'.$link->code)->assertRedirect('https://example.com/offer?subid=abc123');
        $this->get(self::APP.'/go/'.$link->code)->assertRedirect('https://example.com/offer?subid=abc123');
        $this->post(self::MAIN.'/webhooks/stripe')->assertUnauthorized();
    }

    public function test_signing_in_tells_the_public_website_and_signing_out_returns_there(): void
    {
        $user = User::factory()->create(['password' => Hash::make('secret-password-1')]);
        $user->assignRole('user');

        $login = $this->post(self::APP.'/login', ['email' => $user->email, 'password' => 'secret-password-1']);
        $login->assertRedirect();
        $hint = collect($login->headers->getCookies())->firstWhere(fn ($cookie) => $cookie->getName() === SignedInHint::COOKIE);
        $this->assertNotNull($hint);
        $this->assertSame('.affilstack.test', $hint->getDomain());
        $this->assertTrue($hint->isHttpOnly());

        $logout = $this->actingAs($user)->post(self::APP.'/logout');
        $logout->assertRedirect(self::MAIN.'/');
        $cleared = collect($logout->headers->getCookies())->firstWhere(fn ($cookie) => $cookie->getName() === SignedInHint::COOKIE);
        $this->assertNotNull($cleared);
        $this->assertTrue($cleared->isCleared());
    }

    public function test_the_public_header_shows_dashboard_for_signed_in_visitors(): void
    {
        $this->get(self::MAIN.'/')
            ->assertSee(self::APP.'/login', false)
            ->assertDontSee(self::APP.'/dashboard', false);

        $this->withCookie(SignedInHint::COOKIE, '1')
            ->get(self::MAIN.'/')
            ->assertSee(self::APP.'/dashboard', false)
            ->assertDontSee(self::APP.'/login', false)
            ->assertHeader('Cache-Control', 'no-cache, private');
    }

    public function test_the_app_host_is_never_crawled(): void
    {
        $this->get(self::APP.'/robots.txt')->assertOk()->assertSee('Disallow: /');
        $this->get(self::MAIN.'/robots.txt')->assertOk()->assertSee('Sitemap:');
    }

    public function test_passkeys_and_api_addresses_follow_the_hosts(): void
    {
        $this->assertContains(self::APP, config('passkeys.allowed_origins'));
        $this->assertSame(self::MAIN.'/api/v1', SiteDomains::publicUrl('api/v1'));
    }
}
