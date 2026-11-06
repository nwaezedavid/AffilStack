<?php

namespace Tests\Feature;

use App\Models\MobileIntegrationSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * /.well-known/apple-app-site-association and /.well-known/assetlinks.json:
 * what lets AffilStack links open the phone app instead of the browser.
 */
class AppLinksTest extends TestCase
{
    use RefreshDatabase;

    public function test_nothing_is_published_until_the_ids_are_entered(): void
    {
        $this->get('/.well-known/apple-app-site-association')->assertNotFound();
        $this->get('/.well-known/assetlinks.json')->assertNotFound();
    }

    public function test_iphones_get_the_app_id_and_only_the_pages_the_app_can_show(): void
    {
        MobileIntegrationSetting::current()->update(['ios_team_id' => 'A1B2C3D4E5']);

        $response = $this->get('/.well-known/apple-app-site-association')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/json')
            ->assertHeaderMissing('Set-Cookie')
            ->assertJsonPath('applinks.details.0.appIDs.0', 'A1B2C3D4E5.com.affilstack.app')
            ->assertJsonPath('webcredentials.apps.0', 'A1B2C3D4E5.com.affilstack.app');

        $components = collect($response->json('applinks.details.0.components'));

        $this->assertSame(['/' => '/mobile/*', 'exclude' => true], collect($components->first())->only(['/', 'exclude'])->all());
        $this->assertContains('/offers/*', $components->pluck('/')->all());
        $this->assertContains('/crm/inbox/*', $components->pluck('/')->all());
        $this->assertNotContains('/billing', $components->pluck('/')->all());
        $this->assertStringNotContainsString('\/', $response->getContent());
    }

    public function test_android_gets_the_package_and_signing_fingerprints(): void
    {
        $fingerprint = implode(':', array_fill(0, 32, 'AB'));
        MobileIntegrationSetting::current()->update(['android_sha256_fingerprints' => [$fingerprint]]);

        $this->get('/.well-known/assetlinks.json')
            ->assertOk()
            ->assertHeaderMissing('Set-Cookie')
            ->assertJsonPath('0.relation.0', 'delegate_permission/common.handle_all_urls')
            ->assertJsonPath('0.target.namespace', 'android_app')
            ->assertJsonPath('0.target.package_name', 'com.affilstack.app')
            ->assertJsonPath('0.target.sha256_cert_fingerprints.0', $fingerprint);
    }

    public function test_other_dot_paths_stay_blocked(): void
    {
        MobileIntegrationSetting::current()->update(['ios_team_id' => 'A1B2C3D4E5']);

        $this->get('/.well-known/security.txt')->assertNotFound();
        $this->get('/.env')->assertNotFound();
    }
}
