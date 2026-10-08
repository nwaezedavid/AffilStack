<?php

namespace Tests\Feature;

use App\Filament\Resources\BrandLogos\Pages\CreateBrandLogo;
use App\Models\BrandLogo;
use App\Models\User;
use App\Services\Media\LogoNormalizer;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Featured-company logos are trimmed and fitted to one box so the homepage
 * row lines up, whatever the original file looked like.
 */
class BrandLogoNormalizerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A white canvas with a solid block of "ink" somewhere inside it.
     */
    protected function logoPng(int $width, int $height, array $ink): string
    {
        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, imagecolorallocate($image, 255, 255, 255));
        imagefilledrectangle($image, $ink[0], $ink[1], $ink[2], $ink[3], imagecolorallocate($image, 20, 40, 160));
        ob_start();
        imagepng($image);

        return (string) ob_get_clean();
    }

    public function test_logos_of_any_shape_come_out_the_same_size_trimmed_and_centred(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('brand-logos/wide.png', $this->logoPng(1200, 400, [100, 150, 1099, 249]));
        Storage::disk('public')->put('brand-logos/tall.png', $this->logoPng(300, 900, [100, 100, 199, 799]));

        $normalizer = app(LogoNormalizer::class);

        foreach (['wide', 'tall'] as $name) {
            $path = $normalizer->normalize('public', "brand-logos/{$name}.png");

            $this->assertStringEndsWith(LogoNormalizer::SUFFIX, $path);
            $image = imagecreatefromstring(Storage::disk('public')->get($path));
            $this->assertSame([LogoNormalizer::BOX_WIDTH, LogoNormalizer::BOX_HEIGHT], [imagesx($image), imagesy($image)]);

            // Corners are transparent; the centre holds the (trimmed) artwork.
            $this->assertSame(127, (imagecolorat($image, 0, 0) >> 24) & 0x7F);
            $this->assertLessThan(20, (imagecolorat($image, intdiv(LogoNormalizer::BOX_WIDTH, 2), intdiv(LogoNormalizer::BOX_HEIGHT, 2)) >> 24) & 0x7F);
        }

        // The wide logo (1000×100 ink, 10:1) is trimmed so it fills the box width.
        $wide = imagecreatefromstring(Storage::disk('public')->get($normalizer->normalize('public', 'brand-logos/wide.png')));
        $this->assertLessThan(20, (imagecolorat($wide, 12, 60) >> 24) & 0x7F);
    }

    public function test_an_uploaded_logo_is_normalized_when_saved_in_the_admin(): void
    {
        Storage::fake('public');
        $this->seed(RolesSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        Livewire::actingAs($admin)
            ->test(CreateBrandLogo::class)
            ->fillForm([
                'name' => 'Acme',
                'logo_path' => UploadedFile::fake()->image('acme.png', 800, 200),
                'sort_order' => 0,
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $path = BrandLogo::where('name', 'Acme')->value('logo_path');
        $this->assertTrue(LogoNormalizer::isNormalized($path));
        $this->assertSame([320, 120], array_slice(getimagesizefromstring(Storage::disk('public')->get($path)), 0, 2));
    }

    public function test_the_command_reprocesses_existing_logos_once(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('brand-logos/old.png', $this->logoPng(500, 500, [50, 200, 449, 299]));
        $logo = BrandLogo::factory()->create(['logo_path' => 'brand-logos/old.png']);

        $this->artisan('brand-logos:normalize')->assertSuccessful();
        $first = $logo->fresh()->logo_path;
        $this->assertTrue(LogoNormalizer::isNormalized($first));

        $this->artisan('brand-logos:normalize')->assertSuccessful();
        $this->assertSame($first, $logo->fresh()->logo_path);
    }

    public function test_the_marquee_renders_every_logo_in_the_same_box(): void
    {
        BrandLogo::factory()->create(['name' => 'Acme', 'logo_path' => 'brand-logos/acme-abc123-logo.webp']);

        $this->get('/')->assertSee('class="h-12 w-32 object-contain', false);
    }
}
