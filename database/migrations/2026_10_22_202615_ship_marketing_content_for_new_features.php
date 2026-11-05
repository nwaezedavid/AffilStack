<?php

use App\Support\Features\FeatureCatalog;
use Database\Seeders\FaqItemsSeeder;
use Database\Seeders\HomepageFeaturesSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Ships the marketing content for WordPress publishing, affiliate
     * network connections, the dashboard in your language and the add-on
     * marketplace to sites that already exist: the new feature pages, help
     * answers, and three homepage cards placed before "Earn by sharing it,
     * too". Never
     * overwrites anything an admin has edited. A brand-new site (empty
     * homepage_features table) gets the cards from HomepageFeaturesSeeder.
     */
    public function up(): void
    {
        FeatureCatalog::seedMissing();
        $this->addFaqs();

        if (! DB::table('homepage_features')->exists()) {
            return;
        }

        $closer = DB::table('homepage_features')->where('title', 'Earn by sharing it, too')->first();
        $sortOrder = $closer ? (int) $closer->sort_order : (int) DB::table('homepage_features')->max('sort_order') + 1;
        $added = 0;

        foreach (HomepageFeaturesSeeder::NEW_FEATURE_CARDS as $card) {
            if (DB::table('homepage_features')->where('title', $card['title'])->exists()) {
                continue;
            }

            DB::table('homepage_features')->insert($card + [
                'media_type' => 'none',
                'sort_order' => $sortOrder + $added,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $added++;
        }

        if ($closer && $added > 0) {
            DB::table('homepage_features')->where('id', $closer->id)->update(['sort_order' => $sortOrder + $added]);
        }

        Cache::forget('homepage_features:active_list');
    }

    /**
     * The new answers go after the existing FAQs; questions an admin
     * already added (or the seeder created) are left alone.
     */
    private function addFaqs(): void
    {
        if (! DB::table('faq_items')->exists()) {
            return;
        }

        $sortOrder = (int) DB::table('faq_items')->max('sort_order') + 1;

        foreach (FaqItemsSeeder::NEW_FEATURE_FAQS as $faq) {
            if (DB::table('faq_items')->where('question', $faq['question'])->exists()) {
                continue;
            }

            DB::table('faq_items')->insert($faq + ['sort_order' => $sortOrder++, 'is_published' => true, 'created_at' => now(), 'updated_at' => now()]);
        }

        Cache::forget('faq_items:published_grouped');
    }

    public function down(): void
    {
        //
    }
};
