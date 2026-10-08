<?php

namespace Tests\Feature;

use App\Models\AnalyticsEvent;
use App\Models\AnalyticsProfile;
use App\Models\AnalyticsSession;
use App\Models\AnalyticsVisitor;
use App\Models\ConsentRecord;
use App\Models\Generation;
use App\Models\Offer;
use App\Models\PageView;
use App\Models\PaymentTransaction;
use App\Models\PendingSignup;
use App\Models\Plan;
use App\Models\SiteSetting;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Analytics\AnalyticsDataEraser;
use App\Services\Analytics\AnalyticsProfileBuilder;
use App\Support\Analytics\AudienceHasher;
use Illuminate\Auth\Events\Login;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Agent 04: first-party analytics — what the beacon stores with and
 * without consent, sessions and attribution, events, identity stitching,
 * server-side hooks, profiles, purge and account deletion.
 */
class AnalyticsTrackingTest extends TestCase
{
    use RefreshDatabase;

    protected string $vid;

    protected function setUp(): void
    {
        parent::setUp();

        $this->vid = (string) Str::uuid();
    }

    protected function consent(bool $analytics = true, bool $marketing = false, string $version = '2026-10'): static
    {
        $value = "v:{$version}|a:".($analytics ? 1 : 0).'|m:'.($marketing ? 1 : 0).'|id:'.Str::uuid().'|t:'.time();

        return $this->withCredentials()->withUnencryptedCookie('afs_consent', $value)->withUnencryptedCookie('afs_vid', $this->vid);
    }

    protected function beacon(array $data = []): TestResponse
    {
        return $this->postJson('/internal/analytics/view', $data + ['path' => '/', 'vid' => $this->vid, 'lang' => 'en-NG']);
    }

    public function test_without_consent_only_an_anonymous_count_is_stored(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->beacon(['path' => '/pricing', 'query' => '?utm_source=facebook&utm_medium=cpc'])->assertOk();

        $view = PageView::sole();
        $this->assertNull($view->visitor_id);
        $this->assertNull($view->user_id);
        $this->assertNull($view->session_id);
        $this->assertSame('Paid', $view->source);
        $this->assertDatabaseCount('analytics_visitors', 0);
        $this->assertDatabaseCount('analytics_events', 0);
    }

    public function test_a_stale_policy_version_counts_as_no_consent(): void
    {
        SiteSetting::set('consent_policy_version', '2027-01');

        $this->consent()->beacon()->assertOk();

        $this->assertDatabaseCount('analytics_visitors', 0);
    }

    public function test_anonymous_counts_can_be_switched_off(): void
    {
        SiteSetting::setFlag('consent_count_anonymous_views', false);

        $this->beacon()->assertOk()->assertJson(['token' => null]);

        $this->assertDatabaseCount('page_views', 0);
    }

    public function test_with_consent_the_visit_is_attributed_and_sessioned(): void
    {
        $this->consent(marketing: false)->beacon([
            'path' => '/',
            'referrer' => 'https://www.facebook.com/',
            'query' => '?utm_source=facebook&utm_medium=paid_social&utm_campaign=launch&fbclid=abc123',
        ], )->assertOk()->assertJsonStructure(['token']);

        $visitor = AnalyticsVisitor::sole();
        $this->assertSame('facebook', $visitor->ft_source);
        $this->assertSame('launch', $visitor->ft_campaign);
        $this->assertSame('meta', $visitor->ft_click_id_type);
        $this->assertNull($visitor->ft_click_id, 'Click ids need marketing consent');
        $this->assertSame('en-NG', $visitor->language);

        $session = AnalyticsSession::sole();
        $this->assertSame('/', $session->landing_path);
        $this->assertSame('launch', $session->campaign);
        $this->assertSame(1, $session->pageviews);
        $this->assertSame($visitor->id, PageView::sole()->visitor_id);
    }

    public function test_click_ids_are_kept_with_marketing_consent(): void
    {
        $this->consent(marketing: true)->beacon(['query' => '?gclid=XYZ'])->assertOk();

        $this->assertSame('XYZ', AnalyticsVisitor::sole()->ft_click_id);
        $this->assertSame('Paid', AnalyticsSession::sole()->channel);
    }

    public function test_views_within_30_minutes_share_a_session_and_later_ones_start_a_new_one(): void
    {
        $this->consent()->beacon(['path' => '/'])->assertOk();
        $this->travel(10)->minutes();
        $this->consent()->beacon(['path' => '/about'])->assertOk();

        $this->assertSame(1, AnalyticsSession::count());
        $this->assertSame(2, AnalyticsSession::sole()->pageviews);

        $this->travel(31)->minutes();
        $this->consent()->beacon(['path' => '/', 'referrer' => 'https://www.google.com/'])->assertOk();

        $this->assertSame(2, AnalyticsSession::count());
        $visitor = AnalyticsVisitor::sole();
        $this->assertSame(2, $visitor->sessions_count);
        $this->assertSame('Organic Search', $visitor->lt_channel, 'Last touch follows the latest non-direct visit');
        $this->assertSame('Direct', $visitor->ft_channel);
    }

    public function test_accepting_mid_page_upgrades_the_anonymous_count_instead_of_double_counting(): void
    {
        $token = $this->beacon(['path' => '/pricing'])->json('token');

        $this->consent()->beacon(['path' => '/pricing', 'upgrade' => $token])->assertOk();

        $this->assertSame(1, PageView::count());
        $this->assertNotNull(PageView::sole()->visitor_id);
    }

    public function test_pricing_and_plan_pages_record_server_side_events(): void
    {
        $this->consent()->beacon(['path' => '/pricing'])->assertOk();
        $this->consent()->beacon(['path' => '/get-started/pro'])->assertOk();

        $this->assertDatabaseHas('analytics_events', ['name' => 'pricing_viewed']);
        $this->assertSame(['plan' => 'pro'], AnalyticsEvent::where('name', 'plan_selected')->sole()->properties);
    }

    public function test_engagement_beacon_stores_time_scroll_and_session_engagement(): void
    {
        $token = $this->consent()->beacon()->json('token');

        $this->postJson('/internal/analytics/duration', ['token' => $token, 'seconds' => 75, 'scroll' => 80])->assertOk();

        $this->assertDatabaseHas('page_views', ['view_token' => $token, 'duration_seconds' => 75, 'scroll_depth' => 80]);
        $this->assertSame(75, AnalyticsSession::sole()->engaged_seconds);
    }

    public function test_custom_events_are_sanitised_and_conversion_names_are_reserved(): void
    {
        $this->consent()->beacon()->assertOk();

        $this->consent()->postJson('/internal/analytics/event', [
            'name' => 'webinar_signup',
            'props' => ['topic' => 'tiktok', 'email' => 'a@b.com', 'note' => 'me@example.com', 'phone_like' => '+234 803 123 4567', 'Bad Key' => 'x', 'count' => 3],
            'path' => '/learn',
            'vid' => $this->vid,
        ])->assertOk()->assertJson(['ok' => true]);

        $this->assertSame(['topic' => 'tiktok', 'count' => 3], AnalyticsEvent::where('name', 'webinar_signup')->sole()->properties);

        $this->consent()->postJson('/internal/analytics/event', ['name' => 'signup_completed', 'vid' => $this->vid])->assertJson(['ok' => false]);
        $this->consent(analytics: false)->postJson('/internal/analytics/event', ['name' => 'cta_click', 'vid' => $this->vid])->assertJson(['ok' => false]);
        $this->assertDatabaseMissing('analytics_events', ['name' => 'signup_completed']);
    }

    public function test_bots_and_prefetches_are_ignored(): void
    {
        $this->consent()->postJson('/internal/analytics/view', ['path' => '/', 'vid' => $this->vid], ['User-Agent' => 'Googlebot/2.1'])->assertJson(['token' => null]);
        $this->consent()->postJson('/internal/analytics/view', ['path' => '/', 'vid' => $this->vid], ['Sec-Purpose' => 'prefetch'])->assertJson(['token' => null]);

        $this->assertDatabaseCount('page_views', 0);
    }

    public function test_the_event_beacon_is_rate_limited(): void
    {
        for ($i = 0; $i < 60; $i++) {
            $this->postJson('/internal/analytics/event', ['name' => 'cta_click'])->assertOk();
        }

        $this->postJson('/internal/analytics/event', ['name' => 'cta_click'])->assertStatus(429);
    }

    public function test_login_stitches_the_visitor_and_back_fills_its_history(): void
    {
        $this->consent()->beacon(['path' => '/pricing'])->assertOk();
        $user = User::factory()->create();

        $this->consent()->get('/');
        $request = request();
        $request->cookies->set('afs_consent', 'v:2026-10|a:1|m:0|id:'.Str::uuid().'|t:1');
        $request->cookies->set('afs_vid', $this->vid);
        $this->actingAs($user);
        Event::dispatch(new Login('web', $user, false));

        $visitor = AnalyticsVisitor::sole();
        $this->assertSame($user->id, $visitor->user_id);
        $this->assertSame($user->id, AnalyticsSession::sole()->user_id);
        $this->assertSame($user->id, AnalyticsEvent::where('name', 'pricing_viewed')->sole()->user_id);
        $this->assertDatabaseHas('analytics_events', ['name' => 'login', 'user_id' => $user->id]);
    }

    public function test_a_second_account_on_the_same_browser_gets_a_fresh_visitor(): void
    {
        $owner = User::factory()->create();
        AnalyticsVisitor::factory()->create(['uuid' => $this->vid, 'user_id' => $owner->id]);
        $other = User::factory()->create();

        $response = $this->actingAs($other)->consent()->beacon()->assertOk();

        $this->assertNotNull($response->json('vid'));
        $this->assertSame(2, AnalyticsVisitor::count());
        $this->assertSame($owner->id, AnalyticsVisitor::where('uuid', $this->vid)->value('user_id'));
    }

    public function test_checkout_and_paid_signup_are_tracked_and_stitched_by_hashed_email(): void
    {
        $this->consent()->beacon(['path' => '/pricing'])->assertOk();
        $plan = Plan::factory()->create();
        $pending = PendingSignup::create(['name' => 'Ada', 'email' => 'Ada.Obi@Gmail.com', 'password' => bcrypt('secret-pass'), 'tx_ref' => 'tx_1', 'plan_id' => $plan->id, 'billing_cycle' => 'monthly', 'status' => 'pending', 'expires_at' => now()->addDay()]);

        $this->consent()->get('/');
        request()->cookies->set('afs_consent', 'v:2026-10|a:1|m:1|id:'.Str::uuid().'|t:1');
        request()->cookies->set('afs_vid', $this->vid);

        $transaction = PaymentTransaction::create(['pending_signup_id' => $pending->id, 'type' => 'signup', 'gateway' => 'stripe', 'tx_ref' => 'tx_1', 'plan_id' => $plan->id, 'billing_cycle' => 'monthly', 'amount_cents' => 2900, 'currency' => 'USD', 'status' => 'pending']);

        $visitor = AnalyticsVisitor::sole();
        $this->assertSame(hash('sha256', 'ada.obi@gmail.com'), $visitor->email_sha256);
        $this->assertSame(hash('sha256', 'adaobi@gmail.com'), $visitor->email_sha256_google);
        $this->assertDatabaseHas('analytics_events', ['name' => 'checkout_started', 'visitor_id' => $visitor->id, 'value_cents' => 2900]);

        $user = User::factory()->create(['email' => 'ada.obi@gmail.com']);
        ConsentRecord::factory()->create(['user_id' => $user->id]);
        $transaction->update(['status' => 'successful']);
        $transaction->update(['type' => 'subscription', 'user_id' => $user->id]);
        $transaction->update(['user_id' => $user->id, 'processed_at' => now()]);

        $this->assertSame($user->id, $visitor->fresh()->user_id);
        $this->assertSame(1, AnalyticsEvent::where('name', 'signup_completed')->where('user_id', $user->id)->count(), 'Deduplicated');
        $this->assertSame(1, AnalyticsEvent::where('name', 'purchase')->count());
    }

    public function test_feature_use_is_tracked_server_side_and_anonymised_without_consent(): void
    {
        $consenting = User::factory()->create();
        ConsentRecord::factory()->analyticsOnly()->create(['user_id' => $consenting->id]);
        $refusing = User::factory()->create();
        ConsentRecord::factory()->rejected()->create(['user_id' => $refusing->id]);

        $offer = Offer::create(['user_id' => $consenting->id, 'product_name' => 'Keto Guide', 'product_url' => 'https://example.com', 'affiliate_network' => 'ClickBank', 'status' => 'ready']);
        Generation::create(['user_id' => $consenting->id, 'offer_id' => $offer->id, 'module' => 'blog_article', 'status' => 'completed', 'credits_spent' => 1]);
        Generation::create(['user_id' => $refusing->id, 'offer_id' => $offer->id, 'module' => 'ugc_video', 'status' => 'completed', 'credits_spent' => 1]);

        $this->assertDatabaseHas('analytics_events', ['name' => 'research_started', 'user_id' => $consenting->id]);
        $this->assertSame(['module' => 'blog_article'], AnalyticsEvent::where('name', 'content_generated')->where('user_id', $consenting->id)->sole()->properties);

        $anonymous = AnalyticsEvent::where('name', 'content_generated')->whereNull('user_id')->sole();
        $this->assertSame(['module' => 'ugc_video'], $anonymous->properties);
        $this->assertNull($anonymous->visitor_id);
    }

    public function test_profiles_capture_lifecycle_engagement_and_interests(): void
    {
        $user = User::factory()->create(['last_active_at' => now()]);
        ConsentRecord::factory()->create(['user_id' => $user->id]);
        Offer::create(['user_id' => $user->id, 'product_name' => 'Keto Guide', 'product_url' => 'https://example.com', 'affiliate_network' => 'ClickBank', 'status' => 'ready']);
        Generation::create(['user_id' => $user->id, 'module' => 'blog_article', 'status' => 'completed', 'credits_spent' => 1]);
        $visitor = AnalyticsVisitor::factory()->create(['user_id' => $user->id]);
        AnalyticsSession::factory()->create(['visitor_id' => $visitor->id, 'user_id' => $user->id, 'started_at' => now()->subDay()]);
        AnalyticsEvent::factory()->create(['user_id' => $user->id, 'name' => 'pricing_viewed', 'category' => 'conversion']);

        $profile = app(AnalyticsProfileBuilder::class)->buildForUser($user);

        $this->assertSame('pricing_viewer', $profile->lifecycle_stage);
        $this->assertGreaterThan(0, $profile->engagement_score);
        $this->assertSame(['ClickBank' => 1], $profile->interests['networks']);
        $this->assertSame(['blog_article' => 1], $profile->interests['modules']);
        $this->assertSame(['Keto Guide'], $profile->interests['products']);

        Subscription::factory()->create(['user_id' => $user->id, 'status' => 'active']);
        $this->assertSame('customer', app(AnalyticsProfileBuilder::class)->buildForUser($user)->lifecycle_stage);

        ConsentRecord::factory()->rejected()->create(['user_id' => $user->id]);
        $this->assertNull(app(AnalyticsProfileBuilder::class)->buildForUser($user));
        $this->assertDatabaseCount('analytics_profiles', 0);
    }

    public function test_purge_removes_data_older_than_the_retention_period(): void
    {
        SiteSetting::set('analytics_retention_months', '13');
        $old = AnalyticsVisitor::factory()->create(['first_seen_at' => now()->subMonths(14), 'last_seen_at' => now()->subMonths(14)]);
        AnalyticsSession::factory()->create(['visitor_id' => $old->id, 'started_at' => now()->subMonths(14), 'last_activity_at' => now()->subMonths(14)]);
        AnalyticsEvent::factory()->create(['visitor_id' => $old->id, 'created_at' => now()->subMonths(14)]);
        ConsentRecord::factory()->create(['created_at' => now()->subMonths(14)]);
        $recent = AnalyticsVisitor::factory()->create(['last_seen_at' => now()]);
        AnalyticsEvent::factory()->create(['visitor_id' => $recent->id]);

        $this->artisan('analytics:purge')->assertSuccessful();

        $this->assertModelMissing($old);
        $this->assertModelExists($recent);
        $this->assertSame(1, AnalyticsEvent::count());
        $this->assertSame(0, AnalyticsSession::count());
        $this->assertSame(0, ConsentRecord::count());
    }

    public function test_deleting_an_account_erases_its_analytics_data(): void
    {
        $user = User::factory()->create();
        $visitor = AnalyticsVisitor::factory()->create(['user_id' => $user->id]);
        AnalyticsSession::factory()->create(['visitor_id' => $visitor->id, 'user_id' => $user->id]);
        AnalyticsEvent::factory()->create(['user_id' => $user->id, 'visitor_id' => $visitor->id]);
        AnalyticsProfile::factory()->create(['user_id' => $user->id]);
        PageView::factory()->create(['user_id' => $user->id, 'visitor_id' => $visitor->id]);
        ConsentRecord::factory()->create(['user_id' => $user->id]);

        $user->forceDelete();

        $this->assertDatabaseCount('analytics_visitors', 0);
        $this->assertDatabaseCount('analytics_sessions', 0);
        $this->assertDatabaseCount('analytics_events', 0);
        $this->assertDatabaseCount('analytics_profiles', 0);
        $this->assertNull(PageView::sole()->visitor_id);
        $this->assertNull(ConsentRecord::sole()->user_id);
    }

    public function test_eraser_also_works_directly_by_id(): void
    {
        $visitor = AnalyticsVisitor::factory()->create(['user_id' => User::factory()->create()->id]);

        app(AnalyticsDataEraser::class)->eraseUser($visitor->user_id);

        $this->assertModelMissing($visitor);
    }

    public function test_audience_hashing_follows_the_platform_specs(): void
    {
        $this->assertSame(hash('sha256', 'mary@example.com'), AudienceHasher::metaEmailHash('  Mary@Example.com '));
        $this->assertSame(hash('sha256', 'johnsmith@gmail.com'), AudienceHasher::googleEmailHash('John.Smith@gmail.com'));
        $this->assertSame(hash('sha256', 'john.smith@yahoo.com'), AudienceHasher::googleEmailHash('John.Smith@yahoo.com'));
        $this->assertSame(hash('sha256', '2348031234567'), AudienceHasher::metaPhoneHash('+234 803 123 4567'));
        $this->assertSame(hash('sha256', '+2348031234567'), AudienceHasher::googlePhoneHash('08031234567', 'NG'));
        $this->assertNull(AudienceHasher::metaPhoneHash('0803 123', null));
    }
}
