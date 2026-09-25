<?php

namespace Tests\Feature;

use App\Models\ApiToken;
use App\Models\ApiWalletTransaction;
use App\Models\Offer;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * MeterApiUsage — the HTTP-layer half of the API usage prepay wallet (see
 * ApiWalletManagerTest for the ledger/service-level behavior it delegates
 * to). These tests exercise the real /v1/* routes end-to-end rather than
 * calling the middleware directly, since the whole point is confirming it's
 * wired onto exactly the one route config('api_billing.costs') prices, and
 * nowhere else.
 */
class ApiWalletMeteringTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
    }

    protected function tokenFor(User $user): string
    {
        return ApiToken::generate($user, 'Test token')['plainText'];
    }

    protected function authHeaders(string $plainText): array
    {
        return ['Authorization' => "Bearer {$plainText}"];
    }

    public function test_a_free_endpoint_is_unaffected_by_a_zero_wallet_balance(): void
    {
        $user = User::factory()->create(['api_wallet_balance_cents' => 0]);
        $user->assignRole('user');

        $this->getJson('/api/v1/me', $this->authHeaders($this->tokenFor($user)))
            ->assertOk()
            ->assertJson(['api_wallet_balance_cents' => 0]);
    }

    public function test_a_metered_call_with_sufficient_balance_is_charged_and_recorded(): void
    {
        $user = User::factory()->create(['credits_balance' => 1000, 'api_wallet_balance_cents' => 1000]);
        $user->assignRole('user');

        $response = $this->postJson('/api/v1/offers', [
            'product_name' => 'API Offer',
            'product_url' => 'https://api.example',
            'affiliate_network' => 'ShareASale',
        ], $this->authHeaders($this->tokenFor($user)));

        $response->assertStatus(201);
        $this->assertSame(925, $user->fresh()->api_wallet_balance_cents);

        $transaction = ApiWalletTransaction::where('user_id', $user->id)->where('type', 'usage')->sole();
        $this->assertSame(-75, $transaction->amount_cents);
        $this->assertSame(925, $transaction->balance_after_cents);
        $this->assertSame('api:api.v1.offers.store', $transaction->description);
    }

    public function test_a_metered_call_with_insufficient_balance_is_blocked_before_reaching_the_underlying_action(): void
    {
        // Plenty of credits — isolates this to the wallet gate specifically,
        // mirroring ApiV1EndpointsTest's own isolation of the credits gate.
        $user = User::factory()->create(['credits_balance' => 1000, 'api_wallet_balance_cents' => 50]);
        $user->assignRole('user');

        $response = $this->postJson('/api/v1/offers', [
            'product_name' => 'Should not be created',
            'product_url' => 'https://api.example',
            'affiliate_network' => 'ShareASale',
        ], $this->authHeaders($this->tokenFor($user)));

        $response->assertStatus(402)->assertJsonStructure(['message', 'wallet_balance_cents', 'required_cents', 'top_up_url']);
        $response->assertJson(['wallet_balance_cents' => 50, 'required_cents' => 75]);

        // Never reached OffersController::store() at all — no offer, and the
        // untouched balance below proves nothing was debited then refunded.
        $this->assertDatabaseMissing('offers', ['product_name' => 'Should not be created']);
        $this->assertSame(50, $user->fresh()->api_wallet_balance_cents);
        $this->assertSame(0, ApiWalletTransaction::where('user_id', $user->id)->count());
    }

    public function test_a_charge_is_refunded_when_the_underlying_request_still_fails(): void
    {
        // Wallet is funded but credits are not — the request is accepted and
        // billed by MeterApiUsage, then OffersController's own separate
        // credits check fails afterward, so the wallet charge must reverse.
        $user = User::factory()->create(['credits_balance' => 0, 'api_wallet_balance_cents' => 1000]);
        $user->assignRole('user');

        $response = $this->postJson('/api/v1/offers', [
            'product_name' => 'Fails on credits',
            'product_url' => 'https://api.example',
            'affiliate_network' => 'ShareASale',
        ], $this->authHeaders($this->tokenFor($user)));

        $response->assertStatus(402)->assertJson(['message' => 'Not enough credits for offer research.']);

        // Refunded back to exactly where it started — a charge then an
        // equal-and-opposite refund, not simply "never charged".
        $this->assertSame(1000, $user->fresh()->api_wallet_balance_cents);
        $types = ApiWalletTransaction::where('user_id', $user->id)->orderBy('id')->pluck('type', 'id');
        $this->assertSame(['usage', 'refund'], array_values($types->toArray()));
    }

    public function test_reading_a_single_offer_stays_free(): void
    {
        $user = User::factory()->create(['api_wallet_balance_cents' => 0]);
        $user->assignRole('user');
        $offer = Offer::create(['user_id' => $user->id, 'product_name' => 'A', 'product_url' => 'https://a.example', 'affiliate_network' => 'ShareASale', 'status' => 'ready']);

        $this->getJson("/api/v1/offers/{$offer->id}", $this->authHeaders($this->tokenFor($user)))->assertOk();

        $this->assertSame(0, $user->fresh()->api_wallet_balance_cents);
    }
}
