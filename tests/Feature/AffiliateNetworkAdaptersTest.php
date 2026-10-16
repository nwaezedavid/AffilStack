<?php

namespace Tests\Feature;

use App\Models\NetworkConnection;
use App\Models\NetworkProgram;
use App\Models\SiteSetting;
use App\Services\Networks\Adapters\AwinAdapter;
use App\Services\Networks\Adapters\CjAdapter;
use App\Services\Networks\Adapters\ClickBankAdapter;
use App\Services\Networks\Adapters\Digistore24Adapter;
use App\Services\Networks\Adapters\ImpactAdapter;
use App\Services\Networks\Adapters\PartnerStackAdapter;
use App\Services\Networks\NetworkException;
use App\Services\Networks\NetworkRegistry;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Feature 5: each network adapter turns its API's answers (shaped as the
 * networks document them) into AffilStack programs and earnings, builds
 * tracked links and adds the sub-ID in the network's own parameter.
 */
class AffiliateNetworkAdaptersTest extends TestCase
{
    use RefreshDatabase;

    protected function connection(string $network, array $credentials, ?string $accountId = null): NetworkConnection
    {
        return NetworkConnection::factory()->network($network, $credentials, $accountId)->create();
    }

    protected function range(): array
    {
        return [CarbonImmutable::parse('2026-09-01'), CarbonImmutable::parse('2026-10-01')];
    }

    // --- Impact ------------------------------------------------------------

    public function test_impact_verifies_lists_programs_and_reads_actions(): void
    {
        Http::fake([
            'api.impact.com/Mediapartners/IRabc1234567/CompanyInformation' => Http::response(['CompanyName' => 'Ada Media', 'Currency' => 'USD']),
            'api.impact.com/Mediapartners/IRabc1234567/Contracts*' => Http::response(['@numpages' => '1', '@nextpageuri' => '', 'Contracts' => [
                ['CampaignId' => '1001', 'Terms' => ['Currency' => 'USD', 'EventPayouts' => [['EventTypeName' => 'Sale', 'DefaultPayoutRate' => '20.00']]]],
            ]]),
            'api.impact.com/Mediapartners/IRabc1234567/Campaigns*' => Http::response(['@numpages' => '1', '@nextpageuri' => '', 'Campaigns' => [
                ['CampaignId' => '1001', 'CampaignName' => 'Acme CRM', 'AdvertiserName' => 'Acme Inc', 'CampaignUrl' => 'https://acme.example', 'ContractStatus' => 'Active', 'TrackingLink' => 'https://acme.sjv.io/c/111/222/1001', 'AllowsDeeplinking' => 'true'],
            ]]),
            'api.impact.com/Mediapartners/IRabc1234567/Actions*' => Http::response(['@numpages' => '1', '@nextpageuri' => '', 'Actions' => [
                ['Id' => 'A1', 'CampaignId' => '1001', 'CampaignName' => 'Acme CRM', 'State' => 'APPROVED', 'Payout' => '25.50', 'Currency' => 'USD', 'EventDate' => '2026-09-05T10:00:00-07:00', 'ClearedDate' => '', 'SubId1' => 'abc1234'],
                ['Id' => 'A2', 'CampaignId' => '1001', 'State' => 'REVERSED', 'Payout' => '10.00', 'Currency' => 'USD', 'EventDate' => '2026-09-06T10:00:00-07:00'],
                ['Id' => 'A3', 'CampaignId' => '1001', 'State' => 'APPROVED', 'Payout' => '5.00', 'Currency' => 'EUR', 'EventDate' => '2026-09-07T10:00:00Z', 'ClearedDate' => '2026-09-30T00:00:00Z'],
            ]]),
        ]);

        $adapter = new ImpactAdapter;
        $verified = $adapter->verify(['account_sid' => 'IRabc1234567', 'auth_token' => 'tok']);
        $this->assertSame('Ada Media', $verified['account_name']);

        $connection = $this->connection('impact', $verified['credentials'], 'IRabc1234567');
        $programs = $adapter->programs($connection);

        $this->assertSame('1001', $programs[0]['external_id']);
        $this->assertSame('joined', $programs[0]['status']);
        $this->assertSame('20% per sale', $programs[0]['commission']);
        $this->assertTrue($programs[0]['deep_linking']);

        $earnings = collect($adapter->earnings($connection, ...$this->range()))->keyBy('external_ref');
        $this->assertSame(2550, $earnings['A1']['amount_cents']);
        $this->assertSame('approved', $earnings['A1']['status']);
        $this->assertSame('abc1234', $earnings['A1']['sub_id']);
        $this->assertSame('2026-09-05', $earnings['A1']['converted_at']);
        $this->assertSame('declined', $earnings['A2']['status']);
        $this->assertSame('paid', $earnings['A3']['status']);
        $this->assertSame('EUR', $earnings['A3']['currency']);

        Http::assertSent(fn (Request $request) => str_contains($request->url(), '/Actions') && $request->hasHeader('Authorization', 'Basic '.base64_encode('IRabc1234567:tok')));
    }

    public function test_impact_makes_deep_links_and_uses_subid1(): void
    {
        Http::fake(['api.impact.com/*/TrackingLinks*' => Http::response(['TrackingURL' => 'https://acme.sjv.io/c/111/222/1001?u=https%3A%2F%2Facme.example%2Fpricing'])]);

        $connection = $this->connection('impact', ['account_sid' => 'IRabc1234567', 'auth_token' => 'tok']);
        $program = NetworkProgram::create(['network_connection_id' => $connection->id, 'external_id' => '1001', 'name' => 'Acme', 'tracking_url' => 'https://acme.sjv.io/c/111/222/1001', 'deep_linking' => true]);

        $link = (new ImpactAdapter)->trackingLink($connection, $program, 'https://acme.example/pricing');

        $this->assertStringStartsWith('https://acme.sjv.io/c/111/222/1001', $link);
        Http::assertSent(fn (Request $request) => str_contains($request->url(), 'Programs/1001/TrackingLinks') && str_contains($request->url(), 'DeepLink=https%3A%2F%2Facme.example%2Fpricing'));

        $this->assertSame('https://acme.sjv.io/c/111/222/1001?subId1=abc1234', (new ImpactAdapter)->withSubId('https://acme.sjv.io/c/111/222/1001', 'abc1234'));
        $this->assertTrue((new ImpactAdapter)->recognises('https://goto.brand.com/c/111/222/333'));
    }

    public function test_impact_refused_keys_are_reported_as_rejected(): void
    {
        Http::fake(['api.impact.com/*' => Http::response('', 401)]);

        try {
            (new ImpactAdapter)->verify(['account_sid' => 'IRabc1234567', 'auth_token' => 'bad']);
            $this->fail('Expected rejection');
        } catch (NetworkException $e) {
            $this->assertTrue($e->credentialsRejected);
            $this->assertStringContainsString('didn\'t accept these keys', $e->getMessage());
        }
    }

    // --- PartnerStack --------------------------------------------------------

    public function test_partnerstack_lists_partnerships_marketplace_and_rewards_with_cursor_pages(): void
    {
        Http::fake([
            'api.partnerstack.com/api/v2/partnerships*' => Http::response(['data' => ['has_more' => false, 'items' => [
                ['key' => 'part_1', 'status' => 'active', 'company' => ['key' => 'comp_a', 'name' => 'Notion'], 'link' => ['url' => 'https://notion.grsm.io/ada', 'destination' => 'https://notion.so'], 'offers' => [['base_rate' => '50', 'currency' => 'USD', 'description' => '50% for 12 months', 'type' => 'percent']], 'has_sub_id' => true],
                ['key' => 'part_2', 'status' => 'application-denied', 'company' => ['key' => 'comp_x', 'name' => 'Denied']],
            ]], 'status' => 200]),
            'api.partnerstack.com/api/v2/marketplace/programs*' => Http::response(['data' => ['has_more' => false, 'items' => [
                ['key' => 'comp_b', 'name' => 'Monday', 'website' => 'https://monday.com', 'category' => ['Productivity'], 'offers' => [['base_rate' => '100', 'currency' => 'USD', 'description' => '100% first year', 'type_' => 'percent']]],
                ['key' => 'comp_a', 'name' => 'Notion (already joined)'],
            ]]]),
            'api.partnerstack.com/api/v2/rewards*' => Http::sequence()
                ->push(['data' => ['has_more' => true, 'items' => [
                    ['key' => 'rew_1', 'amount' => 4900, 'currency' => 'USD', 'reward_status' => 'approved', 'payment_status' => 'pending', 'created_at' => 1788566400000, 'company' => ['key' => 'comp_a', 'name' => 'Notion'], 'customer' => ['sub_ids' => ['abc1234']]],
                ]]])
                ->push(['data' => ['has_more' => false, 'items' => [
                    ['key' => 'rew_2', 'amount' => 1000, 'currency' => 'USD', 'reward_status' => 'declined', 'created_at' => 1788652800000],
                    ['key' => 'rew_3', 'amount' => 2000, 'currency' => 'USD', 'reward_status' => 'approved', 'payment_status' => 'withdrawn', 'created_at' => 1788652800000],
                ]]]),
        ]);

        $adapter = new PartnerStackAdapter;
        $connection = $this->connection('partnerstack', ['api_key' => 'ps_live_key_123']);

        $programs = collect($adapter->programs($connection))->keyBy('external_id');
        $this->assertSame(['comp_a', 'comp_b'], $programs->keys()->all());
        $this->assertSame('joined', $programs['comp_a']['status']);
        $this->assertSame('https://notion.grsm.io/ada', $programs['comp_a']['tracking_url']);
        $this->assertSame('50% for 12 months', $programs['comp_a']['commission']);
        $this->assertSame('available', $programs['comp_b']['status']);

        $earnings = collect($adapter->earnings($connection, ...$this->range()))->keyBy('external_ref');
        $this->assertSame(4900, $earnings['rew_1']['amount_cents']);
        $this->assertSame('abc1234', $earnings['rew_1']['sub_id']);
        $this->assertSame('comp_a', $earnings['rew_1']['program_external_id']);
        $this->assertSame('declined', $earnings['rew_2']['status']);
        $this->assertSame('paid', $earnings['rew_3']['status']);

        Http::assertSent(fn (Request $request) => str_contains($request->url(), 'rewards') && str_contains($request->url(), 'starting_after=rew_1'));
        $this->assertSame('https://notion.grsm.io/ada?sid=abc1234', $adapter->withSubId('https://notion.grsm.io/ada', 'abc1234'));
    }

    // --- Awin ---------------------------------------------------------------

    public function test_awin_picks_the_publisher_account_and_reads_programmes_and_transactions(): void
    {
        Http::fake([
            'api.awin.com/accounts*' => Http::response(['userId' => 1, 'accounts' => [
                ['accountId' => 45628, 'accountName' => 'Ada Reviews', 'accountType' => 'publisher', 'userRole' => 'userAdmin'],
            ]]),
            'api.awin.com/publishers/45628/programmes?relationship=joined' => Http::response([
                ['id' => 7001, 'name' => 'Shoe Shop', 'displayUrl' => 'https://shoes.example', 'clickThroughUrl' => 'https://www.awin1.com/awclick.php?mid=7001&id=45628', 'logoUrl' => 'https://ui.awin.com/logo.png', 'currencyCode' => 'GBP', 'primarySector' => 'Fashion'],
            ]),
            'api.awin.com/publishers/45628/programmes?relationship=pending' => Http::response([]),
            'api.awin.com/publishers/45628/programmes?relationship=notjoined' => Http::response([
                ['id' => 7002, 'name' => 'Bag Shop', 'displayUrl' => 'bags.example', 'currencyCode' => 'EUR'],
            ]),
            'api.awin.com/publishers/45628/transactions/*' => Http::response([
                ['id' => 900, 'advertiserId' => 7001, 'commissionStatus' => 'approved', 'commissionAmount' => ['amount' => 12.4, 'currency' => 'GBP'], 'clickRefs' => ['clickRef' => 'abc1234'], 'transactionDate' => '2026-09-10T12:00:00', 'paidToPublisher' => false],
                ['id' => 901, 'advertiserId' => 7001, 'commissionStatus' => 'deleted', 'commissionAmount' => ['amount' => 3, 'currency' => 'GBP'], 'transactionDate' => '2026-09-11T12:00:00'],
            ]),
            'api.awin.com/publishers/45628/linkbuilder/generate' => Http::response(['url' => 'https://www.awin1.com/cread.php?awinmid=7001&awinaffid=45628&ued=https%3A%2F%2Fshoes.example%2Fboots']),
        ]);

        $adapter = new AwinAdapter;
        $verified = $adapter->verify(['api_token' => 'awin-token-123']);
        $this->assertSame('45628', $verified['account_id']);

        $connection = $this->connection('awin', $verified['credentials'], '45628');
        $programs = collect($adapter->programs($connection))->keyBy('external_id');
        $this->assertSame('joined', $programs['7001']['status']);
        $this->assertSame('GBP', $programs['7001']['currency']);
        $this->assertSame('available', $programs['7002']['status']);
        $this->assertSame('https://bags.example', $programs['7002']['homepage_url']);
        $this->assertNull($programs['7002']['tracking_url']);

        $earnings = collect($adapter->earnings($connection, ...$this->range()))->keyBy('external_ref');
        $this->assertSame(1240, $earnings['900']['amount_cents']);
        $this->assertSame('GBP', $earnings['900']['currency']);
        $this->assertSame('abc1234', $earnings['900']['sub_id']);
        $this->assertSame('declined', $earnings['901']['status']);

        $program = NetworkProgram::create(['network_connection_id' => $connection->id, 'external_id' => '7001', 'name' => 'Shoe Shop', 'deep_linking' => true]);
        $link = $adapter->trackingLink($connection, $program, 'https://shoes.example/boots');
        $this->assertStringContainsString('awinmid=7001', $link);
        Http::assertSent(fn (Request $request) => str_contains($request->url(), 'linkbuilder/generate') && $request['advertiserId'] === 7001 && $request['destinationUrl'] === 'https://shoes.example/boots');

        // clickref goes ahead of the destination.
        $this->assertSame(
            'https://www.awin1.com/cread.php?awinmid=7001&awinaffid=45628&clickref=abc1234&ued=https%3A%2F%2Fshoes.example%2Fboots',
            $adapter->withSubId('https://www.awin1.com/cread.php?awinmid=7001&awinaffid=45628&ued=https%3A%2F%2Fshoes.example%2Fboots', 'abc1234'),
        );
    }

    public function test_awin_says_so_when_a_token_has_no_publisher_account(): void
    {
        Http::fake(['api.awin.com/accounts*' => Http::response(['accounts' => [['accountId' => 1, 'accountType' => 'advertiser']]])]);

        $this->expectException(NetworkException::class);
        $this->expectExceptionMessage('isn\'t linked to an Awin publisher account');

        (new AwinAdapter)->verify(['api_token' => 'awin-token-123']);
    }

    // --- CJ -----------------------------------------------------------------

    public function test_cj_reads_advertisers_and_adds_up_corrections_per_order(): void
    {
        $joined = '<?xml version="1.0"?><cj-api><advertisers total-matched="1" records-returned="1" page-number="1"><advertiser><advertiser-id>5001</advertiser-id><account-status>Active</account-status><advertiser-name>Travel Co</advertiser-name><program-url>https://travel.example</program-url><relationship-status>joined</relationship-status><primary-category><parent>Travel</parent><child>Hotels</child></primary-category><actions><action><name>Sale</name><type>advanced sale</type><commission><default>4.00%</default></commission></action></actions></advertiser></advertisers></cj-api>';
        $notJoined = '<?xml version="1.0"?><cj-api><advertisers total-matched="1" records-returned="1" page-number="1"><advertiser><advertiser-id>5002</advertiser-id><account-status>Active</account-status><advertiser-name>Gadget Co</advertiser-name><program-url>https://gadgets.example</program-url></advertiser></advertisers></cj-api>';

        Http::fake([
            'advertiser-lookup.api.cj.com/*advertiser-ids=joined*' => Http::response($joined),
            'advertiser-lookup.api.cj.com/*advertiser-ids=notjoined*' => Http::response($notJoined),
            'commissions.api.cj.com/query' => Http::response(['data' => ['publisherCommissions' => ['count' => 3, 'payloadComplete' => true, 'records' => [
                ['commissionId' => 'c1', 'orderId' => 'o1', 'original' => true, 'actionStatus' => 'locked', 'validationStatus' => 'ACCEPTED', 'advertiserId' => '5001', 'advertiserName' => 'Travel Co', 'eventDate' => '2026-09-03T10:00:00Z', 'postingDate' => '2026-09-03T11:00:00Z', 'pubCommissionAmountUsd' => '20.00', 'shopperId' => 'abc1234'],
                ['commissionId' => 'c2', 'orderId' => 'o1', 'original' => false, 'actionStatus' => 'locked', 'advertiserId' => '5001', 'eventDate' => '2026-09-03T10:00:00Z', 'pubCommissionAmountUsd' => '-5.00'],
                ['commissionId' => 'c3', 'orderId' => 'o2', 'original' => true, 'actionStatus' => 'new', 'validationStatus' => 'DECLINED', 'advertiserId' => '5001', 'eventDate' => '2026-09-04T10:00:00Z', 'pubCommissionAmountUsd' => '9.00'],
            ]]]]),
            'link-search.api.cj.com/*' => Http::response('<?xml version="1.0"?><cj-api><links total-matched="1"><link><link-id>11470088</link-id><clickUrl>https://www.tkqlhce.com/click-3074780-11470088</clickUrl></link></links></cj-api>'),
        ]);

        $adapter = new CjAdapter;
        $verified = $adapter->verify(['access_token' => 'cj-token-abcdef', 'publisher_cid' => '999', 'website_id' => '3074780']);
        $connection = $this->connection('cj', $verified['credentials'], '999');

        $programs = collect($adapter->programs($connection))->keyBy('external_id');
        $this->assertSame('joined', $programs['5001']['status']);
        $this->assertSame('4.00% per advanced sale', $programs['5001']['commission']);
        $this->assertSame('Travel / Hotels', $programs['5001']['category']);
        $this->assertSame('available', $programs['5002']['status']);

        $earnings = collect($adapter->earnings($connection, ...$this->range()))->keyBy('external_ref');
        $this->assertSame(1500, $earnings['order:5001:o1']['amount_cents']);
        $this->assertSame('approved', $earnings['order:5001:o1']['status']);
        $this->assertSame('abc1234', $earnings['order:5001:o1']['sub_id']);
        $this->assertSame('declined', $earnings['order:5001:o2']['status']);

        Http::assertSent(fn (Request $request) => $request->url() === CjAdapter::COMMISSIONS && str_contains($request->body(), 'forPublishers: ["999"]'));

        $program = NetworkProgram::create(['network_connection_id' => $connection->id, 'external_id' => '5001', 'name' => 'Travel Co', 'deep_linking' => true]);
        $this->assertSame('https://www.tkqlhce.com/click-3074780-11470088?url=https%3A%2F%2Ftravel.example%2Fdeals', $adapter->trackingLink($connection, $program, 'https://travel.example/deals'));
        $this->assertSame('https://www.tkqlhce.com/click-3074780-11470088?sid=abc1234', $adapter->withSubId('https://www.tkqlhce.com/click-3074780-11470088', 'abc1234'));
    }

    public function test_cj_ids_must_be_numbers(): void
    {
        $this->expectException(NetworkException::class);

        (new CjAdapter)->verify(['access_token' => 'cj-token-abcdef', 'publisher_cid' => 'abc', 'website_id' => '1']);
    }

    // --- ClickBank ------------------------------------------------------------

    public function test_clickbank_pages_orders_and_voids_refunded_sales(): void
    {
        Http::fake([
            'api.clickbank.com/rest/1.3/quickstats/accounts' => Http::response(['accountData' => [['nickName' => 'adaaff']]]),
            'api.clickbank.com/rest/1.3/orders2/list*' => Http::sequence()
                ->push(['orderData' => [
                    ['receipt' => 'R1', 'transactionTime' => '2026-09-02T08:00:00-07:00', 'transactionType' => 'SALE', 'vendor' => 'mitolyn', 'currency' => 'USD', 'trackingId' => 'abc1234', 'lineItemData' => [['productTitle' => 'Mitolyn', 'accountAmount' => '45.10'], ['productTitle' => 'Upsell', 'accountAmount' => '10.00']]],
                    ['receipt' => 'R2', 'transactionTime' => '2026-09-03T08:00:00-07:00', 'transactionType' => 'SALE', 'vendor' => 'mitolyn', 'currency' => 'USD', 'lineItemData' => ['productTitle' => 'Mitolyn', 'accountAmount' => '30.00']],
                ]], 206)
                ->push(['orderData' => ['receipt' => 'R2', 'transactionTime' => '2026-09-05T08:00:00-07:00', 'transactionType' => 'RFND', 'vendor' => 'mitolyn', 'lineItemData' => ['accountAmount' => '-30.00']]], 200),
        ]);

        $adapter = new ClickBankAdapter;
        $verified = $adapter->verify(['api_key' => 'API-ABCDEF123456']);
        $this->assertSame('adaaff', $verified['account_id']);

        $connection = $this->connection('clickbank', $verified['credentials'], 'adaaff');
        $earnings = collect($adapter->earnings($connection, CarbonImmutable::parse('2026-09-01'), CarbonImmutable::parse('2026-09-20')))->keyBy('external_ref');

        $this->assertSame(5510, $earnings['R1']['amount_cents']);
        $this->assertSame('approved', $earnings['R1']['status']);
        $this->assertSame('abc1234', $earnings['R1']['sub_id']);
        $this->assertSame('declined', $earnings['R2']['status']);

        Http::assertSent(fn (Request $request) => str_contains($request->url(), 'orders2/list') && $request->hasHeader('Page', '2') && $request->hasHeader('Authorization', 'API-ABCDEF123456'));

        $program = $adapter->programByHand($connection, 'Mitolyn');
        $this->assertSame('https://hop.clickbank.net/?affiliate=adaaff&vendor=mitolyn', $program['tracking_url']);
        $this->assertSame('https://hop.clickbank.net/?affiliate=adaaff&vendor=mitolyn&tid=abc1234', $adapter->withSubId($program['tracking_url'], 'abc1234'));
    }

    public function test_clickbank_keys_must_start_with_api(): void
    {
        $this->expectException(NetworkException::class);

        (new ClickBankAdapter)->verify(['api_key' => 'DEV-123:CLERK-456']);
    }

    // --- Digistore24 ------------------------------------------------------------

    public function test_digistore24_adds_commissions_per_purchase_with_sub_ids(): void
    {
        Http::fake([
            'www.digistore24.com/api/call/getUserInfo' => Http::response(['result' => 'success', 'data' => ['user_id' => 5, 'user_name' => 'AdaAff', 'granted_roles' => 'user,affiliate']]),
            'www.digistore24.com/api/call/listCommissions' => Http::response(['result' => 'success', 'data' => ['page_count' => 1, 'items' => [
                ['id' => 1, 'created_at' => '2026-09-08 10:00:00', 'amount' => '27.50', 'currency' => 'EUR', 'purchase_id' => 'P100', 'schedule_payout_at' => '2026-09-20 00:00:00'],
                ['id' => 2, 'created_at' => '2026-09-09 10:00:00', 'amount' => '19.00', 'currency' => 'EUR', 'purchase_id' => 'P101', 'schedule_payout_at' => '2099-01-01 00:00:00'],
                ['id' => 3, 'created_at' => '2026-09-12 10:00:00', 'amount' => '-19.00', 'currency' => 'EUR', 'purchase_id' => 'P101'],
            ]]]),
            'www.digistore24.com/api/call/listPurchases' => Http::response(['result' => 'success', 'data' => ['page_count' => 1, 'purchase_list' => [
                ['id' => 'P100', 'product_id' => '123456', 'product_name' => 'Keto Plan', 'sub_ids' => ['sid1' => 'abc1234']],
            ]]]),
        ]);

        $adapter = new Digistore24Adapter;
        $verified = $adapter->verify(['api_key' => 'ds24-key-1234567']);
        $this->assertSame('AdaAff', $verified['account_id']);

        $connection = $this->connection('digistore24', $verified['credentials'], 'AdaAff');
        $earnings = collect($adapter->earnings($connection, ...$this->range()))->keyBy('external_ref');

        $this->assertSame(2750, $earnings['purchase:P100']['amount_cents']);
        $this->assertSame('EUR', $earnings['purchase:P100']['currency']);
        $this->assertSame('paid', $earnings['purchase:P100']['status']);
        $this->assertSame('abc1234', $earnings['purchase:P100']['sub_id']);
        $this->assertSame('123456', $earnings['purchase:P100']['program_external_id']);
        $this->assertSame('declined', $earnings['purchase:P101']['status']);

        Http::assertSent(fn (Request $request) => $request->hasHeader('X-DS-API-KEY', 'ds24-key-1234567'));

        $this->assertSame(
            'https://www.checkout-ds24.com/redir/123456/AdaAff/?sid1=abc1234',
            $adapter->withSubId('https://www.checkout-ds24.com/redir/123456/AdaAff/', 'abc1234'),
        );
    }

    public function test_digistore24_errors_sent_with_http_200_are_still_errors(): void
    {
        Http::fake(['www.digistore24.com/*' => Http::response(['result' => 'error', 'code' => 2, 'message' => 'Invalid API key'])]);

        try {
            (new Digistore24Adapter)->verify(['api_key' => 'ds24-key-1234567']);
            $this->fail('Expected rejection');
        } catch (NetworkException $e) {
            $this->assertTrue($e->credentialsRejected);
        }
    }

    public function test_digistore24_approval_uses_the_developer_key(): void
    {
        SiteSetting::set(Digistore24Adapter::DEVELOPER_KEY_SETTING, Crypt::encryptString('dev-key-999'));
        Http::fake([
            'www.digistore24.com/api/call/requestApiKey' => Http::response(['result' => 'success', 'data' => ['request_url' => 'https://www.digistore24.com/api_key/approve/xyz', 'request_token' => 'tok-1']]),
            'www.digistore24.com/api/call/retrieveApiKey' => Http::response(['result' => 'success', 'data' => ['api_key' => 'new-user-key', 'request_status' => 'completed']]),
        ]);

        $adapter = new Digistore24Adapter;
        $approval = $adapter->requestApproval('https://app.example/return', 'https://app.example/cancel');

        $this->assertSame('tok-1', $approval['token']);
        $this->assertSame('new-user-key', $adapter->retrieveApprovedKey('tok-1'));
        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), 'requestApiKey') && $request->hasHeader('X-DS-API-KEY', 'dev-key-999') && $request['permissions'] === 'read-only');
    }

    // --- Registry ------------------------------------------------------------

    public function test_links_are_matched_to_the_network_that_made_them(): void
    {
        $cases = [
            'https://www.awin1.com/cread.php?awinmid=1&awinaffid=2' => AwinAdapter::class,
            'https://www.anrdoezrs.net/click-1-2' => CjAdapter::class,
            'https://hop.clickbank.net/?affiliate=a&vendor=b' => ClickBankAdapter::class,
            'https://www.checkout-ds24.com/redir/1/a/' => Digistore24Adapter::class,
            'https://notion.grsm.io/ada' => PartnerStackAdapter::class,
            'https://acme.sjv.io/c/1/2/3' => ImpactAdapter::class,
        ];

        foreach ($cases as $url => $class) {
            $this->assertInstanceOf($class, NetworkRegistry::forUrl($url), $url);
        }

        $this->assertNull(NetworkRegistry::forUrl('https://example.com/product?ref=1'));
    }
}
