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
        Schema::create('payment_gateway_settings', function (Blueprint $table) {
            $table->id();
            $table->string('gateway')->unique(); // stripe, flutterwave
            $table->boolean('is_enabled')->default(false);
            $table->text('credentials')->nullable(); // encrypted JSON — see PaymentGatewaySetting cast
            $table->timestamp('last_verified_at')->nullable();
            $table->string('last_verification_status')->nullable(); // success, failed
            $table->text('last_verification_message')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_gateway_settings');
    }
};
