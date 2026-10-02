<?php

namespace Tests\Feature;

use App\Filament\Pages\MobileAppSettings;
use App\Models\Plan;
use App\Models\SiteSetting;
use App\Models\Subscription;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * "As soon as I launch the mobile apps on Google play store and apple
 * store, I'll update the links in the admin dashboard area and it will
 * become available and visible on the frontend."
 */
class MobileAppLinksTest extends TestCase
{
    use RefreshDatabase;

    protected const PLAY = 'https://play.google.com/store/apps/details?id=com.affilstack.app';

    protected const APPLE = 'https://apps.apple.com/app/affilstack/id1234567890';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
    }

    protected function customer(): User
    {
        $user = User::factory()->create();
        $user->assignRole('user');
        Subscription::factory()->create(['user_id' => $user->id, 'plan_id' => Plan::factory()->create()->id]);

        return $user;
    }

    public function test_nothing_about_the_apps_shows_before_a_link_is_saved(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertDontSee('id="get-the-app"', false)
            ->assertDontSee('Google Play')
            ->assertDontSee('apple-itunes-app', false);

        $this->actingAs($this->customer())->get(route('dashboard'))->assertOk()->assertDontSee('Get the mobile app');
    }

    public function test_a_google_play_link_lights_up_the_homepage_band_footer_and_dashboard(): void
    {
        SiteSetting::set('mobile_app_play_url', self::PLAY);

        $this->get('/')
            ->assertOk()
            ->assertSee('id="get-the-app"', false)
            ->assertSee('href="'.self::PLAY.'"', false)
            ->assertSee('Get AffilStack on Google Play')
            ->assertDontSee('Download AffilStack on the App Store');

        $this->get(route('help.index'))->assertOk()->assertSee('href="'.self::PLAY.'"', false);

        $this->actingAs($this->customer())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Get the mobile app')
            ->assertSee('href="'.self::PLAY.'"', false);
    }

    public function test_an_app_store_link_adds_the_badge_and_the_smart_app_banner(): void
    {
        SiteSetting::set('mobile_app_store_url', self::APPLE);

        $this->get('/')
            ->assertOk()
            ->assertSee('Download AffilStack on the App Store')
            ->assertSee('<meta name="apple-itunes-app" content="app-id=1234567890">', false);
    }

    public function test_coming_soon_shows_the_band_before_launch_without_links(): void
    {
        SiteSetting::setFlag('mobile_app_coming_soon', true);
        SiteSetting::set('mobile_app_name', 'AffilStack Mobile');

        $this->get('/')
            ->assertOk()
            ->assertSee('id="get-the-app"', false)
            ->assertSee('AffilStack Mobile')
            ->assertSee('Mobile apps — coming soon')
            ->assertSee('Coming soon to')
            ->assertDontSee('apple-itunes-app', false);
    }

    public function test_links_to_other_hosts_are_ignored(): void
    {
        SiteSetting::set('mobile_app_play_url', 'https://evil.example/app.apk');
        SiteSetting::set('mobile_app_store_url', 'javascript:alert(1)');

        $this->get('/')->assertOk()->assertDontSee('id="get-the-app"', false)->assertDontSee('evil.example', false);
    }

    public function test_the_app_smart_link_sends_each_device_to_the_right_store(): void
    {
        SiteSetting::set('mobile_app_play_url', self::PLAY);
        SiteSetting::set('mobile_app_store_url', self::APPLE);

        $this->withHeader('User-Agent', 'Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 Chrome/128 Mobile')
            ->get('/app')->assertRedirect(self::PLAY);

        $this->withHeader('User-Agent', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 Mobile/15E148')
            ->get('/app')->assertRedirect(self::APPLE);

        $this->withHeader('User-Agent', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/128')
            ->get('/app')->assertRedirect(route('home').'#get-the-app');
    }

    public function test_the_smart_link_falls_back_to_the_homepage_when_that_store_has_no_link(): void
    {
        SiteSetting::set('mobile_app_play_url', self::PLAY);

        $this->withHeader('User-Agent', 'Mozilla/5.0 (iPad; CPU OS 17_0 like Mac OS X)')
            ->get('/app')->assertRedirect(route('home').'#get-the-app');

        SiteSetting::set('mobile_app_play_url', null);

        $this->withHeader('User-Agent', 'Mozilla/5.0 (Linux; Android 14)')
            ->get('/app')->assertRedirect(route('home'));
    }

    public function test_admin_saves_store_links_from_the_mobile_apps_page(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        Livewire::actingAs($admin)
            ->test(MobileAppSettings::class)
            ->fillForm([
                'mobile_app_play_url' => self::PLAY,
                'mobile_app_store_url' => self::APPLE,
                'mobile_app_coming_soon' => false,
                'mobile_app_pitch' => 'Your affiliate business in your pocket.',
            ])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertNotified('Mobile app settings saved');

        $this->assertSame(self::PLAY, SiteSetting::get('mobile_app_play_url'));
        $this->assertSame(self::APPLE, SiteSetting::get('mobile_app_store_url'));

        $this->get('/')->assertSee('Your affiliate business in your pocket.');
    }

    public function test_admin_cannot_save_a_link_that_is_not_a_store_listing(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        Livewire::actingAs($admin)
            ->test(MobileAppSettings::class)
            ->fillForm([
                'mobile_app_play_url' => 'https://example.com/app',
                'mobile_app_store_url' => 'https://play.google.com/store/apps/details?id=x',
            ])
            ->call('save')
            ->assertHasFormErrors(['mobile_app_play_url', 'mobile_app_store_url']);
    }
}
