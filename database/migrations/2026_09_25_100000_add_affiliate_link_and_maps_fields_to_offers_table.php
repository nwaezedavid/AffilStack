<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Guided product-to-leads flow: collects the affiliate link alongside the
 * existing product_name/product_url/affiliate_network inputs (previously
 * nowhere in the schema at all), and lets OfferResearchService's AI response
 * suggest a ready-to-use Google Places search (niche + location) whenever
 * recommended_channel or secondary_channel comes back "google_maps" — see
 * OfferResearchService::research() and the "Recommended next step" card on
 * offers/show.blade.php, which links straight into Local Leads pre-filled
 * with these two values.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('offers', function (Blueprint $table) {
            $table->string('affiliate_link')->nullable()->after('affiliate_network');
            $table->string('suggested_maps_niche')->nullable()->after('recommended_angle');
            $table->string('suggested_maps_location')->nullable()->after('suggested_maps_niche');
        });
    }

    public function down(): void
    {
        Schema::table('offers', function (Blueprint $table) {
            $table->dropColumn(['affiliate_link', 'suggested_maps_niche', 'suggested_maps_location']);
        });
    }
};
