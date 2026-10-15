<?php

namespace Tests\Feature;

use App\Models\AffiliateBannerImpression;
use App\Models\AffiliateSwipe;
use App\Models\User;
use App\Services\Referrals\MarketingKit\BannerCatalog;
use App\Services\Referrals\MarketingKit\MarketingKit;
use Database\Seeders\AffiliateSwipeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The Marketing Kit's per-affiliate pieces: tracked links, banner/channel
 * click tracking through /r/{code}, swipe placeholders and the dashboard.
 */
class AffiliateMarketingKitTest extends TestCase
{
    use RefreshDatabase;

    public function test_banner_clicks_record_the_banner_and_channel(): void
    {
        $affiliate = User::factory()->create();
        $code = $affiliate->referralCode();

        $this->get("/r/{$code}?utm_source=affiliate&utm_medium=banner&utm_content=commissions-300x250&b=commissions-300x250")
            ->assertRedirect(route('registration.pricing', ['utm_source' => 'affiliate', 'utm_medium' => 'banner', 'utm_content' => 'commissions-300x250']))
            ->assertCookie(config('referrals.cookie_name'), $code);

        $click = $affiliate->referralClicks()->sole();
        $this->assertSame('commissions-300x250', $click->banner_key);
        $this->assertSame('banner', $click->source);
    }

    public function test_unknown_banner_keys_and_sources_are_ignored_but_the_click_still_counts(): void
    {
        $affiliate = User::factory()->create();
        $code = $affiliate->referralCode();

        $this->get("/r/{$code}?b=../../etc-300x250&utm_medium=<script>")->assertRedirect();
        $this->get("/r/{$code}?b=made-up-300x250")->assertRedirect();

        $this->assertSame(2, $affiliate->referralClicks()->count());
        $this->assertSame(0, $affiliate->referralClicks()->whereNotNull('banner_key')->count());
        $this->assertSame(0, $affiliate->referralClicks()->whereNotNull('source')->count());
    }

    public function test_per_banner_stats_combine_impressions_and_clicks(): void
    {
        $affiliate = User::factory()->create();
        $code = $affiliate->referralCode();
        AffiliateBannerImpression::create(['user_id' => $affiliate->id, 'banner_key' => 'research-728x90', 'day' => now()->toDateString(), 'impressions' => 200]);
        $this->get("/r/{$code}?b=research-728x90&utm_medium=banner");
        $this->get("/r/{$code}?utm_medium=email");

        $stats = app(MarketingKit::class)->stats($affiliate);

        $this->assertSame(['impressions' => 200, 'clicks' => 1, 'ctr' => 0.5], $stats['banners']['research-728x90']);
        $this->assertSame(['banner' => 1, 'email' => 1], collect($stats['by_source'])->sortKeys()->all());
        $this->assertSame(2, $stats['clicks']);
    }

    public function test_swipe_placeholders_are_filled_for_the_affiliate(): void
    {
        $affiliate = User::factory()->create(['name' => 'Chidi Okeke']);
        $swipe = AffiliateSwipe::factory()->create([
            'subject' => 'A note from {your_name}',
            'body' => "Hi {first_name},\nTry it: {affiliate_link}\n- {your_name}",
        ]);

        $rendered = app(MarketingKit::class)->renderSwipe($swipe, $affiliate);
        $link = url('/r/'.$affiliate->referralCode()).'?utm_medium=email&utm_content=swipe-'.$swipe->id;

        $this->assertSame('A note from Chidi', $rendered['subject']);
        $this->assertSame("Hi {first_name},\nTry it: {$link}\n- Chidi", $rendered['body']);
        $this->assertSame($link, $rendered['link']);
    }

    public function test_social_swipes_are_tagged_with_their_platform(): void
    {
        $affiliate = User::factory()->create();
        $swipe = AffiliateSwipe::factory()->social('linkedin')->create();

        $rendered = app(MarketingKit::class)->renderSwipe($swipe, $affiliate);

        $this->assertStringContainsString('utm_medium=social&utm_content=linkedin', $rendered['body']);
    }

    public function test_presets_cover_every_requested_angle_and_platform(): void
    {
        $this->assertGreaterThanOrEqual(6, AffiliateSwipe::where('type', 'email')->count());
        $this->assertEqualsCanonicalizing(
            array_keys(config('affiliate_kit.social_platforms')),
            AffiliateSwipe::where('type', 'social')->pluck('platform')->all(),
        );
        $this->assertTrue(AffiliateSwipe::where('type', 'email')->get()->every(fn (AffiliateSwipe $swipe) => str_contains($swipe->body, '{affiliate_link}')));

        $edited = AffiliateSwipe::where('preset_key', 'email-beginner')->first();
        $edited->update(['subject' => 'My own subject']);
        AffiliateSwipe::where('preset_key', 'social-x')->delete();

        (new AffiliateSwipeSeeder)->run();

        $this->assertSame('My own subject', $edited->fresh()->subject);
        $this->assertTrue(AffiliateSwipe::where('preset_key', 'social-x')->exists());
    }

    public function test_html_snippet_always_carries_the_affiliates_attribution(): void
    {
        $affiliate = User::factory()->create();
        $banner = app(BannerCatalog::class)->find('stack-replace-336x280');

        $snippets = app(MarketingKit::class)->snippets($affiliate, $banner);
        $code = $affiliate->referralCode();

        $this->assertStringContainsString('href="'.url("/r/{$code}").'?utm_source=affiliate&amp;utm_medium=banner&amp;utm_content=stack-replace-336x280&amp;b=stack-replace-336x280"', $snippets['html']);
        $this->assertStringContainsString('rel="sponsored noopener"', $snippets['html']);
        $this->assertStringContainsString('width="336" height="280"', $snippets['html']);
        $this->assertStringContainsString('/banners/stack-replace/336x280.png', $snippets['html']);
        $this->assertStringContainsString("ref={$code}", $snippets['iframe']);
        $this->assertStringContainsString("data-ref=\"{$code}\" data-size=\"336x280\" data-design=\"stack-replace\"", $snippets['script']);
        $this->assertStringNotContainsString('data-design', $snippets['script_rotating']);
    }

    public function test_customers_see_the_full_marketing_kit(): void
    {
        $customer = User::factory()->create();

        $response = $this->actingAs($customer)->get(route('referrals.index', ['tab' => 'banners']));

        $response->assertOk();
        foreach (['Banners', 'Email swipes', 'Social posts', 'Text links &amp; brand', 'Guidelines', 'Earnings &amp; payouts'] as $tab) {
            $response->assertSee($tab, false);
        }
        $response->assertSee('data-banner-open="commissions-300x250"', false);
        $response->assertSee('/r/'.$customer->referral_code, false);
        $response->assertSee('No brand bidding');
        $response->assertDontSee('Partner Portal');
    }

    public function test_affiliate_only_partners_see_the_kit_in_the_partner_portal(): void
    {
        $partner = User::factory()->create(['is_affiliate_only' => true]);

        $this->actingAs($partner)->get(route('partner.banners'))->assertOk()->assertSee('Auto-updating ad slot')->assertSee('data-banner-open="commissions-300x250"', false);

        $response = $this->actingAs($partner)->get(route('partner.promote'));

        $response->assertOk();
        $response->assertSee('Partner Portal');
        $response->assertSee('Email swipes');
        $response->assertSee('I stopped tab-switching my way through affiliate marketing');
        $response->assertDontSee('Credits', false);
    }
}
