<?php

namespace Database\Seeders;

use App\Models\PaymentGatewaySetting;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Flutterwave starts enabled (matching this app's behavior before the
 * multi-gateway settings existed — it falls back to .env credentials until
 * an admin enters real ones on Billing > Payment Gateways). Stripe starts
 * disabled until an admin configures and verifies it.
 */
class PaymentGatewaySettingsSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        PaymentGatewaySetting::firstOrCreate(['gateway' => 'flutterwave'], ['is_enabled' => true]);
        PaymentGatewaySetting::firstOrCreate(['gateway' => 'stripe'], ['is_enabled' => false]);
    }
}
