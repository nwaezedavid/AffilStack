<?php

namespace Tests\Feature;

use App\Filament\Pages\BrandSettings;
use App\Filament\Resources\BrandLogos\Pages\CreateBrandLogo;
use App\Models\BrandLogo;
use App\Models\SiteSetting;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The going-forward half of image optimization: every image uploaded
 * through a WebpFileUpload field (see that class, and WebpImageConverter
 * for the actual conversion) should land on disk as .webp the moment an
 * admin saves the form — not just when OptimizeExistingImagesToWebp is
 * run later. Exercises this through two real Filament forms (a plain
 * settings Page and a Resource's Create page) to prove the wiring holds
 * for both shapes of form this app has.
 */
class WebpImageUploadConversionTest extends TestCase
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

    public function test_uploading_a_brand_settings_logo_stores_it_as_webp(): void
    {
        Storage::fake('public');

        Livewire::actingAs($this->admin())
            ->test(BrandSettings::class)
            ->fillForm([
                'site_name' => 'AffilStack',
                'logo_rectangular' => UploadedFile::fake()->image('wide-logo.png'),
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $path = SiteSetting::get('logo_rectangular_path');

        $this->assertStringEndsWith('.webp', $path);
        Storage::disk('public')->assertExists($path);
        // The original PNG Livewire's temp-upload flow moved into place is
        // never deleted — only the stored *pointer* changes to the .webp
        // sibling.
        Storage::disk('public')->assertExists(str_replace('.webp', '.png', $path));
    }

    public function test_creating_a_brand_logo_stores_the_upload_as_webp(): void
    {
        Storage::fake('public');

        Livewire::actingAs($this->admin())
            ->test(CreateBrandLogo::class)
            ->fillForm([
                'name' => 'Acme Corp',
                'logo_path' => UploadedFile::fake()->image('acme.png'),
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $logo = BrandLogo::firstWhere('name', 'Acme Corp');

        $this->assertNotNull($logo);
        $this->assertStringEndsWith('.webp', $logo->logo_path);
        Storage::disk('public')->assertExists($logo->logo_path);
    }
}
