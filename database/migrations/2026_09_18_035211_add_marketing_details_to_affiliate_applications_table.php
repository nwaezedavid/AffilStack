<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('affiliate_applications', function (Blueprint $table) {
            // "Collect all the necessary information about them and how
            // they intend to promote us" — beyond the free-text
            // promotion_channels field, these give the admin a quick,
            // filterable read on an applicant's reach and track record
            // before approving them.
            $table->string('website_url')->nullable()->after('phone');
            $table->string('audience_size')->nullable()->after('promotion_channels'); // under_1k, 1k_10k, 10k_100k, over_100k
            $table->string('experience_level')->nullable()->after('audience_size'); // new, some_experience, experienced
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('affiliate_applications', function (Blueprint $table) {
            $table->dropColumn(['website_url', 'audience_size', 'experience_level']);
        });
    }
};
