<?php

namespace Tests\Feature;

use App\Models\FaqItem;
use App\Models\HomepageFeature;
use App\Models\Plan;
use Database\Seeders\FaqItemsSeeder;
use Database\Seeders\HomepageFeaturesSeeder;
use Database\Seeders\PlansSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * WordPress publishing, affiliate networks, the dashboard in your language
 * and the add-on marketplace are on the marketing site: feature pages,
 * homepage cards, help answers, menus and the plan limits on Pricing.
 */
class NewFeaturesMarketingTest extends TestCase
{
    use RefreshDatabase;

    protected function runContentMigration(): void
    {
        (require database_path('migrations/2026_10_22_202615_ship_marketing_content_for_new_features.php'))->up();
    }

    public function test_the_new_feature_pages_are_published_in_the_tools_menu(): void
    {
        $response = $this->get(route('home'))->assertOk();

        foreach (['affiliate-networks', 'wordpress-publishing', 'multi-language', 'add-on-marketplace'] as $slug) {
            $response->assertSee('/features/'.$slug, false);
            $this->get('/features/'.$slug)->assertOk();
        }
    }

    public function test_a_new_site_gets_the_new_homepage_cards_and_help_answers_from_the_seeders(): void
    {
        $this->seed([HomepageFeaturesSeeder::class, FaqItemsSeeder::class]);

        $titles = HomepageFeature::orderBy('sort_order')->pluck('title')->all();
        $this->assertCount(12, $titles);
        $this->assertSame('An add-on marketplace', $titles[10]);
        $this->assertSame('Earn by sharing it, too', $titles[11]);
        $this->assertTrue(FaqItem::where('question', 'Which affiliate networks can I connect?')->exists());

        $this->get(route('home'))->assertOk()
            ->assertSee('Your affiliate networks, connected')
            ->assertSee('Straight to your WordPress site')
            ->assertSee('/images/features/add-on-marketplace.webp', false);
    }

    public function test_an_existing_site_gets_the_new_cards_before_the_closing_card_without_touching_its_edits(): void
    {
        HomepageFeature::create(['icon' => 'star', 'title' => 'My own card', 'description' => 'Written by the admin.', 'media_type' => 'none', 'sort_order' => 0, 'is_active' => true]);
        HomepageFeature::create(['icon' => 'banknotes', 'title' => 'Earn by sharing it, too', 'description' => 'Edited.', 'media_type' => 'none', 'sort_order' => 1, 'is_active' => true]);
        FaqItem::create(['question' => 'Can I build and sell add-ons?', 'answer' => 'The admin\'s own answer.', 'category' => 'general', 'sort_order' => 3]);

        $this->runContentMigration();
        $this->runContentMigration();

        $this->assertSame(
            ['My own card', 'Your affiliate networks, connected', 'Straight to your WordPress site', 'An add-on marketplace', 'Earn by sharing it, too'],
            HomepageFeature::orderBy('sort_order')->pluck('title')->all(),
        );
        $this->assertSame('Edited.', HomepageFeature::where('title', 'Earn by sharing it, too')->value('description'));
        $this->assertSame(1, FaqItem::where('question', 'Can I build and sell add-ons?')->count());
        $this->assertSame('The admin\'s own answer.', FaqItem::where('question', 'Can I build and sell add-ons?')->value('answer'));
        $this->assertSame(count(FaqItemsSeeder::NEW_FEATURE_FAQS), FaqItem::count());
    }

    public function test_an_empty_site_is_left_to_the_seeders(): void
    {
        $this->runContentMigration();

        $this->assertSame(0, HomepageFeature::count());
        $this->assertSame(0, FaqItem::count());
    }

    public function test_pricing_shows_each_plans_wordpress_and_network_allowance(): void
    {
        $this->seed(PlansSeeder::class);
        Plan::where('slug', 'starter')->update(['wordpress_sites_limit' => 0]);

        $html = $this->get(route('registration.pricing'))->assertOk()->getContent();

        $this->assertStringContainsString('3 WordPress sites', $html);
        $this->assertStringContainsString('Unlimited WordPress sites', $html);
        $this->assertStringContainsString('1 affiliate network connection', $html);
        $this->assertStringContainsString('Unlimited affiliate network connections', $html);
        $this->assertStringNotContainsString('1 WordPress site<', $html);
    }

    public function test_the_marketplace_and_developer_portal_are_in_the_default_menus(): void
    {
        $this->get(route('home'))->assertOk()
            ->assertSee(route('marketplace.index'), false)
            ->assertSee(route('developer.home'), false);
    }
}
