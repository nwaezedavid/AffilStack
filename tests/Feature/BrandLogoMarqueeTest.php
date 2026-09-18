<?php

namespace Tests\Feature;

use App\Filament\Resources\BrandLogos\BrandLogoResource;
use App\Models\BrandLogo;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * "That design that popular websites use to show brands they've worked
 * with (it's constantly moving in a loop), then make space/menu in the
 * admin dashboard area where I can upload the logos of each brand I've
 * worked with (unlimited list)." See BrandLogo::activePublicList() for the
 * caching pattern and marketing/home.blade.php for the marquee itself.
 */
class BrandLogoMarqueeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
    }

    public function test_homepage_hides_the_marquee_section_when_no_logos_exist(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertDontSee('Trusted by marketers promoting');
    }

    public function test_homepage_shows_active_logos_in_the_marquee(): void
    {
        BrandLogo::factory()->create(['name' => 'Acme Corp', 'is_active' => true]);
        BrandLogo::factory()->create(['name' => 'Hidden Co', 'is_active' => false]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Trusted by marketers promoting');
        $response->assertSee('Acme Corp', false);
        $response->assertDontSee('Hidden Co', false);
    }

    public function test_the_list_is_unlimited_and_ordered_by_sort_order(): void
    {
        BrandLogo::factory()->create(['name' => 'Third', 'sort_order' => 3]);
        BrandLogo::factory()->create(['name' => 'First', 'sort_order' => 1]);
        BrandLogo::factory()->create(['name' => 'Second', 'sort_order' => 2]);

        $list = BrandLogo::activePublicList();

        $this->assertCount(3, $list);
        $this->assertSame(['First', 'Second', 'Third'], $list->pluck('name')->all());
    }

    public function test_the_active_list_is_cached_and_busted_on_save(): void
    {
        $logo = BrandLogo::factory()->create(['is_active' => true]);

        BrandLogo::activePublicList();

        DB::enableQueryLog();
        BrandLogo::activePublicList();
        $this->assertEmpty(DB::getQueryLog(), 'A cached read should not hit the database again.');
        DB::disableQueryLog();

        $logo->update(['is_active' => false]);

        $this->assertCount(0, BrandLogo::activePublicList());
    }

    public function test_a_department_scoped_sub_account_without_content_access_cannot_reach_the_resource(): void
    {
        $subAccount = User::factory()->create();
        $subAccount->assignRole('admin_sub');
        $subAccount->syncPermissions([]);

        $this->actingAs($subAccount);
        $this->assertFalse(BrandLogoResource::canAccess());

        $subAccount->syncPermissions(['department.content']);
        $this->assertTrue(BrandLogoResource::canAccess());
    }
}
