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
        Schema::table('generations', function (Blueprint $table) {
            $table->string('target_market')->nullable()->after('published_at');
            $table->foreignId('localized_from_id')->nullable()->after('target_market')->constrained('generations')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('generations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('localized_from_id');
            $table->dropColumn('target_market');
        });
    }
};
