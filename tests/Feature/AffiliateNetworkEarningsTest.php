<?php

namespace Tests\Feature;

use App\Jobs\SyncNetworkConnection;
use App\Models\Earning;
use App\Models\Generation;
use App\Models\NetworkConnection;
use App\Models\NetworkProgram;
use App\Models\Offer;
use App\Models\SiteSetting;
use App\Models\TrackedLink;
use App\Models\User;
use App\Support\ExchangeRates;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Feature 5: commissions synced from networks land in Earnings, matched
 * to the exact piece of content by sub-ID, kept up to date as their status
 * changes, and added up across currencies in the customer's display
 * currency.
 */
class AffiliateNetworkEarningsTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected NetworkConnection $connection;

    protected Offer $offer;

    protected Generation $article;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
        SiteSetting::set(ExchangeRates::SETTING, json_encode(['date' => '2026-10-15', 'rates' => ['USD' => 1.0, 'GBP' => 0.8, 'EUR' => 0.9]]));
        config(['services.paystack.usd_to_ngn_rate' => 1500]);

        $this->user = User::factory()->create(['country' => 'GB']);
        $this->user->assignRole('user');
        $this->connection = NetworkConnection::factory()->create(['user_id' => $this->user->id]);
        $this->offer = Offer::create(['user_id' => $this->user->id, 'product_name' => 'Shoe Shop', 'product_url' => 'https://shoes.example', 'affiliate_network' => 'Awin', 'status' => 'ready']);
        $this->article = $this->offer->generations()->create(['user_id' => $this->user->id, 'module' => 'blog_article', 'status' => 'completed', 'output' => 'x', 'output_meta' => ['title' => 'Best running shoes 2026']]);
        TrackedLink::create(['user_id' => $this->user->id, 'offer_id' => $this->offer->id, 'generation_id' => $this->article->id, 'module' => 'blog_article', 'code' => 'abc1234', 'destination_url' => 'https://www.awin1.com/cread.php?awinmid=7001', 'clicks_count' => 40]);
    }

    /** @var array<int, array<string, mixed>> */
    protected array $awinTransactions = [];

    /** @var array<int, array<string, mixed>> */
    protected array $awinJoined = [];

    protected bool $awinFaked = false;

    /**
     * What the fake Awin API answers; can change between syncs.
     */
    protected function fakeAwin(array $transactions, array $joined = []): void
    {
        $this->awinTransactions = $transactions;
        $this->awinJoined = $joined;

        if ($this->awinFaked) {
            return;
        }

        $this->awinFaked = true;

        Http::fake(function ($request) {
            return match (true) {
                str_contains($request->url(), 'relationship=joined') => Http::response($this->awinJoined),
                str_contains($request->url(), '/programmes') => Http::response([]),
                str_contains($request->url(), '/transactions/') => Http::response($this->awinTransactions),
                default => Http::response([], 404),
            };
        });
    }

    protected function transaction(int $id, string $status, float $amount, ?string $clickRef = 'abc1234', int $advertiser = 7001, bool $paid = false): array
    {
        return ['id' => $id, 'advertiserId' => $advertiser, 'commissionStatus' => $status, 'commissionAmount' => ['amount' => $amount, 'currency' => 'GBP'], 'clickRefs' => ['clickRef' => $clickRef], 'transactionDate' => '2026-10-01T12:00:00', 'paidToPublisher' => $paid];
    }

    public function test_a_sync_imports_commissions_and_traces_them_to_the_article(): void
    {
        $this->fakeAwin([$this->transaction(900, 'pending', 12.40), $this->transaction(901, 'approved', 8.00, null)]);

        SyncNetworkConnection::dispatchSync($this->connection);

        $traced = Earning::where('external_ref', '900')->sole();
        $this->assertSame('awin', $traced->network);
        $this->assertSame('network_sync', $traced->source);
        $this->assertSame(1240, $traced->amount_cents);
        $this->assertSame('GBP', $traced->currency);
        $this->assertSame(1550, $traced->usd_cents);
        $this->assertSame($this->offer->id, $traced->offer_id);
        $this->assertSame($this->article->id, $traced->generation_id);
        $this->assertNotNull($traced->tracked_link_id);

        $this->assertNull(Earning::where('external_ref', '901')->sole()->offer_id);

        $this->connection->refresh();
        $this->assertSame('connected', $this->connection->status);
        $this->assertNotNull($this->connection->last_synced_at);
        $this->assertSame(2, $this->connection->last_sync_new);
    }

    public function test_status_changes_update_the_same_earning_and_reversals_stop_counting(): void
    {
        $this->fakeAwin([$this->transaction(900, 'pending', 12.40), $this->transaction(902, 'pending', 5.00)]);
        SyncNetworkConnection::dispatchSync($this->connection);

        $this->connection->update(['last_synced_at' => now()->subDay()]);
        $this->fakeAwin([$this->transaction(900, 'approved', 12.40, paid: true), $this->transaction(902, 'declined', 5.00)]);
        SyncNetworkConnection::dispatchSync($this->connection);

        $this->assertSame(2, Earning::count());
        $this->assertSame('paid', Earning::where('external_ref', '900')->value('status'));
        $this->assertSame('declined', Earning::where('external_ref', '902')->value('status'));
        $this->assertSame(2, $this->connection->fresh()->last_sync_updated);

        $this->actingAs($this->user)->get(route('earnings.index'))
            ->assertOk()
            ->assertViewHas('totals', fn ($totals) => $totals['total_cents'] === 1550 && $totals['paid_cents'] === 1550 && $totals['pending_cents'] === 0);
    }

    public function test_a_hand_assigned_offer_isnt_overwritten_by_a_later_sync_without_a_match(): void
    {
        $this->fakeAwin([$this->transaction(905, 'pending', 3.00, null)]);
        SyncNetworkConnection::dispatchSync($this->connection);

        $other = Offer::create(['user_id' => $this->user->id, 'product_name' => 'Other', 'product_url' => 'https://o.example', 'affiliate_network' => 'Awin', 'status' => 'ready']);
        Earning::where('external_ref', '905')->update(['offer_id' => $other->id]);

        $this->fakeAwin([$this->transaction(905, 'approved', 3.00, null)]);
        SyncNetworkConnection::dispatchSync($this->connection);

        $earning = Earning::where('external_ref', '905')->sole();
        $this->assertSame('approved', $earning->status);
        $this->assertSame($other->id, $earning->offer_id);
    }

    public function test_sales_without_a_sub_id_match_the_product_added_from_that_program(): void
    {
        $program = NetworkProgram::create(['network_connection_id' => $this->connection->id, 'external_id' => '7001', 'name' => 'Shoe Shop', 'status' => 'joined']);
        $this->offer->update(['network_program_id' => $program->id]);
        $this->fakeAwin([$this->transaction(906, 'pending', 4.00, null)], [['id' => 7001, 'name' => 'Shoe Shop']]);

        SyncNetworkConnection::dispatchSync($this->connection);

        $this->assertSame($this->offer->id, Earning::where('external_ref', '906')->value('offer_id'));
    }

    public function test_sub_ids_never_match_another_accounts_links(): void
    {
        $stranger = User::factory()->create();
        $theirOffer = Offer::create(['user_id' => $stranger->id, 'product_name' => 'Theirs', 'product_url' => 'https://t.example', 'affiliate_network' => 'Awin', 'status' => 'ready']);
        TrackedLink::create(['user_id' => $stranger->id, 'offer_id' => $theirOffer->id, 'module' => 'blog_article', 'code' => 'zzz9999', 'destination_url' => 'https://t.example']);
        $this->fakeAwin([$this->transaction(907, 'pending', 4.00, 'zzz9999')]);

        SyncNetworkConnection::dispatchSync($this->connection);

        $this->assertNull(Earning::where('external_ref', '907')->value('offer_id'));
    }

    public function test_refused_keys_mark_the_connection_for_attention(): void
    {
        Http::fake(['api.awin.com/*' => Http::response(['error' => 'unauthorized'], 401)]);

        SyncNetworkConnection::dispatchSync($this->connection);

        $this->connection->refresh();
        $this->assertSame('error', $this->connection->status);
        $this->assertStringContainsString('didn\'t accept these keys', $this->connection->last_error);
        $this->assertNull($this->connection->last_synced_at);

        $this->actingAs($this->user)->get(route('networks.index'))->assertOk()->assertSee('Needs attention')->assertSee('Update keys');
    }

    public function test_a_temporary_outage_keeps_the_connection_connected(): void
    {
        Http::fake(['api.awin.com/*' => Http::response('', 503)]);

        SyncNetworkConnection::dispatchSync($this->connection);

        $this->assertSame('connected', $this->connection->fresh()->status);
        $this->assertStringContainsString('HTTP 503', $this->connection->fresh()->last_error);
    }

    public function test_programs_that_disappear_are_removed_unless_a_product_uses_them_or_they_were_added_by_hand(): void
    {
        $gone = NetworkProgram::create(['network_connection_id' => $this->connection->id, 'external_id' => '1', 'name' => 'Gone', 'status' => 'available']);
        $used = NetworkProgram::create(['network_connection_id' => $this->connection->id, 'external_id' => '2', 'name' => 'Used', 'status' => 'joined']);
        $manual = NetworkProgram::create(['network_connection_id' => $this->connection->id, 'external_id' => '3', 'name' => 'Manual', 'status' => 'joined', 'meta' => ['manual' => true]]);
        $this->offer->update(['network_program_id' => $used->id]);
        $this->fakeAwin([], [['id' => 7001, 'name' => 'Shoe Shop']]);

        SyncNetworkConnection::dispatchSync($this->connection);

        $this->assertNull($gone->fresh());
        $this->assertNotNull($used->fresh());
        $this->assertNotNull($manual->fresh());
        $this->assertSame('Shoe Shop', NetworkProgram::where('external_id', '7001')->value('name'));
    }

    public function test_the_daily_command_queues_every_active_connection(): void
    {
        Queue::fake();
        NetworkConnection::factory()->network('cj', ['access_token' => 'x'])->create(['status' => 'error']);
        NetworkConnection::create(['user_id' => $this->user->id, 'network' => 'digistore24', 'status' => 'pending']);

        $this->artisan('networks:sync')->assertSuccessful();

        Queue::assertPushed(SyncNetworkConnection::class, 2);
    }

    public function test_totals_add_up_currencies_in_the_display_currency(): void
    {
        Earning::create(['user_id' => $this->user->id, 'network' => 'awin', 'amount_cents' => 800, 'currency' => 'GBP', 'status' => 'approved', 'converted_at' => '2026-10-01', 'offer_id' => $this->offer->id, 'generation_id' => $this->article->id]);
        Earning::create(['user_id' => $this->user->id, 'network' => 'impact', 'amount_cents' => 1000, 'currency' => 'USD', 'status' => 'pending', 'converted_at' => '2026-10-02']);
        Earning::create(['user_id' => $this->user->id, 'network' => 'impact', 'amount_cents' => 9999, 'currency' => 'USD', 'status' => 'declined', 'converted_at' => '2026-10-02']);

        $this->actingAs($this->user)->get(route('earnings.index'))
            ->assertOk()
            ->assertViewHas('currency', 'USD')
            ->assertViewHas('totals', fn ($totals) => $totals['total_cents'] === 2000 && $totals['approved_cents'] === 1000)
            ->assertSee('$20.00')
            ->assertSee('£8.00')
            ->assertSee('converted at daily exchange rates')
            ->assertSee('By piece of content')
            ->assertSee('Best running shoes 2026');

        $this->actingAs($this->user)->patch(route('earnings.currency'), ['currency' => 'NGN'])->assertSessionHas('success');

        $this->actingAs($this->user)->get(route('earnings.index'))
            ->assertViewHas('currency', 'NGN')
            ->assertViewHas('totals', fn ($totals) => $totals['total_cents'] === 3000000)
            ->assertSee('₦30,000.00');
    }

    public function test_nigerian_accounts_see_naira_by_default(): void
    {
        $this->user->update(['country' => 'NG']);

        $this->actingAs($this->user)->get(route('earnings.index'))->assertViewHas('currency', 'NGN');
    }

    public function test_exchange_rates_refresh_from_the_ecb_and_fill_in_missing_dollar_values(): void
    {
        Http::fake([ExchangeRates::ECB_URL => Http::response('<gesmes:Envelope><Cube><Cube time="2026-10-16"><Cube currency="USD" rate="1.1000"/><Cube currency="GBP" rate="0.8800"/><Cube currency="JPY" rate="165.00"/></Cube></Cube></gesmes:Envelope>')]);
        $earning = Earning::create(['user_id' => $this->user->id, 'network' => 'other', 'amount_cents' => 10000, 'currency' => 'XYZ', 'status' => 'approved', 'converted_at' => '2026-10-01']);
        $this->assertNull($earning->usd_cents);

        $this->artisan('fx:refresh')->assertSuccessful();

        $this->assertSame('2026-10-16', ExchangeRates::updatedOn());
        $this->assertEqualsWithDelta(0.8, ExchangeRates::perUsd('GBP'), 0.0001);
        $this->assertEqualsWithDelta(1 / 1.1, ExchangeRates::perUsd('EUR'), 0.0001);
        $this->assertSame(1000, ExchangeRates::toUsdCents(800, 'GBP'));
        $this->assertNull($earning->fresh()->usd_cents, 'unknown currencies stay unconverted');
    }

    public function test_a_failed_rate_refresh_keeps_the_last_rates(): void
    {
        Http::fake([ExchangeRates::ECB_URL => Http::response('', 500)]);

        $this->artisan('fx:refresh')->assertSuccessful();

        $this->assertSame('2026-10-15', ExchangeRates::updatedOn());
        $this->assertEqualsWithDelta(0.8, ExchangeRates::perUsd('GBP'), 0.0001);
    }

    public function test_manual_entries_can_be_in_other_currencies(): void
    {
        $this->actingAs($this->user)->post(route('earnings.store'), [
            'network' => 'awin', 'amount' => '10.00', 'currency' => 'EUR', 'status' => 'approved', 'converted_at' => now()->toDateString(),
        ])->assertSessionHas('success');

        $earning = Earning::sole();
        $this->assertSame('EUR', $earning->currency);
        $this->assertSame(1111, $earning->usd_cents);
    }
}
