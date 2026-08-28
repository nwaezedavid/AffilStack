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
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('agency_owner_id')->nullable()->after('id')->constrained('users')->nullOnDelete();
            $table->foreignId('seat_offer_id')->nullable()->after('agency_owner_id')->constrained('offers')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('seat_offer_id');
            $table->dropConstrainedForeignId('agency_owner_id');
        });
    }
};
