<?php

namespace Tests\Feature;

use App\Filament\Resources\AffiliateBanners\AffiliateBannerResource;
use App\Filament\Resources\AffiliateBanners\Pages\CreateAffiliateBanner;
use App\Filament\Resources\AffiliateBanners\Pages\ListAffiliateBanners;
use App\Filament\Resources\AffiliateSwipes\AffiliateSwipeResource;
use App\Filament\Resources\AffiliateSwipes\Pages\CreateAffiliateSwipe;
use App\Filament\Resources\AffiliateSwipes\Pages\EditAffiliateSwipe;
use App\Filament\Resources\AffiliateSwipes\Pages\ListAffiliateSwipes;
use App\Models\AffiliateBanner;
use App\Models\AffiliateSwipe;
use App\Models\User;
use App\Services\Referrals\MarketingKit\BannerCatalog;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Billing → Affiliate Banners / Affiliate Swipes: admins manage the kit's
 * creatives and copy; uploads keep their original format and show up first
 * in every affiliate's gallery.
 */
class AffiliateKitAdminResourcesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        return $admin;
    }

    public function test_admin_can_upload_a_banner_which_stays_png_and_leads_the_gallery(): void
    {
        Storage::fake('public');

        Livewire::actingAs($this->admin())
            ->test(CreateAffiliateBanner::class)
            ->fillForm([
                'title' => 'Black Friday',
                'size' => '728x90',
                'alt_text' => 'AffilStack Black Friday',
                'image_path' => UploadedFile::fake()->image('black-friday.png', 728, 90),
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $banner = AffiliateBanner::firstWhere('title', 'Black Friday');
        $this->assertNotNull($banner);
        $this->assertStringEndsWith('.png', $banner->image_path);
        Storage::disk('public')->assertExists($banner->image_path);

        $gallery = app(BannerCatalog::class)->all('728x90');
        $this->assertSame('upload'.$banner->id.'-728x90', $gallery->first()['key']);
        $this->assertSame('AffilStack Black Friday', $gallery->first()['alt']);
    }

    public function test_svg_uploads_are_rejected(): void
    {
        Storage::fake('public');

        Livewire::actingAs($this->admin())
            ->test(CreateAffiliateBanner::class)
            ->fillForm([
                'title' => 'Sneaky',
                'size' => '300x250',
                'image_path' => UploadedFile::fake()->createWithContent('banner.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'),
            ])
            ->call('create')
            ->assertHasFormErrors(['image_path']);

        $this->assertSame(0, AffiliateBanner::count());
    }

    public function test_inactive_uploads_are_hidden_from_affiliates(): void
    {
        $banner = AffiliateBanner::factory()->create(['is_active' => false]);

        $this->assertNull(app(BannerCatalog::class)->find($banner->bannerKey()));
        Livewire::actingAs($this->admin())->test(ListAffiliateBanners::class)->assertCanSeeTableRecords([$banner]);
    }

    public function test_admin_can_create_and_edit_swipes(): void
    {
        Livewire::actingAs($this->admin())
            ->test(CreateAffiliateSwipe::class)
            ->fillForm([
                'type' => 'social',
                'platform' => 'x',
                'title' => 'Launch week',
                'body' => 'New on AffilStack: {affiliate_link} #ad',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $swipe = AffiliateSwipe::firstWhere('title', 'Launch week');
        $this->assertSame('x', $swipe->platform);

        Livewire::actingAs($this->admin())
            ->test(EditAffiliateSwipe::class, ['record' => $swipe->getRouteKey()])
            ->fillForm(['body' => 'Updated: {affiliate_link}'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Updated: {affiliate_link}', $swipe->fresh()->body);
    }

    public function test_email_swipes_require_a_subject(): void
    {
        Livewire::actingAs($this->admin())
            ->test(CreateAffiliateSwipe::class)
            ->fillForm(['type' => 'email', 'title' => 'No subject', 'body' => 'Hi {first_name}'])
            ->call('create')
            ->assertHasFormErrors(['subject' => 'required']);
    }

    public function test_restore_presets_re_adds_deleted_presets_only(): void
    {
        AffiliateSwipe::where('preset_key', 'email-time-saver')->delete();
        $kept = AffiliateSwipe::firstWhere('preset_key', 'email-beginner');
        $kept->update(['title' => 'Edited by admin']);

        Livewire::actingAs($this->admin())
            ->test(ListAffiliateSwipes::class)
            ->callAction('restorePresets');

        $this->assertTrue(AffiliateSwipe::where('preset_key', 'email-time-saver')->exists());
        $this->assertSame('Edited by admin', $kept->fresh()->title);
    }

    public function test_resources_are_scoped_to_the_billing_department(): void
    {
        $billing = User::factory()->create();
        $billing->assignRole('admin_sub');
        $billing->syncPermissions(['department.billing']);
        $support = User::factory()->create();
        $support->assignRole('admin_sub');
        $support->syncPermissions(['department.support']);

        $this->actingAs($billing);
        $this->assertTrue(AffiliateBannerResource::canAccess());
        $this->assertTrue(AffiliateSwipeResource::canAccess());

        $this->actingAs($support);
        $this->assertFalse(AffiliateBannerResource::canAccess());
        $this->assertFalse(AffiliateSwipeResource::canAccess());
    }
}
