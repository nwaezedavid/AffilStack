<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('product_name');
            $table->string('product_url');
            $table->string('affiliate_network');
            $table->string('status')->default('researching'); // researching, ready, archived
            $table->text('ideal_customer_summary')->nullable();
            $table->text('where_to_find')->nullable();
            $table->string('recommended_channel')->nullable();
            $table->text('recommended_angle')->nullable();
            $table->json('research_data')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offers');
    }
};
