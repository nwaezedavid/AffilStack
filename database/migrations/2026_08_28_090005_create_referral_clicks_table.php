<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referral_clicks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete(); // the referrer whose link was clicked
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('clicked_at');
            $table->timestamps();

            $table->index(['user_id', 'clicked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referral_clicks');
    }
};
