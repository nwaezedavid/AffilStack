<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Agent 04: advertising audiences — a named set of rules evaluated over
     * analytics_profiles (see SegmentEvaluator). Presets are seeded and can
     * be tuned but not deleted.
     */
    public function up(): void
    {
        Schema::create('analytics_segments', function (Blueprint $table) {
            $table->id();
            $table->string('key', 60)->nullable()->unique();
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->string('purpose', 20)->default('target');
            $table->json('rules');
            $table->boolean('is_preset')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_segments');
    }
};
