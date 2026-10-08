<?php

namespace Tests\Feature;

use App\Filament\Pages\Analytics\AnalyticsDownloads;
use App\Filament\Pages\Analytics\AudienceInsights;
use App\Filament\Pages\Analytics\CookieConsentSettings;
use App\Filament\Pages\Analytics\Journeys;
use App\Filament\Resources\AnalyticsSegments\Pages\ListAnalyticsSegments;
use App\Filament\Resources\ConsentRecords\Pages\ListConsentRecords;
use App\Http\Middleware\EnsurePanelLoginCompleted;
use App\Models\AnalyticsEvent;
use App\Models\AnalyticsProfile;
use App\Models\AnalyticsSegment;
use App\Models\AnalyticsSession;
use App\Models\AnalyticsVisitor;
use App\Models\ConsentRecord;
use App\Models\SiteSetting;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Analytics\SegmentEvaluator;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

/**
 * Agent 04: the admin side — insights/journeys/settings pages, department
 * scoping, segment logic and the audit-logged, hashed CSV exports.
 */
class AnalyticsAdminTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');
    }

    protected function download(string $type, array $query = []): TestResponse
    {
        return $this->actingAs($this->admin)
            ->withSession([EnsurePanelLoginCompleted::SESSION_KEY => $this->admin->id])
            ->get(route('admin.analytics.export', ['type' => $type] + $query));
    }

    /**
     * A customer with a profile and an active subscription, who allowed
     * (or refused) advertising cookies.
     */
    protected function customer(string $email, bool $marketing, array $profile = []): User
    {
        $user = User::factory()->create(['email' => $email, 'phone' => '+234 803 123 4567', 'country' => 'NG']);
        ConsentRecord::factory()->create(['user_id' => $user->id, 'analytics' => true, 'marketing' => $marketing]);
        Subscription::factory()->create(['user_id' => $user->id, 'status' => 'active']);
        AnalyticsProfile::factory()->create($profile + ['user_id' => $user->id, 'lifecycle_stage' => 'active', 'engagement_score' => 70]);

        return $user;
    }

    public function test_the_admin_pages_render_with_data(): void
    {
        $visitor = AnalyticsVisitor::factory()->create();
        AnalyticsSession::factory()->count(3)->create(['visitor_id' => $visitor->id, 'started_at' => now()->subDay()]);
        AnalyticsEvent::factory()->create(['visitor_id' => $visitor->id, 'name' => 'pricing_viewed', 'category' => 'conversion']);
        AnalyticsEvent::factory()->create(['name' => 'content_generated', 'category' => 'feature', 'properties' => ['module' => 'blog_article']]);
        ConsentRecord::factory()->create();

        Livewire::actingAs($this->admin)->test(AudienceInsights::class)
            ->assertSuccessful()
            ->assertSee('From first visit to paying customer')
            ->assertSee('What this data is — and isn')
            ->assertSee('Generated: Blog Article')
            ->call('applyPreset', '7d')
            ->assertSet('preset', '7d');

        Livewire::actingAs($this->admin)->test(Journeys::class)->assertSuccessful()->call('selectVisitor', $visitor->id)->assertSee('Viewed pricing');
        Livewire::actingAs($this->admin)->test(AnalyticsDownloads::class)->assertSuccessful()->assertSee('Download CSV');
        Livewire::actingAs($this->admin)->test(ListConsentRecords::class)->assertSuccessful();
        Livewire::actingAs($this->admin)->test(ListAnalyticsSegments::class)->assertSuccessful()->assertSee('Started checkout, didn');
        $this->assertSame(5, AnalyticsSegment::where('is_preset', true)->count());
    }

    public function test_pages_are_scoped_to_the_site_department(): void
    {
        $sub = User::factory()->create();
        $sub->assignRole('admin_sub');
        $sub->syncPermissions([]);

        Livewire::actingAs($sub)->test(AudienceInsights::class)->assertForbidden();
        Livewire::actingAs($sub)->test(Journeys::class)->assertForbidden();

        $sub->givePermissionTo('department.site');
        Livewire::actingAs($sub->fresh())->test(AudienceInsights::class)->assertSuccessful();
    }

    public function test_cookie_consent_settings_save_and_bump_the_version(): void
    {
        Livewire::actingAs($this->admin)->test(CookieConsentSettings::class)
            ->fillForm(['consent_banner_title' => 'Cookies, plainly', 'analytics_retention_months' => 6, 'consent_policy_version' => '2026-11'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Cookies, plainly', SiteSetting::get('consent_banner_title'));
        $this->assertSame('6', SiteSetting::get('analytics_retention_months'));
        $this->get('/')->assertSee('Cookies, plainly')->assertSee('"version":"2026-11"', false);

        Livewire::actingAs($this->admin)->test(CookieConsentSettings::class)->callAction('bumpVersion');
        $this->assertNotSame('2026-11', SiteSetting::get('consent_policy_version'));
    }

    public function test_segments_match_their_rules_and_exports_need_marketing_consent(): void
    {
        $this->customer('yes@example.com', true);
        $this->customer('no@example.com', false);
        $prospect = AnalyticsVisitor::factory()->create(['email_sha256' => hash('sha256', 'p@example.com')]);
        AnalyticsProfile::factory()->create(['visitor_id' => $prospect->id, 'lifecycle_stage' => 'checkout_started']);
        AnalyticsEvent::factory()->create(['visitor_id' => $prospect->id, 'name' => 'checkout_started', 'category' => 'conversion']);
        ConsentRecord::factory()->create(['visitor_id' => $prospect->id, 'marketing' => true]);

        SegmentEvaluator::ensurePresets();
        $evaluator = app(SegmentEvaluator::class);
        $customers = AnalyticsSegment::where('key', 'customers_exclude')->sole();
        $abandoned = AnalyticsSegment::where('key', 'checkout_abandoned')->sole();
        $engaged = AnalyticsSegment::where('key', 'highly_engaged')->sole();

        $this->assertSame(2, $evaluator->count($customers));
        $this->assertSame(1, $evaluator->exportableCount($customers));
        $this->assertSame(1, $evaluator->count($abandoned));
        $this->assertSame(1, $evaluator->exportableCount($abandoned));
        $this->assertSame(2, $evaluator->count($engaged));

        $this->assertSame(1, $evaluator->count([
            ['field' => 'lifecycle_stage', 'operator' => 'in', 'value' => 'checkout_started'],
            ['field' => 'identified', 'operator' => 'is', 'value' => 'no'],
        ]));
    }

    public function test_inactive_customers_preset_uses_live_dashboard_activity(): void
    {
        $quiet = $this->customer('quiet@example.com', true);
        $quiet->forceFill(['last_active_at' => now()->subDays(20)])->save();
        $busy = $this->customer('busy@example.com', true);
        $busy->forceFill(['last_active_at' => now()])->save();

        SegmentEvaluator::ensurePresets();
        $inactive = AnalyticsSegment::where('key', 'inactive_customers')->sole();

        $this->assertSame([$quiet->id], app(SegmentEvaluator::class)->query($inactive)->pluck('user_id')->all());
    }

    public function test_meta_and_google_audience_exports_are_hashed_and_audit_logged(): void
    {
        $this->customer('John.Smith@Gmail.com', true);
        $this->customer('refused@example.com', false);
        SegmentEvaluator::ensurePresets();
        $segment = AnalyticsSegment::where('key', 'customers_exclude')->sole();

        $meta = $this->download('audience', ['segment' => $segment->id, 'platform' => 'meta'])->assertOk()->streamedContent();
        $rows = array_map('str_getcsv', array_filter(explode("\n", trim($meta))));

        $this->assertSame(['email', 'phone', 'country'], $rows[0]);
        $this->assertCount(2, $rows, 'Only the customer who allowed advertising');
        $this->assertSame([hash('sha256', 'john.smith@gmail.com'), hash('sha256', '2348031234567'), hash('sha256', 'ng')], $rows[1]);
        $this->assertStringNotContainsString('gmail.com', $meta);

        $google = $this->download('audience', ['segment' => $segment->id, 'platform' => 'google'])->assertOk()->streamedContent();
        $rows = array_map('str_getcsv', array_filter(explode("\n", trim($google))));
        $this->assertSame(['Email', 'Phone'], $rows[0]);
        $this->assertSame([hash('sha256', 'johnsmith@gmail.com'), hash('sha256', '+2348031234567')], $rows[1]);

        $this->assertSame(2, Activity::where('log_name', 'analytics-export')->count());
        $this->assertSame($this->admin->id, Activity::where('log_name', 'analytics-export')->first()->causer_id);
    }

    public function test_raw_exports_stream_csv_and_neutralise_formulas(): void
    {
        $visitor = AnalyticsVisitor::factory()->create();
        AnalyticsEvent::factory()->create(['visitor_id' => $visitor->id, 'name' => 'cta_click', 'path' => '=HYPERLINK("x")']);

        $csv = $this->download('events')->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8')->streamedContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertStringContainsString($visitor->shortId(), $csv);

        foreach (['page_views', 'sessions', 'visitors', 'users', 'consent'] as $type) {
            $this->download($type)->assertOk();
        }
    }

    public function test_exports_are_full_admin_only_and_need_the_panel_login(): void
    {
        $sub = User::factory()->create();
        $sub->assignRole('admin_sub');
        $sub->givePermissionTo('department.site');

        $this->actingAs($sub)->withSession([EnsurePanelLoginCompleted::SESSION_KEY => $sub->id])
            ->get(route('admin.analytics.export', ['type' => 'events']))->assertForbidden();

        $this->actingAs($this->admin)->get(route('admin.analytics.export', ['type' => 'events']))->assertRedirect();
        $this->download('nope')->assertNotFound();
    }
}
