<?php

namespace Tests\Feature;

use App\Filament\Pages\AffiliateNetworkSettings;
use App\Filament\Resources\Plans\Pages\EditPlan;
use App\Jobs\RunOfferResearch;
use App\Jobs\SyncNetworkConnection;
use App\Models\NetworkConnection;
use App\Models\NetworkProgram;
use App\Models\Offer;
use App\Models\Plan;
use App\Models\SiteSetting;
use App\Models\Subscription;
use App\Models\TrackedLink;
use App\Models\User;
use App\Services\Links\LinkCloakingService;
use App\Services\Networks\Adapters\Digistore24Adapter;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Feature 5: connecting affiliate networks (plan allowance, guided key
 * forms, Digistore24 approval), programs becoming products with the
 * network's tracked link, switching a product's link source, and the
 * redirect passing each link's code in the network's sub-ID parameter.
 */
class AffiliateNetworkConnectionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
    }

    protected function customer(int $networks = 3, int $credits = 500): User
    {
        $user = User::factory()->create(['credits_balance' => $credits]);
        $user->assignRole('user');
        $plan = Plan::factory()->create(['network_connections_limit' => $networks]);
        Subscription::factory()->create(['user_id' => $user->id, 'plan_id' => $plan->id]);

        return $user->fresh();
    }

    protected function fakeAwinVerify(): void
    {
        Http::fake(['api.awin.com/accounts*' => Http::response(['accounts' => [['accountId' => 45628, 'accountName' => 'Ada Reviews', 'accountType' => 'publisher']]])]);
    }

    public function test_connecting_a_network_checks_the_keys_stores_them_encrypted_and_starts_a_sync(): void
    {
        Queue::fake();
        $this->fakeAwinVerify();
        $user = $this->customer();

        $this->actingAs($user)->get(route('networks.connect', 'awin'))
            ->assertOk()
            ->assertSee('Connect Awin')
            ->assertSee('ui.awin.com/awin-api');

        $this->actingAs($user)->post(route('networks.store', 'awin'), ['api_token' => 'awin-secret-token'])
            ->assertRedirect(route('networks.index'))
            ->assertSessionHas('success');

        $connection = NetworkConnection::sole();
        $this->assertSame('connected', $connection->status);
        $this->assertSame('45628', $connection->account_id);
        $this->assertSame('awin-secret-token', $connection->credential('api_token'));
        $this->assertStringNotContainsString('awin-secret-token', (string) $connection->getRawOriginal('credentials'));
        $this->assertArrayNotHasKey('credentials', $connection->toArray());
        Queue::assertPushed(SyncNetworkConnection::class);

        $this->actingAs($user)->get(route('networks.index'))
            ->assertOk()
            ->assertSee('Ada Reviews')
            ->assertSee('Sync now')
            ->assertDontSee('awin-secret-token');
    }

    public function test_refused_keys_show_why_and_nothing_is_saved(): void
    {
        Http::fake(['api.awin.com/*' => Http::response(['error' => 'unauthorized'], 401)]);
        $user = $this->customer();

        $this->actingAs($user)->from(route('networks.connect', 'awin'))
            ->post(route('networks.store', 'awin'), ['api_token' => 'wrong-token-123'])
            ->assertRedirect(route('networks.connect', 'awin'))
            ->assertSessionHas('error', fn ($message) => str_contains($message, 'didn\'t accept these keys'))
            ->assertSessionMissing('_old_input.api_token');

        $this->assertSame(0, NetworkConnection::count());
    }

    public function test_the_plan_allowance_limits_how_many_networks_connect(): void
    {
        Queue::fake();
        $this->fakeAwinVerify();

        $none = $this->customer(0);
        $this->actingAs($none)->get(route('networks.connect', 'awin'))->assertOk()->assertSee('doesn&#039;t include affiliate network connections', false);
        $this->actingAs($none)->post(route('networks.store', 'awin'), ['api_token' => 'awin-secret-token'])->assertSessionHas('error');

        $one = $this->customer(1);
        NetworkConnection::factory()->network('impact', ['account_sid' => 'IRx', 'auth_token' => 't'])->create(['user_id' => $one->id]);
        $this->actingAs($one)->post(route('networks.store', 'awin'), ['api_token' => 'awin-secret-token'])
            ->assertSessionHas('error', fn ($message) => str_contains($message, '1 network'));

        // Re-entering keys for a network already connected doesn't count twice.
        NetworkConnection::query()->where('user_id', $one->id)->update(['network' => 'awin']);
        $this->actingAs($one)->post(route('networks.store', 'awin'), ['api_token' => 'awin-secret-token'])->assertSessionHas('success');

        $this->assertSame(1, NetworkConnection::where('user_id', $one->id)->count());
        $this->assertSame(0, NetworkConnection::where('user_id', $none->id)->count());
    }

    public function test_only_the_account_owner_manages_connections(): void
    {
        $owner = $this->customer();
        $connection = NetworkConnection::factory()->create(['user_id' => $owner->id]);
        $seat = User::factory()->create(['agency_owner_id' => $owner->id]);
        $seat->assignRole('user');
        $stranger = $this->customer();

        $this->actingAs($seat->fresh())->delete(route('networks.destroy', $connection))->assertForbidden();
        $this->actingAs($stranger)->delete(route('networks.destroy', $connection))->assertNotFound();
        $this->actingAs($stranger)->post(route('networks.sync', $connection))->assertNotFound();

        $this->assertNotNull($connection->fresh());
    }

    public function test_sync_now_queues_a_sync_but_not_more_than_every_few_minutes(): void
    {
        Queue::fake();
        $user = $this->customer();
        $connection = NetworkConnection::factory()->create(['user_id' => $user->id, 'last_synced_at' => now()->subHour()]);

        $this->actingAs($user)->post(route('networks.sync', $connection))->assertSessionHas('success');
        Queue::assertPushed(SyncNetworkConnection::class, 1);

        $connection->update(['last_synced_at' => now()->subMinutes(2)]);
        $this->actingAs($user)->post(route('networks.sync', $connection))->assertSessionHas('error');
        Queue::assertPushed(SyncNetworkConnection::class, 1);
    }

    public function test_disconnecting_deletes_the_keys_and_products_fall_back_to_the_customers_own_link(): void
    {
        $user = $this->customer();
        $connection = NetworkConnection::factory()->create(['user_id' => $user->id]);
        $program = NetworkProgram::create(['network_connection_id' => $connection->id, 'external_id' => '7001', 'name' => 'Shoe Shop', 'status' => 'joined']);
        $offer = Offer::create([
            'user_id' => $user->id, 'product_name' => 'Boots', 'product_url' => 'https://shoes.example/boots', 'affiliate_network' => 'Awin', 'status' => 'ready',
            'affiliate_link' => 'https://my-own-link.example/boots', 'network_program_id' => $program->id, 'network_link' => 'https://www.awin1.com/cread.php?awinmid=7001&awinaffid=45628', 'use_network_link' => true,
        ]);
        $link = TrackedLink::create(['user_id' => $user->id, 'offer_id' => $offer->id, 'module' => 'blog_article', 'code' => 'abc1234', 'destination_url' => $offer->network_link]);

        $this->actingAs($user)->delete(route('networks.destroy', $connection))->assertRedirect(route('networks.index'));

        $this->assertNull($connection->fresh());
        $this->assertSame(0, NetworkProgram::count());
        $this->assertFalse($offer->fresh()->use_network_link);
        $this->assertNull($offer->fresh()->network_program_id);
        $this->assertSame('https://my-own-link.example/boots', $link->fresh()->destination_url);
    }

    public function test_digistore24_can_be_approved_in_the_browser(): void
    {
        Queue::fake();
        SiteSetting::set(Digistore24Adapter::DEVELOPER_KEY_SETTING, Crypt::encryptString('dev-key-999'));
        Http::fake([
            'www.digistore24.com/api/call/requestApiKey' => Http::response(['result' => 'success', 'data' => ['request_url' => 'https://www.digistore24.com/api_key/approve/xyz', 'request_token' => 'tok-1']]),
            'www.digistore24.com/api/call/retrieveApiKey' => Http::response(['result' => 'success', 'data' => ['api_key' => 'user-key-123456', 'request_status' => 'completed']]),
            'www.digistore24.com/api/call/getUserInfo' => Http::response(['result' => 'success', 'data' => ['user_name' => 'AdaAff']]),
        ]);
        $user = $this->customer();

        $this->actingAs($user)->get(route('networks.connect', 'digistore24'))->assertOk()->assertSee('Approve on Digistore24');

        $this->actingAs($user)->post(route('networks.digistore24.approve'))
            ->assertRedirect('https://www.digistore24.com/api_key/approve/xyz');
        $this->assertSame('pending', NetworkConnection::sole()->status);

        $this->actingAs($user)->get(route('networks.digistore24.return'))
            ->assertRedirect(route('networks.index'))
            ->assertSessionHas('success');

        $connection = NetworkConnection::sole();
        $this->assertSame('connected', $connection->status);
        $this->assertSame('AdaAff', $connection->account_id);
        $this->assertSame('user-key-123456', $connection->credential('api_key'));
        $this->assertNull($connection->connect_token);
    }

    public function test_a_cancelled_digistore24_approval_leaves_nothing_behind(): void
    {
        SiteSetting::set(Digistore24Adapter::DEVELOPER_KEY_SETTING, Crypt::encryptString('dev-key-999'));
        Http::fake(['www.digistore24.com/api/call/retrieveApiKey' => Http::response(['result' => 'success', 'data' => ['api_key' => '', 'request_status' => 'aborted']])]);
        $user = $this->customer();
        NetworkConnection::create(['user_id' => $user->id, 'network' => 'digistore24', 'status' => 'pending', 'connect_token' => 'tok-1']);

        $this->actingAs($user)->get(route('networks.digistore24.return'))
            ->assertRedirect(route('networks.connect', 'digistore24'))
            ->assertSessionHas('error');

        $this->assertSame(0, NetworkConnection::count());
    }

    public function test_joined_programs_become_products_with_the_networks_tracked_link(): void
    {
        Queue::fake();
        Http::fake(['api.awin.com/publishers/45628/linkbuilder/generate' => Http::response(['url' => 'https://www.awin1.com/cread.php?awinmid=7001&awinaffid=45628'])]);
        $user = $this->customer();
        $connection = NetworkConnection::factory()->create(['user_id' => $user->id]);
        $program = NetworkProgram::create(['network_connection_id' => $connection->id, 'external_id' => '7001', 'name' => 'Shoe Shop', 'status' => 'joined', 'homepage_url' => 'https://shoes.example', 'deep_linking' => true]);

        $this->actingAs($user)->get(route('networks.programs'))->assertOk()->assertSee('Shoe Shop')->assertSee('Add as product');

        $response = $this->actingAs($user)->post(route('networks.programs.import', $program));

        $offer = Offer::sole();
        $response->assertRedirect(route('offers.show', $offer));
        $this->assertSame('Shoe Shop', $offer->product_name);
        $this->assertSame('Awin', $offer->affiliate_network);
        $this->assertSame($program->id, $offer->network_program_id);
        $this->assertTrue($offer->use_network_link);
        $this->assertStringContainsString('awinmid=7001', $offer->network_link);
        Queue::assertPushed(RunOfferResearch::class);

        // The product's content links now go to the network link.
        $this->assertStringContainsString('awinmid=7001', app(LinkCloakingService::class)->getOrCreateForOffer($offer, 'blog_article')->destination_url);
    }

    public function test_programs_not_joined_link_to_the_network_to_apply(): void
    {
        $user = $this->customer();
        $connection = NetworkConnection::factory()->create(['user_id' => $user->id]);
        $program = NetworkProgram::create(['network_connection_id' => $connection->id, 'external_id' => '7002', 'name' => 'Bag Shop', 'status' => 'available']);

        $this->actingAs($user)->get(route('networks.programs', ['status' => 'available']))
            ->assertOk()
            ->assertSee('Bag Shop')
            ->assertSee('Apply on Awin')
            ->assertSee('https://ui.awin.com/merchant-profile/7002', false);

        $this->actingAs($user)->post(route('networks.programs.import', $program))->assertSessionHas('error');
        $this->assertSame(0, Offer::count());
    }

    public function test_clickbank_products_are_added_by_vendor_nickname(): void
    {
        $user = $this->customer();
        $connection = NetworkConnection::factory()->network('clickbank', ['api_key' => 'API-ABCDEF123456'], 'adaaff')->create(['user_id' => $user->id]);

        $this->actingAs($user)->post(route('networks.programs.add', $connection), ['identifier' => 'Mitolyn'])
            ->assertRedirect()
            ->assertSessionHas('success');

        $program = NetworkProgram::sole();
        $this->assertSame('mitolyn', $program->external_id);
        $this->assertSame('joined', $program->status);
        $this->assertTrue($program->meta['manual']);
        $this->assertSame('https://hop.clickbank.net/?affiliate=adaaff&vendor=mitolyn', $program->tracking_url);

        $this->actingAs($user)->post(route('networks.programs.add', $connection), ['identifier' => 'not valid!'])->assertSessionHas('error');
    }

    public function test_an_existing_product_can_switch_to_the_network_link_and_back(): void
    {
        Http::fake(['api.awin.com/publishers/45628/linkbuilder/generate' => Http::response(['url' => 'https://www.awin1.com/cread.php?awinmid=7001&awinaffid=45628&ued=https%3A%2F%2Fshoes.example%2Fboots'])]);
        $user = $this->customer();
        $connection = NetworkConnection::factory()->create(['user_id' => $user->id]);
        $program = NetworkProgram::create(['network_connection_id' => $connection->id, 'external_id' => '7001', 'name' => 'Shoe Shop', 'status' => 'joined', 'homepage_url' => 'https://www.shoes.example', 'deep_linking' => true]);
        $offer = Offer::create(['user_id' => $user->id, 'product_name' => 'Boots', 'product_url' => 'https://shoes.example/boots', 'affiliate_network' => 'Awin', 'status' => 'ready', 'affiliate_link' => 'https://my-own-link.example/boots']);
        $link = TrackedLink::create(['user_id' => $user->id, 'offer_id' => $offer->id, 'module' => 'blog_article', 'code' => 'abc1234', 'destination_url' => 'https://my-own-link.example/boots']);

        $this->actingAs($user)->get(route('offers.show', $offer))->assertOk()->assertSee('Tracked link')->assertSee('Shoe Shop · Awin (same website)');

        $this->actingAs($user)->post(route('offers.network-link.store', $offer), ['program_id' => $program->id])->assertSessionHas('success');

        $offer->refresh();
        $this->assertTrue($offer->use_network_link);
        $this->assertStringContainsString('ued=https%3A%2F%2Fshoes.example%2Fboots', $link->fresh()->destination_url);
        Http::assertSent(fn ($request) => $request['destinationUrl'] === 'https://shoes.example/boots');

        $this->actingAs($user)->patch(route('offers.network-link.update', $offer), ['use_network_link' => 0])->assertSessionHas('success');
        $this->assertSame('https://my-own-link.example/boots', $link->fresh()->destination_url);

        $this->actingAs($user)->patch(route('offers.network-link.update', $offer), ['use_network_link' => 1]);
        $this->assertStringContainsString('awinmid=7001', $link->fresh()->destination_url);
    }

    public function test_programs_from_someone_elses_network_cant_be_used(): void
    {
        $user = $this->customer();
        $other = NetworkConnection::factory()->create();
        $program = NetworkProgram::create(['network_connection_id' => $other->id, 'external_id' => '1', 'name' => 'Theirs', 'status' => 'joined']);
        $offer = Offer::create(['user_id' => $user->id, 'product_name' => 'Mine', 'product_url' => 'https://mine.example', 'affiliate_network' => 'Awin', 'status' => 'ready']);

        $this->actingAs($user)->post(route('offers.network-link.store', $offer), ['program_id' => $program->id])->assertSessionHas('error');
        $this->actingAs($user)->post(route('networks.programs.import', $program))->assertNotFound();
        $this->assertNull($offer->fresh()->network_program_id);
    }

    public function test_clicks_pass_the_link_code_in_each_networks_sub_id_parameter(): void
    {
        $user = $this->customer();
        $offer = Offer::create(['user_id' => $user->id, 'product_name' => 'X', 'product_url' => 'https://x.example', 'affiliate_network' => 'Awin', 'status' => 'ready']);

        $cases = [
            'https://www.awin1.com/cread.php?awinmid=1&awinaffid=2&ued=https%3A%2F%2Fx.example' => 'clickref=c0de001',
            'https://hop.clickbank.net/?affiliate=a&vendor=b' => 'tid=c0de002',
            'https://www.tkqlhce.com/click-1-2' => 'sid=c0de003',
            'https://acme.sjv.io/c/1/2/3' => 'subId1=c0de004',
            'https://notion.grsm.io/ada' => 'sid=c0de005',
            'https://www.checkout-ds24.com/redir/1/a/' => 'sid1=c0de006',
            'https://x.example/?ref=me' => 'subid=c0de007',
        ];

        $i = 0;
        foreach ($cases as $destination => $expected) {
            $i++;
            TrackedLink::create(['user_id' => $user->id, 'offer_id' => $offer->id, 'module' => 'm'.$i, 'code' => 'c0de00'.$i, 'destination_url' => $destination]);

            $location = (string) $this->get('/go/c0de00'.$i)->assertRedirect()->headers->get('Location');
            $this->assertStringContainsString($expected, $location, $destination);
        }
    }

    public function test_each_piece_of_content_gets_its_own_link_code(): void
    {
        $user = $this->customer();
        $offer = Offer::create(['user_id' => $user->id, 'product_name' => 'X', 'product_url' => 'https://x.example', 'affiliate_network' => 'Awin', 'status' => 'ready']);
        $first = $offer->generations()->create(['user_id' => $user->id, 'module' => 'blog_article', 'status' => 'completed', 'output' => 'Buy '.Offer::LINK_PLACEHOLDER]);
        $second = $offer->generations()->create(['user_id' => $user->id, 'module' => 'blog_article', 'status' => 'completed', 'output' => 'Buy '.Offer::LINK_PLACEHOLDER]);

        $a = $offer->cloak($first->output, 'blog_article', false, $first);
        $b = $offer->cloak($second->output, 'blog_article', false, $second);
        $again = $offer->cloak($first->output, 'blog_article', false, $first);

        $this->assertNotSame($a, $b);
        $this->assertSame($a, $again);
        $this->assertSame(2, TrackedLink::whereNotNull('generation_id')->count());
        $this->assertSame($first->id, TrackedLink::where('generation_id', $first->id)->value('generation_id'));
    }

    public function test_admins_set_the_network_allowance_per_plan_and_the_digistore24_developer_key(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $plan = Plan::factory()->create();

        Livewire::actingAs($admin)
            ->test(EditPlan::class, ['record' => $plan->getRouteKey()])
            ->fillForm(['network_connections_limit' => 3])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertSame(3, $plan->fresh()->network_connections_limit);

        Livewire::actingAs($admin)
            ->test(AffiliateNetworkSettings::class)
            ->fillForm(['digistore24_developer_key' => 'dev-key-xyz'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('dev-key-xyz', Digistore24Adapter::developerKey());
        $this->assertNotSame('dev-key-xyz', SiteSetting::get(Digistore24Adapter::DEVELOPER_KEY_SETTING));
    }
}
