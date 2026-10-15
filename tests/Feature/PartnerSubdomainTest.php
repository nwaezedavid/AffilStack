<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * The affiliate program on affiliate.affilstack.com, with the customer app
 * on app.affilstack.com and the website on affilstack.com — the same three
 * hosts as production.
 */
class PartnerSubdomainTest extends TestCase
{
    use RefreshDatabase;

    protected const MAIN = 'http://affilstack.test';

    protected const APP = 'http://app.affilstack.test';

    protected const PARTNERS = 'http://affiliate.affilstack.test';

    public function createApplication(): Application
    {
        $app = require __DIR__.'/../../bootstrap/app.php';

        $app->afterBootstrapping(LoadConfiguration::class, function (Application $app): void {
            $app['config']->set('app.url', self::MAIN);
            $app['config']->set('app.app_domain', 'app.affilstack.test');
            $app['config']->set('fortify.domain', 'app.affilstack.test');
            $app['config']->set('referrals.landing_subdomain', 'affiliate.affilstack.test');
        });

        $app->make(Kernel::class)->bootstrap();

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
    }

    public function test_the_partner_routes_live_on_the_affiliate_host(): void
    {
        $this->assertSame(self::PARTNERS.'/login', route('partner.login'));
        $this->assertSame(self::PARTNERS.'/portal', route('partner.dashboard'));
        $this->assertSame(self::PARTNERS, rtrim(route('affiliate.landing'), '/'));

        $this->get(self::PARTNERS.'/login')->assertOk()->assertSee('Partner sign in');
        $this->get(self::MAIN.'/login')->assertNotFound();
        $this->get(self::APP.'/portal')->assertNotFound();
    }

    public function test_a_partner_signs_in_on_the_affiliate_host_and_is_sent_there_from_the_app(): void
    {
        $partner = User::factory()->create(['is_affiliate_only' => true, 'email' => 'ada@example.com', 'password' => Hash::make('partner-pass-123')]);
        $partner->assignRole('user');

        $this->post(self::PARTNERS.'/login', ['email' => 'ada@example.com', 'password' => 'partner-pass-123'])
            ->assertRedirect(self::PARTNERS.'/portal');

        $this->get(self::PARTNERS.'/portal')->assertOk();

        $this->actingAs($partner)->get(self::APP.'/dashboard')->assertRedirect(self::PARTNERS.'/portal');
    }

    public function test_guests_on_the_portal_go_to_the_partner_sign_in(): void
    {
        $this->get(self::PARTNERS.'/portal/payouts')->assertRedirect(self::PARTNERS.'/login');
    }

    public function test_referral_links_and_the_attribution_cookie_work_across_hosts(): void
    {
        $partner = User::factory()->create(['is_affiliate_only' => true]);
        $partner->assignRole('user');

        $this->assertSame(self::MAIN.'/r/'.$partner->referralCode(), $partner->referral_link);

        $click = $this->get(self::MAIN.'/r/'.$partner->referralCode());
        $cookie = collect($click->headers->getCookies())->first(fn ($c) => $c->getName() === config('referrals.cookie_name'));

        $this->assertSame('.affilstack.test', $cookie->getDomain());
    }

    public function test_embed_codes_handed_out_on_the_portal_point_at_the_website(): void
    {
        $partner = User::factory()->create(['is_affiliate_only' => true]);
        $partner->assignRole('user');

        $this->actingAs($partner)->get(self::PARTNERS.'/portal/banners')
            ->assertOk()
            ->assertSee(e(self::MAIN.'/embed/banner.js'), false)
            ->assertDontSee(self::PARTNERS.'/embed/banner.js', false);
    }
}
