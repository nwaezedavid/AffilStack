<?php

namespace Tests\Feature;

use App\Models\PaymentTransaction;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Audit gap #2: PaymentTransaction has recorded every charge since Phase
 * 1, but the Billing & Plan page never surfaced any of it.
 */
class BillingHistoryTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
        $this->user = User::factory()->create();
        $this->user->assignRole('user');
    }

    public function test_the_billing_page_lists_successful_and_failed_transactions(): void
    {
        PaymentTransaction::create([
            'user_id' => $this->user->id, 'type' => 'subscription', 'gateway' => 'flutterwave',
            'tx_ref' => 'ref-1', 'amount_cents' => 6700, 'currency' => 'USD', 'status' => 'successful',
            'processed_at' => now(),
        ]);
        PaymentTransaction::create([
            'user_id' => $this->user->id, 'type' => 'renewal', 'gateway' => 'stripe',
            'tx_ref' => 'ref-2', 'amount_cents' => 6700, 'currency' => 'USD', 'status' => 'failed',
        ]);

        $response = $this->actingAs($this->user)->get(route('billing.index'));

        $response->assertOk();
        $response->assertSee('67.00');
        $response->assertSee('successful');
        $response->assertSee('failed');
    }

    public function test_pending_abandoned_checkouts_are_not_shown(): void
    {
        PaymentTransaction::create([
            'user_id' => $this->user->id, 'type' => 'subscription', 'gateway' => 'flutterwave',
            'tx_ref' => 'ref-1', 'amount_cents' => 6700, 'currency' => 'USD', 'status' => 'pending',
        ]);

        $this->actingAs($this->user)
            ->get(route('billing.index'))
            ->assertOk()
            ->assertSee('No payments recorded yet');
    }

    public function test_a_user_never_sees_another_users_transactions(): void
    {
        $other = User::factory()->create();
        PaymentTransaction::create([
            'user_id' => $other->id, 'type' => 'subscription', 'gateway' => 'flutterwave',
            'tx_ref' => 'ref-other', 'amount_cents' => 12700, 'currency' => 'USD', 'status' => 'successful',
        ]);

        $this->actingAs($this->user)
            ->get(route('billing.index'))
            ->assertOk()
            ->assertSee('No payments recorded yet')
            ->assertDontSee('127.00');
    }
}
