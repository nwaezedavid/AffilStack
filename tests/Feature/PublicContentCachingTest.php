<?php

namespace Tests\Feature;

use App\Filament\Pages\BrandSettings;
use App\Models\FaqItem;
use App\Models\HomepageFeature;
use App\Models\Plan;
use App\Models\SitePage;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Audit item #7 (caching/performance) — Plan/SitePage/FaqItem/HomepageFeature
 * all cache their public-facing read indefinitely (mirrors
 * SiteSetting::get()/set()) and bust that cache from a model event whenever
 * a row is saved or deleted. These tests care about two things: the cache
 * actually avoids a repeat query, and an edit is never stuck behind stale
 * cached content.
 */
class PublicContentCachingTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_plan_list_is_cached_and_busted_on_save(): void
    {
        $plan = Plan::factory()->create(['is_active' => true, 'sort_order' => 1]);

        $first = Plan::activePublicList();
        $this->assertCount(1, $first);

        DB::enableQueryLog();
        $second = Plan::activePublicList();
        $this->assertEmpty(DB::getQueryLog(), 'A cached read should not hit the database again.');
        DB::disableQueryLog();

        $this->assertSame($first->pluck('id')->all(), $second->pluck('id')->all());

        // Saving a new plan busts the cache — the next read sees it.
        Plan::factory()->create(['is_active' => true, 'sort_order' => 2]);
        $this->assertCount(2, Plan::activePublicList());

        // Deactivating an existing plan also busts it.
        $plan->update(['is_active' => false]);
        $this->assertCount(1, Plan::activePublicList());
    }

    public function test_published_site_page_is_cached_and_busted_on_content_change(): void
    {
        SitePage::create(['slug' => 'about', 'title' => 'About', 'content' => 'Original content', 'is_published' => true]);

        $first = SitePage::published('about');
        $this->assertSame('Original content', $first->content);

        DB::enableQueryLog();
        SitePage::published('about');
        $this->assertEmpty(DB::getQueryLog(), 'A cached read should not hit the database again.');
        DB::disableQueryLog();

        $page = SitePage::where('slug', 'about')->first();
        $page->update(['content' => 'Updated content']);

        $this->assertSame('Updated content', SitePage::published('about')->content);
    }

    public function test_unpublishing_a_site_page_removes_it_from_the_cached_lookup(): void
    {
        $page = SitePage::create(['slug' => 'about', 'title' => 'About', 'content' => 'Hello', 'is_published' => true]);
        $this->assertNotNull(SitePage::published('about'));

        $page->update(['is_published' => false]);

        $this->assertNull(SitePage::published('about'));
    }

    public function test_faq_items_are_cached_grouped_by_category_and_busted_on_save(): void
    {
        FaqItem::create(['question' => 'Q1', 'answer' => 'A1', 'category' => 'billing', 'is_published' => true, 'sort_order' => 1]);
        FaqItem::create(['question' => 'Q2', 'answer' => 'A2', 'category' => 'general', 'is_published' => true, 'sort_order' => 2]);

        $groups = FaqItem::publishedGrouped();
        $this->assertCount(2, $groups);
        $this->assertTrue($groups->has('billing') && $groups->has('general'));

        DB::enableQueryLog();
        FaqItem::publishedGrouped();
        $this->assertEmpty(DB::getQueryLog(), 'A cached read should not hit the database again.');
        DB::disableQueryLog();

        FaqItem::create(['question' => 'Q3', 'answer' => 'A3', 'category' => 'billing', 'is_published' => true, 'sort_order' => 3]);
        $this->assertCount(2, FaqItem::publishedGrouped()->get('billing'));
    }

    public function test_homepage_features_are_cached_and_busted_on_save(): void
    {
        HomepageFeature::create(['title' => 'Feature 1', 'description' => 'Desc', 'media_type' => 'none', 'is_active' => true, 'sort_order' => 1]);

        $first = HomepageFeature::previewAwareActiveList();
        $this->assertCount(1, $first);

        DB::enableQueryLog();
        HomepageFeature::previewAwareActiveList();
        $this->assertEmpty(DB::getQueryLog(), 'A cached read should not hit the database again.');
        DB::disableQueryLog();

        HomepageFeature::create(['title' => 'Feature 2', 'description' => 'Desc', 'media_type' => 'none', 'is_active' => true, 'sort_order' => 2]);
        $this->assertCount(2, HomepageFeature::previewAwareActiveList());
    }

    public function test_cache_public_page_sets_cache_control_on_a_plain_visit(): void
    {
        SitePage::create(['slug' => 'about', 'title' => 'About', 'content' => 'Hello', 'is_published' => true]);

        $response = $this->get('/about');

        $response->assertOk();
        $this->assertStringContainsString('public', (string) $response->headers->get('Cache-Control'));
    }

    public function test_cache_public_page_does_not_cache_a_response_carrying_a_flash_message(): void
    {
        SitePage::create(['slug' => 'about', 'title' => 'About', 'content' => 'Hello', 'is_published' => true]);

        $response = $this->withSession(['error' => 'Something went wrong.'])->get('/about');

        $response->assertOk();
        $this->assertStringNotContainsString('public', (string) $response->headers->get('Cache-Control'));
    }

    /**
     * Regression test for a real bug: every method above was originally
     * implemented as `Cache::rememberForever(..., fn () => static::query()->get())`
     * — caching the Eloquent Model/Collection object directly. That works
     * fine against the 'array' store this whole test file otherwise runs
     * under (phpunit.xml sets CACHE_STORE=array, so nothing is ever really
     * serialized), but under any real persistent store (database, file,
     * Redis) PHP's serialize()/unserialize() round-trip on a hydrated Model
     * comes back as an unusable __PHP_Incomplete_Class, which threw a
     * TypeError on the very next request and 500'd every public page and
     * the billing page. Caching plain getAttributes() arrays and
     * rehydrating with hydrate()/newFromBuilder() (the current
     * implementation) is what fixes it — this test forces the database
     * cache store specifically so that class of bug can never silently
     * come back unnoticed by a suite that otherwise only ever exercises
     * the array store.
     */
    public function test_cached_reads_survive_a_real_persistent_cache_store(): void
    {
        config(['cache.default' => 'database']);

        Plan::factory()->create(['is_active' => true, 'sort_order' => 1, 'name' => 'Starter']);
        SitePage::create(['slug' => 'about', 'title' => 'About', 'content' => 'Hello', 'is_published' => true]);
        FaqItem::create(['question' => 'Q1', 'answer' => 'A1', 'category' => 'billing', 'is_published' => true, 'sort_order' => 1]);
        HomepageFeature::create(['title' => 'Feature 1', 'description' => 'Desc', 'media_type' => 'none', 'is_active' => true, 'sort_order' => 1]);

        // First read populates the database-backed cache; second read
        // rehydrates from the serialized row instead of querying — this is
        // exactly the round trip that broke with cached Eloquent objects.
        Plan::activePublicList();
        SitePage::published('about');
        FaqItem::publishedGrouped();
        HomepageFeature::previewAwareActiveList();

        $plans = Plan::activePublicList();
        $page = SitePage::published('about');
        $faqGroups = FaqItem::publishedGrouped();
        $features = HomepageFeature::previewAwareActiveList();

        $this->assertCount(1, $plans);
        $this->assertSame('Starter', $plans->first()->name);
        $this->assertTrue($plans->first()->exists);

        $this->assertNotNull($page);
        $this->assertSame('Hello', $page->content);
        $this->assertTrue($page->exists);

        $this->assertCount(1, $faqGroups->get('billing'));

        $this->assertCount(1, $features);
        $this->assertSame('Feature 1', $features->first()->title);
    }

    /**
     * Regression test for a real bug found while reviewing the finished
     * platform: SiteSetting::get() is backed by Cache::rememberForever(),
     * which never re-evaluates a key once it's cached. The public homepage
     * (marketing.blade.php) and the admin Brand Settings page
     * (BrandSettings::mount()) both read the 'menu_items' setting, but used
     * to pass different-typed defaults (an array vs. a JSON string) for
     * the case where no value has ever been saved yet. On a fresh
     * install/cache, whichever page loaded first "won" and permanently
     * cached its own default's shape for every other reader of that key —
     * a visitor hitting the homepage before the admin ever opened Brand
     * Settings would poison the cache with an array, and Brand Settings
     * would then crash with "Array to string conversion" trying to
     * json_decode((string) $anArray). Fixed by making both call sites
     * tolerate either shape and by normalizing both defaults to the same
     * (string) type so the cache can no longer be poisoned this way.
     */
    public function test_brand_settings_page_survives_the_homepage_populating_the_menu_items_cache_first(): void
    {
        config(['cache.default' => 'database']);
        $this->seed(RolesSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        // No menu_items row has ever been saved. Visiting the homepage
        // first is what used to seed the cache with an array default.
        $this->get('/')->assertOk();

        Livewire::actingAs($admin)->test(BrandSettings::class)->assertOk();
    }
}
