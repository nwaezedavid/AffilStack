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
        Schema::table('offers', function (Blueprint $table) {
            // The jurisdiction whose disclosure wording applies to this
            // offer's audience — not necessarily the affiliate's own
            // location. See config/disclosure.php and DisclosureService.
            $table->string('disclosure_country')->default('US')->after('affiliate_network');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('offers', function (Blueprint $table) {
            $table->dropColumn('disclosure_country');
        });
    }
};
