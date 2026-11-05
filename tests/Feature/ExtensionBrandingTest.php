<?php

namespace Tests\Feature;

use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;
use ZipArchive;

class ExtensionBrandingTest extends TestCase
{
    use RefreshDatabase;

    protected function uploadLogo(): string
    {
        Storage::fake('public');
        $image = imagecreatetruecolor(600, 600);
        imagefill($image, 0, 0, imagecolorallocate($image, 200, 30, 30));
        ob_start();
        imagepng($image);
        $png = (string) ob_get_clean();
        Storage::disk('public')->put('branding/square.png', $png);
        SiteSetting::set('logo_square_path', 'branding/square.png');

        return $png;
    }

    /**
     * @return array<string, string>
     */
    protected function zipEntries(string $path): array
    {
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path));
        $entries = [];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            $entries[$name] = (string) $zip->getFromIndex($i);
        }

        $zip->close();

        return $entries;
    }

    public function test_the_download_uses_the_uploaded_site_icon_for_every_extension_icon(): void
    {
        $this->uploadLogo();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('extension.download'));
        $entries = $this->zipEntries($response->baseResponse->getFile()->getPathname());

        foreach ([16, 32, 48, 128] as $size) {
            $icon = $entries["affilstack-extension/icons/icon{$size}.png"];
            [$width, $height] = getimagesizefromstring($icon);
            $this->assertSame([$size, $size], [$width, $height]);

            $decoded = imagecreatefromstring($icon);
            $rgb = imagecolorat($decoded, intdiv($size, 2), intdiv($size, 2));
            $this->assertGreaterThan(150, ($rgb >> 16) & 0xFF, "icon{$size} should be the uploaded red logo");
        }

        $manifest = json_decode($entries['affilstack-extension/manifest.json'], true);
        $this->assertSame('icons/icon32.png', $manifest['icons']['32']);
    }

    public function test_without_an_upload_the_built_in_icons_ship(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('extension.download'));
        $entries = $this->zipEntries($response->baseResponse->getFile()->getPathname());

        $this->assertSame(
            file_get_contents(resource_path('browser-extension/icons/icon128.png')),
            $entries['affilstack-extension/icons/icon128.png'],
        );
    }

    public function test_the_store_package_is_owner_only_and_has_the_manifest_at_the_root(): void
    {
        $this->uploadLogo();
        $customer = User::factory()->create();
        $this->actingAs($customer)->get(route('extension.store-package'))->assertForbidden();

        Role::findOrCreate('super-admin', 'web');
        $owner = User::factory()->create();
        $owner->assignRole('super-admin');

        $response = $this->actingAs($owner)->get(route('extension.store-package'));
        $response->assertOk();
        $entries = $this->zipEntries($response->baseResponse->getFile()->getPathname());

        $this->assertArrayHasKey('manifest.json', $entries);
        $this->assertArrayNotHasKey('README.md', $entries);
        $this->assertStringContainsString("'https://app.affilstack.com/api'", $entries['config.js']);
        $this->assertArrayNotHasKey('store/listing.md', $entries);
    }
}
