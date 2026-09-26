<?php

namespace Tests\Feature;

use App\Http\Controllers\Dashboard\ExtensionController;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use ZipArchive;

class BrowserExtensionDownloadTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_downloaded_extension_already_points_at_this_site(): void
    {
        config(['app.url' => 'https://example-affilstack.test']);
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('extension.download'));

        $response->assertOk();
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($response->baseResponse->getFile()->getPathname()));
        $config = (string) $zip->getFromName('affilstack-extension/config.js');
        $manifest = json_decode((string) $zip->getFromName('affilstack-extension/manifest.json'), true);
        $zip->close();

        $this->assertStringContainsString("'https://example-affilstack.test/api'", $config);
        $this->assertStringNotContainsString(ExtensionController::STORE_BUILD_API_BASE_URL, $config);
        $this->assertSame(3, $manifest['manifest_version']);
    }

    public function test_the_store_build_default_matches_the_address_the_download_replaces(): void
    {
        $config = file_get_contents(resource_path('browser-extension/config.js'));

        $this->assertStringContainsString("'".ExtensionController::STORE_BUILD_API_BASE_URL."'", $config);
    }

    public function test_the_extension_page_shows_the_api_address(): void
    {
        config(['app.url' => 'https://example-affilstack.test']);
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('extension.index'))
            ->assertOk()
            ->assertSee('https://example-affilstack.test/api');
    }

    public function test_once_published_the_page_links_to_the_chrome_web_store(): void
    {
        config(['extension.store_url' => 'https://chromewebstore.google.com/detail/affilstack/abc123']);
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('extension.index'))
            ->assertOk()
            ->assertSee('Add to Chrome')
            ->assertSee('https://chromewebstore.google.com/detail/affilstack/abc123')
            ->assertDontSee('Load unpacked');
    }
}
