<?php

namespace Tests\Feature;

use App\Filament\Resources\BannerAds\Pages\CreateBannerAd;
use App\Filament\Resources\BannerAds\Pages\ListBannerAds;
use App\Models\BannerAd;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;
use Tests\TestCase;

class BannerAdsTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_embed_shows_the_live_ad_and_updates_when_the_admin_edits_it(): void
    {
        $partner = User::factory()->create(['is_affiliate_only' => true]);
        $code = $partner->referralCode();
        $ad = BannerAd::factory()->create(['headline' => 'Black Friday: one stack', 'discount_badge' => '40% OFF', 'landing_path' => '/features']);

        $frame = $this->get("/embed/banner/300x250?ref={$code}", ['User-Agent' => 'Mozilla/5.0 Chrome/128.0']);
        $frame->assertOk()->assertSee('Black Friday: one stack')->assertSee('40% OFF');
        $this->assertSame([], $frame->headers->getCookies());

        preg_match('/href="([^"]+)"/', $frame->getContent(), $match);
        $click = html_entity_decode($match[1]);
        $this->assertStringContainsString('b=ad'.$ad->id.'-300x250', $click);
        $this->assertStringContainsString('to=%2Ffeatures', $click);

        $this->get($click)->assertRedirect();
        $this->assertSame('ad'.$ad->id.'-300x250', $partner->referralClicks()->sole()->banner_key);
        $this->assertSame(1, (int) $partner->affiliateBannerImpressions()->sum('impressions'));

        $ad->update(['headline' => 'Cyber Monday: last chance', 'discount_badge' => null]);

        $this->get("/embed/banner/300x250?ref={$code}")->assertSee('Cyber Monday: last chance')->assertDontSee('40% OFF');
    }

    public function test_switched_off_scheduled_and_ended_ads_are_not_shown(): void
    {
        BannerAd::factory()->off()->create(['headline' => 'Off ad']);
        BannerAd::factory()->create(['headline' => 'Future ad', 'starts_at' => now()->addDay()]);
        BannerAd::factory()->create(['headline' => 'Past ad', 'ends_at' => now()->subMinute()]);
        BannerAd::factory()->create(['headline' => 'Wide only', 'sizes' => ['728x90']]);

        $response = $this->get('/embed/banner/300x250?ref=NOPE');

        $response->assertOk();
        foreach (['Off ad', 'Future ad', 'Past ad', 'Wide only'] as $headline) {
            $response->assertDontSee($headline);
        }

        $this->get('/embed/banner/728x90')->assertSee('Wide only');
    }

    public function test_only_a_signed_preview_shows_an_ad_that_is_switched_off(): void
    {
        $ad = BannerAd::factory()->off()->create(['headline' => 'Draft promo']);

        $this->get('/embed/banner/300x250?design=ad'.$ad->id)->assertDontSee('Draft promo');
        $this->get(URL::temporarySignedRoute('embed.banner', now()->addHour(), ['size' => '300x250', 'design' => 'ad'.$ad->id, 'preview' => 1]))
            ->assertOk()->assertSee('Draft promo');
    }

    public function test_every_ad_size_renders(): void
    {
        $ad = BannerAd::factory()->create(['headline' => 'Every size works']);

        foreach (BannerAd::SIZES as $size) {
            $this->get('/embed/banner/'.$size.'?design=ad'.$ad->id)->assertOk()->assertSee('Every size works');
        }
    }

    public function test_partners_see_live_ads_in_their_gallery(): void
    {
        $partner = User::factory()->create(['is_affiliate_only' => true]);
        $ad = BannerAd::factory()->create(['headline' => 'Gallery promo']);

        $this->actingAs($partner)->get(route('partner.banners'))
            ->assertOk()
            ->assertSee('Auto-updating')
            ->assertSee('data-banner-open="ad'.$ad->id.'-300x250"', false);
    }

    public function test_admins_create_ads_from_the_panel(): void
    {
        $this->seed(RolesSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        Livewire::actingAs($admin)->test(CreateBannerAd::class)
            ->fillForm([
                'name' => 'Launch week',
                'headline' => 'Launch week: 30% off',
                'cta_label' => 'Claim it',
                'landing_path' => '/pricing',
                'theme' => 'gold',
                'weight' => 5,
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('banner_ads', ['name' => 'Launch week', 'theme' => 'gold']);
        Livewire::actingAs($admin)->test(ListBannerAds::class)->assertOk()->assertSee('Launch week');
    }
}
