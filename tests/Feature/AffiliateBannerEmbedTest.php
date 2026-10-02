<?php

namespace Tests\Feature;

use App\Models\AffiliateBanner;
use App\Models\AffiliateBannerImpression;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The embeddable banner (/embed/*): framable by any site, cookie-less, safe
 * with unknown referral codes, and counting deduplicated impressions.
 */
class AffiliateBannerEmbedTest extends TestCase
{
    use RefreshDatabase;

    private const BROWSER = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/128.0 Safari/537.36';

    private function affiliate(): User
    {
        $user = User::factory()->create();
        $user->referralCode();

        return $user;
    }

    public function test_embed_routes_can_be_framed_by_any_site(): void
    {
        $affiliate = $this->affiliate();

        foreach (["/embed/banner/300x250?ref={$affiliate->referral_code}", '/embed/banner.js'] as $url) {
            $response = $this->get($url, ['User-Agent' => self::BROWSER]);

            $response->assertOk();
            $response->assertHeaderMissing('X-Frame-Options');
            $this->assertStringContainsString('frame-ancestors *', $response->headers->get('Content-Security-Policy'));
        }
    }

    public function test_every_other_route_keeps_its_framing_protection(): void
    {
        $affiliate = $this->affiliate();

        foreach (['/', '/banners/commissions/300x250.svg', "/r/{$affiliate->referral_code}", '/pricing'] as $url) {
            $response = $this->get($url);

            $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
            $this->assertStringContainsString("frame-ancestors 'self'", $response->headers->get('Content-Security-Policy'));
            $this->assertStringNotContainsString('frame-ancestors *', $response->headers->get('Content-Security-Policy'));
        }
    }

    public function test_embed_and_image_responses_set_no_cookies(): void
    {
        $affiliate = $this->affiliate();

        foreach ([
            "/embed/banner/300x250?ref={$affiliate->referral_code}",
            '/embed/banner/728x90?ref=unknown',
            '/embed/banner.js',
            '/banners/all-in-one/300x250.svg',
            '/brand-assets/icon.svg',
        ] as $url) {
            $response = $this->get($url, ['User-Agent' => self::BROWSER]);

            $response->assertOk();
            $this->assertSame([], $response->headers->getCookies(), "{$url} set a cookie");
        }
    }

    public function test_a_valid_ref_links_the_banner_to_the_affiliates_tracked_link(): void
    {
        $affiliate = $this->affiliate();

        $response = $this->get("/embed/banner/300x250?ref={$affiliate->referral_code}&design=commissions", ['User-Agent' => self::BROWSER]);

        $response->assertOk();
        $response->assertSee('/r/'.$affiliate->referral_code.'?utm_source=affiliate&amp;utm_medium=banner&amp;utm_content=commissions-300x250&amp;b=commissions-300x250', false);
        $response->assertSee('target="_blank"', false);
        $response->assertSee('/banners/commissions/300x250.svg', false);
        $response->assertDontSee($affiliate->email);
        $response->assertDontSee($affiliate->name);
        $response->assertHeader('Cache-Control', 'max-age=0, no-store, private');
    }

    public function test_unknown_malformed_and_suspended_refs_still_render_but_link_home(): void
    {
        $suspended = $this->affiliate();
        $suspended->update(['is_suspended' => true]);

        foreach (['NOPE1234', '<script>', '', $suspended->referral_code] as $ref) {
            $response = $this->get('/embed/banner/300x250?ref='.urlencode($ref), ['User-Agent' => self::BROWSER]);

            $response->assertOk();
            $response->assertSee('utm_medium=banner', false);
            $response->assertDontSee('/r/', false);
        }

        $this->assertSame(0, AffiliateBannerImpression::count());
    }

    public function test_unknown_sizes_are_not_found(): void
    {
        $this->get('/embed/banner/123x456')->assertNotFound();
        $this->get('/banners/commissions/123x456.svg')->assertNotFound();
        $this->get('/banners/not-a-design/300x250.svg')->assertNotFound();
    }

    public function test_impressions_are_counted_once_per_viewer_within_the_dedupe_window(): void
    {
        $affiliate = $this->affiliate();
        $url = "/embed/banner/300x250?ref={$affiliate->referral_code}&design=research";

        $this->get($url, ['User-Agent' => self::BROWSER])->assertOk();
        $this->get($url, ['User-Agent' => self::BROWSER])->assertOk();
        $this->get($url, ['User-Agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0) Safari/604.1'])->assertOk();

        $row = AffiliateBannerImpression::sole();
        $this->assertSame($affiliate->id, $row->user_id);
        $this->assertSame('research-300x250', $row->banner_key);
        $this->assertSame(2, $row->impressions);
    }

    public function test_previews_and_bots_are_not_counted(): void
    {
        $affiliate = $this->affiliate();

        $this->get("/embed/banner/300x250?ref={$affiliate->referral_code}&preview=1", ['User-Agent' => self::BROWSER])->assertOk();
        $this->get("/embed/banner/300x250?ref={$affiliate->referral_code}", ['User-Agent' => 'Googlebot/2.1 (+http://www.google.com/bot.html)'])->assertOk();

        $this->assertSame(0, AffiliateBannerImpression::count());
    }

    public function test_rotation_prefers_admin_uploads_for_that_size(): void
    {
        $affiliate = $this->affiliate();
        $upload = AffiliateBanner::factory()->create(['size' => '300x250', 'image_path' => 'affiliate-banners/promo.png']);

        $response = $this->get("/embed/banner/300x250?ref={$affiliate->referral_code}", ['User-Agent' => self::BROWSER]);

        $response->assertSee('affiliate-banners/promo.png', false);
        $response->assertSee('b=upload'.$upload->id.'-300x250', false);
    }

    public function test_the_loader_script_is_served_as_javascript(): void
    {
        $response = $this->get('/embed/banner.js');

        $response->assertOk();
        $this->assertStringStartsWith('application/javascript', $response->headers->get('Content-Type'));
        $response->assertSee('allow-popups-to-escape-sandbox', false);
        $response->assertSee(url('/embed/banner'), false);
    }
}
