<?php

namespace Tests\Feature;

use App\Filament\Pages\BingWebmasterSettings;
use App\Models\BingWebmasterSetting;
use App\Models\User;
use App\Services\Analytics\BingWebmasterClient;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Bing Webmaster Tools — deliberately a guided connect rather than full
 * auto-provisioning (see BingWebmasterSetting's docblock for why): paste an
 * API key, add the site on bing.com/webmasters with meta-tag verification,
 * paste the code back, then verify + auto-submit the sitemap.
 */
class BingWebmasterTest extends TestCase
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

    public function test_verify_api_key_requires_a_key(): void
    {
        $result = (new BingWebmasterClient(''))->verifyApiKey();

        $this->assertFalse($result['success']);
    }

    public function test_verify_api_key_reports_the_registered_sites_on_success(): void
    {
        Http::fake([
            'ssl.bing.com/*' => Http::response(['d' => [['Url' => 'https://example.com/']]]),
        ]);

        $result = (new BingWebmasterClient('valid-key'))->verifyApiKey();

        $this->assertTrue($result['success']);
        $this->assertSame(['https://example.com/'], $result['sites']);
    }

    public function test_verify_api_key_fails_loudly_on_a_rejected_key(): void
    {
        Http::fake(['ssl.bing.com/*' => Http::response(['error' => 'invalid key'], 401)]);

        $result = (new BingWebmasterClient('bad-key'))->verifyApiKey();

        $this->assertFalse($result['success']);
    }

    public function test_verify_site_succeeds_when_the_site_is_already_registered(): void
    {
        Http::fake(['ssl.bing.com/*' => Http::response(['d' => [['Url' => 'https://example.com']]])]);

        $result = (new BingWebmasterClient('valid-key'))->verifySite('https://example.com');

        $this->assertTrue($result['success']);
    }

    public function test_verify_site_explains_the_next_step_when_the_site_is_not_registered_yet(): void
    {
        Http::fake(['ssl.bing.com/*' => Http::response(['d' => []])]);

        $result = (new BingWebmasterClient('valid-key'))->verifySite('https://example.com');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('bing.com/webmasters', $result['message']);
    }

    public function test_submit_sitemap_reports_success(): void
    {
        Http::fake(['ssl.bing.com/*' => Http::response(['ok' => true])]);

        $result = (new BingWebmasterClient('valid-key'))->submitSitemap('https://example.com', 'https://example.com/sitemap.xml');

        $this->assertTrue($result['success']);
    }

    public function test_admin_can_save_the_api_key_and_verification_code(): void
    {
        Livewire::actingAs($this->admin)
            ->test(BingWebmasterSettings::class)
            ->fillForm([
                'is_enabled' => true,
                'api_key' => 'secret-bing-key',
                'verification_code' => 'ABC123XYZ',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $settings = BingWebmasterSetting::current();
        $this->assertTrue($settings->is_enabled);
        $this->assertSame('secret-bing-key', $settings->credential('api_key'));
        $this->assertSame('ABC123XYZ', $settings->verification_code);
        $this->assertTrue($settings->isConfigured());

        $raw = \DB::table('bing_webmaster_settings')->where('id', 1)->value('credentials');
        $this->assertStringNotContainsString('secret-bing-key', (string) $raw);
    }

    public function test_the_verification_code_renders_as_a_meta_tag_on_the_public_site(): void
    {
        BingWebmasterSetting::current()->update(['verification_code' => 'ABC123XYZ']);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('name="msvalidate.01" content="ABC123XYZ"', false);
    }

    public function test_admin_can_verify_and_the_result_persists_to_the_settings_row(): void
    {
        Http::fake(['ssl.bing.com/*' => Http::response(['d' => [['Url' => url('/')]]])]);

        Livewire::actingAs($this->admin)
            ->test(BingWebmasterSettings::class)
            ->fillForm(['api_key' => 'valid-key'])
            ->call('verify');

        $settings = BingWebmasterSetting::current();
        $this->assertSame('success', $settings->verification_status);
        $this->assertNotNull($settings->verified_at);
    }

    public function test_a_non_admin_cannot_access_the_page(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');

        Livewire::actingAs($user)
            ->test(BingWebmasterSettings::class)
            ->assertForbidden();
    }
}
