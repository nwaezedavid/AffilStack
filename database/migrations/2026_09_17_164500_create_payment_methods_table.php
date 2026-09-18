<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Saved payment methods (audit item #2) — captured automatically from
 * every successful payment's gateway response (card/authorization details
 * for Stripe/Flutterwave/Paystack, an email for PayPal), never entered
 * directly by this app. See PaymentMethodRecorder.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('gateway');
            $table->string('type')->default('card'); // card, paypal
            $table->string('brand')->nullable(); // visa, mastercard, ...
            $table->string('last4', 4)->nullable();
            $table->unsignedTinyInteger('exp_month')->nullable();
            $table->unsignedSmallInteger('exp_year')->nullable();
            $table->string('label')->nullable(); // e.g. a PayPal email
            $table->string('gateway_customer_id')->nullable();
            $table->text('gateway_token')->nullable(); // encrypted at rest via the model cast
            $table->boolean('is_default')->default(false);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'is_default']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_methods');
    }
};
