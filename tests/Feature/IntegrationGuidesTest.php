<?php

namespace Tests\Feature;

use App\Filament\Pages\Documentation\IntegrationGuide;
use App\Filament\Pages\Documentation\IntegrationGuides;
use App\Models\GoogleOauthSetting;
use App\Models\PaymentGatewaySetting;
use App\Models\SiteSetting;
use App\Models\User;
use App\Support\Docs\IntegrationGuideRegistry;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * "I need detailed guide on how to setup each of the platform features that
 * requires AffilStack to connect to other platforms" — the Integration
 * Guides hub (setup checklist) and one step-by-step page per integration.
 */
class IntegrationGuidesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
    }

    protected function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        return $admin;
    }

    /**
     * @return array<int, string>
     */
    protected function guideKeys(): array
    {
        return array_keys(config('integration_guides.guides'));
    }

    public function test_every_configured_guide_has_complete_metadata_and_a_view(): void
    {
        $phases = array_keys(config('integration_guides.phases'));

        $this->assertGreaterThanOrEqual(25, count($this->guideKeys()));

        foreach (config('integration_guides.guides') as $key => $guide) {
            foreach (['title', 'short', 'phase', 'priority', 'icon', 'enables', 'cost', 'cost_source', 'time', 'admin', 'status', 'docs'] as $field) {
                $this->assertArrayHasKey($field, $guide, "{$key} is missing {$field}");
            }

            $this->assertContains($guide['phase'], $phases, "{$key} has an unknown phase");
            $this->assertContains($guide['priority'], ['required', 'recommended', 'optional']);
            $this->assertNotEmpty($guide['docs'], "{$key} links no official docs");
            $this->assertTrue(view()->exists('filament.pages.documentation.guides.'.$key), "{$key} has no guide view");
        }
    }

    public function test_an_admin_can_open_the_hub_and_every_guide(): void
    {
        $admin = $this->admin();

        Livewire::actingAs($admin)->test(IntegrationGuides::class)
            ->assertSuccessful()
            ->assertSee('Setup checklist')
            ->assertSee('Step 1 — Foundations');

        foreach ($this->guideKeys() as $key) {
            Livewire::actingAs($admin)->test(IntegrationGuide::class, ['guide' => $key])
                ->assertSuccessful()
                ->assertSee(config("integration_guides.guides.{$key}.title"))
                ->assertSee('Official documentation')
                ->assertSee('Last reviewed');
        }
    }

    public function test_every_guide_has_the_standard_sections(): void
    {
        foreach ($this->guideKeys() as $key) {
            $html = IntegrationGuide::renderBody($key);
            $sections = array_column(IntegrationGuide::tableOfContents($html), 'id');

            foreach (['before', 'steps', 'confirm', 'troubleshooting'] as $section) {
                $this->assertContains($section, $sections, "{$key} has no '{$section}' section");
            }
        }
    }

    public function test_an_admin_sub_account_can_read_the_guides(): void
    {
        $subAdmin = User::factory()->create();
        $subAdmin->assignRole('admin_sub');

        Livewire::actingAs($subAdmin)->test(IntegrationGuides::class)->assertSuccessful();
        Livewire::actingAs($subAdmin)->test(IntegrationGuide::class, ['guide' => 'google-sign-in'])->assertSuccessful();
    }

    public function test_a_non_admin_cannot_open_the_hub_or_any_guide(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');

        Livewire::actingAs($user)->test(IntegrationGuides::class)->assertForbidden();

        foreach ($this->guideKeys() as $key) {
            Livewire::actingAs($user)->test(IntegrationGuide::class, ['guide' => $key])->assertForbidden();
        }
    }

    public function test_an_unknown_guide_is_not_found(): void
    {
        Livewire::actingAs($this->admin())->test(IntegrationGuide::class, ['guide' => 'does-not-exist'])->assertNotFound();
    }

    public function test_the_google_guide_shows_every_redirect_uri_built_from_the_real_routes(): void
    {
        $html = IntegrationGuide::renderBody('google-sign-in');

        $this->assertStringContainsString(route('google.callback'), $html);
        $this->assertStringContainsString(route('email-connections.gmail.callback'), $html);
        $this->assertStringContainsString(route('social-connections.callback', ['provider' => 'youtube']), $html);
        $this->assertStringContainsString(route('google-site-analytics.callback'), $html);
        $this->assertStringContainsString(route('privacy'), $html);
    }

    public function test_social_guides_show_their_own_callback_urls(): void
    {
        foreach (['linkedin', 'tiktok', 'instagram'] as $provider) {
            $this->assertStringContainsString(
                route('social-connections.callback', ['provider' => $provider]),
                IntegrationGuide::renderBody($provider),
                "{$provider} guide is missing its redirect URL"
            );
        }

        $this->assertStringContainsString(route('google-site-analytics.callback'), IntegrationGuide::renderBody('site-analytics'));
    }

    public function test_each_payment_guide_shows_its_exact_webhook_url_and_events(): void
    {
        $expectations = [
            'flutterwave' => [route('webhooks.flutterwave'), 'verif-hash'],
            'paystack' => [route('webhooks.paystack'), 'charge.success'],
            'stripe' => [route('webhooks.stripe'), 'checkout.session.completed', 'invoice.paid', 'invoice.payment_failed', 'customer.subscription.deleted', 'charge.refunded', 'charge.dispute.created'],
            'paypal' => [route('webhooks.paypal'), 'CHECKOUT.ORDER.APPROVED', 'PAYMENT.CAPTURE.COMPLETED', 'PAYMENT.CAPTURE.REFUNDED', 'CUSTOMER.DISPUTE.CREATED'],
        ];

        foreach ($expectations as $gateway => $needles) {
            $html = IntegrationGuide::renderBody($gateway);

            foreach ($needles as $needle) {
                $this->assertStringContainsString($needle, $html, "{$gateway} guide is missing {$needle}");
            }
        }
    }

    public function test_the_registry_urls_match_the_named_routes(): void
    {
        $urls = app(IntegrationGuideRegistry::class)->urls();

        $this->assertSame(route('google.callback'), $urls['google_callback']);
        $this->assertSame(route('webhooks.stripe'), $urls['webhook_stripe']);
        $this->assertSame(route('webhooks.paystack'), $urls['webhook_paystack']);
        $this->assertSame(route('webhooks.flutterwave'), $urls['webhook_flutterwave']);
        $this->assertSame(route('webhooks.paypal'), $urls['webhook_paypal']);
        $this->assertSame(route('social-connections.callback', ['provider' => 'tiktok']), $urls['tiktok_callback']);
    }

    public function test_the_checklist_status_reflects_saved_settings(): void
    {
        $registry = app(IntegrationGuideRegistry::class);

        $this->assertSame('not_set', $registry->status('google-sign-in')['state']);
        $this->assertSame('not_set', $registry->status('stripe')['state']);
        $this->assertSame('not_set', $registry->status('meta-pixel')['state']);
        $this->assertSame('user', $registry->status('imap-replies')['state']);

        GoogleOauthSetting::current()->update([
            'is_enabled' => true,
            'gmail_sending_enabled' => true,
            'credentials' => ['client_id' => 'abc.apps.googleusercontent.com', 'client_secret' => 'GOCSPX-secret'],
        ]);
        PaymentGatewaySetting::forGateway('stripe')->update([
            'is_enabled' => true,
            'credentials' => ['secret_key' => 'sk_live_x'],
            'last_verification_status' => 'failed',
            'last_verified_at' => now(),
        ]);
        PaymentGatewaySetting::forGateway('paystack')->update([
            'is_enabled' => true,
            'credentials' => ['secret_key' => 'sk_live_y'],
            'last_verification_status' => 'success',
            'last_verified_at' => now(),
        ]);
        SiteSetting::set('seo_meta_pixel_id', '123456789012345');

        $fresh = new IntegrationGuideRegistry;

        $this->assertSame('configured', $fresh->status('google-sign-in')['state']);
        $this->assertSame('configured', $fresh->status('gmail-sending')['state']);
        $this->assertSame('failing', $fresh->status('stripe')['state']);
        $this->assertSame('working', $fresh->status('paystack')['state']);
        $this->assertSame('configured', $fresh->status('meta-pixel')['state']);
    }

    public function test_the_hub_renders_each_cards_live_status(): void
    {
        PaymentGatewaySetting::forGateway('paystack')->update([
            'is_enabled' => true,
            'credentials' => ['secret_key' => 'sk_live_y'],
            'last_verification_status' => 'success',
            'last_verified_at' => now(),
        ]);

        Livewire::actingAs($this->admin())->test(IntegrationGuides::class)
            ->assertSeeHtml('data-guide="paystack" data-status="working"')
            ->assertSeeHtml('data-guide="stripe" data-status="not_set"');
    }

    public function test_the_mail_status_flags_a_log_only_mailer(): void
    {
        config(['mail.default' => 'log']);

        $this->assertSame('not_set', app(IntegrationGuideRegistry::class)->status('platform-email')['state']);

        config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => 'smtp.hostinger.com']);

        $this->assertSame('configured', (new IntegrationGuideRegistry)->status('platform-email')['state']);
    }
}
