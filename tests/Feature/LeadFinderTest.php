<?php

namespace Tests\Feature;

use App\Filament\Pages\LeadSourceSettings;
use App\Jobs\FindLeads;
use App\Jobs\RunOfferResearch;
use App\Models\CrmContact;
use App\Models\CrmList;
use App\Models\LeadRun;
use App\Models\LeadSourceSetting;
use App\Models\Offer;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\AI\AIProvider;
use App\Services\Leads\EmailChecker;
use App\Services\Leads\LeadFinder;
use App\Services\Leads\LeadFinderException;
use App\Services\Leads\WebsiteEmailFinder;
use App\Services\Modules\OfferResearchService;
use App\Support\OutboundUrlGuard;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * B2B lead finding: buyers found from Google Maps (+ website emails, Hunter)
 * and Apollo, checked, scored, tagged and filed into the product's CRM list,
 * inside the plan's monthly allowance or paid with credits.
 */
class LeadFinderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
        config(['services.google_places.api_key' => 'places-key']);
        OutboundUrlGuard::resolveUsing(fn (string $host): array => ['93.184.216.34']);
        EmailChecker::resolveMxUsing(fn (string $domain): bool => $domain !== 'dead-domain.test');

        $this->app->instance(AIProvider::class, new class implements AIProvider
        {
            public function generateText(string $systemPrompt, string $userPrompt, array $options = []): string
            {
                return '';
            }

            public function generateJson(string $systemPrompt, string $userPrompt, array $options = []): array
            {
                preg_match_all('/"index":(\d+)/', $userPrompt, $m);

                return ['scores' => array_map(fn ($i) => ['index' => (int) $i, 'score' => 85, 'reason' => 'Runs a clinic that needs bookings'], $m[1])];
            }

            public function generateImage(string $prompt, array $options = []): string
            {
                return '';
            }
        });
    }

    protected function tearDown(): void
    {
        OutboundUrlGuard::resolveUsing(null);
        EmailChecker::resolveMxUsing(null);

        parent::tearDown();
    }

    protected function customer(int $allowance = 100, int $credits = 0): User
    {
        $user = User::factory()->create(['credits_balance' => $credits]);
        $user->assignRole('user');
        $plan = Plan::factory()->create(['monthly_lead_allowance' => $allowance, 'contact_limit' => 0]);
        Subscription::factory()->create(['user_id' => $user->id, 'plan_id' => $plan->id]);

        return $user->fresh();
    }

    protected function offer(User $user): Offer
    {
        return Offer::create([
            'user_id' => $user->id,
            'product_name' => 'BookingPro',
            'product_url' => 'https://bookingpro.example',
            'affiliate_network' => 'PartnerStack',
            'status' => 'ready',
            'ideal_customer_summary' => 'Clinic owners who lose patients to missed bookings.',
            'research_data' => [
                'google_maps_keywords' => [['keyword' => 'dental clinic', 'why' => 'Needs bookings']],
                'suggested_locations' => ['Lagos, Nigeria'],
                'linkedin_keywords' => ['people' => ['Clinic Owner', 'Practice Manager'], 'companies' => ['dental']],
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $first  stubs matched before the website ones
     */
    protected function fakeMaps(array $first = []): void
    {
        Http::fake($first + [
            'places.googleapis.com/v1/places:searchText' => Http::response(['places' => [
                ['id' => 'place-1', 'displayName' => ['text' => 'Smile Dental'], 'formattedAddress' => '1 Allen Ave, Ikeja, Nigeria', 'primaryType' => 'dentist', 'googleMapsUri' => 'https://maps.google.com/?cid=1'],
                ['id' => 'place-2', 'displayName' => ['text' => 'Bright Teeth'], 'formattedAddress' => '9 Awolowo Rd, Lagos, Nigeria', 'primaryType' => 'dentist', 'googleMapsUri' => 'https://maps.google.com/?cid=2'],
                ['id' => 'place-3', 'displayName' => ['text' => 'No Contact Clinic'], 'formattedAddress' => 'Lagos, Nigeria', 'primaryType' => 'dentist'],
            ]]),
            'places.googleapis.com/v1/places/place-1' => Http::response(['nationalPhoneNumber' => '0801 000 0001', 'websiteUri' => 'https://smiledental.test']),
            'places.googleapis.com/v1/places/place-2' => Http::response(['nationalPhoneNumber' => '0801 000 0002', 'websiteUri' => 'https://brightteeth.test']),
            'places.googleapis.com/v1/places/place-3' => Http::response([]),
            'smiledental.test/*' => Http::response('<html><body>Book now: <a href="mailto:hello@smiledental.test">hello@smiledental.test</a> <img src="logo@2x.png"></body></html>'),
            'smiledental.test' => Http::response('<html><body>Book now: <a href="mailto:hello@smiledental.test">hello@smiledental.test</a></body></html>'),
            'brightteeth.test*' => Http::response('<html><body>Call us today.</body></html>'),
        ]);
    }

    public function test_a_run_finds_checks_scores_tags_and_files_leads_into_the_products_list(): void
    {
        $user = $this->customer(allowance: 100);
        $offer = $this->offer($user);
        $this->fakeMaps();

        app(LeadFinder::class)->queue($user, $offer, 25);

        $run = LeadRun::firstOrFail();
        $this->assertSame('completed', $run->status);
        $this->assertSame(2, $run->saved, 'The clinic with no email or phone is skipped.');
        $this->assertSame(1, $run->with_email);
        $this->assertSame(2, $run->free_leads);
        $this->assertSame(0, $run->credits_spent);
        $this->assertSame(['google_maps'], $run->sources);

        $smile = CrmContact::where('company', 'Smile Dental')->firstOrFail();
        $this->assertSame('hello@smiledental.test', $smile->email);
        $this->assertSame(85, $smile->lead_score);
        $this->assertSame(['AffilStack lead', 'BookingPro', 'Google Maps', 'High fit', 'dental clinic', 'Nigeria'], $smile->tags);
        $this->assertSame('place-1', $smile->raw_data['google_place_id']);
        $this->assertSame('website', $smile->raw_data['email_source']);
        $this->assertSame('deliverable', $smile->raw_data['email_status']);
        $this->assertSame('legitimate_interest_b2b', $smile->raw_data['lawful_basis']);
        $this->assertTrue(CrmList::forOffer($offer)->contacts()->whereKey($smile->id)->exists());

        $bright = CrmContact::where('company', 'Bright Teeth')->firstOrFail();
        $this->assertNull($bright->email);
        $this->assertSame('0801 000 0002', $bright->phone);
    }

    public function test_leads_already_in_the_crm_are_not_added_twice(): void
    {
        $user = $this->customer();
        $offer = $this->offer($user);
        CrmContact::create(['user_id' => $user->id, 'name' => 'Smile Dental', 'source' => 'google_maps', 'status' => 'new', 'raw_data' => ['google_place_id' => 'place-1']]);
        $this->fakeMaps();

        app(LeadFinder::class)->queue($user, $offer, 25);

        $this->assertSame(1, LeadRun::firstOrFail()->saved);
        $this->assertSame(1, CrmContact::where('company', 'Bright Teeth')->count());
        $this->assertSame(1, CrmContact::where('name', 'Smile Dental')->count());
    }

    public function test_leads_past_the_monthly_allowance_cost_credits_and_the_run_stops_when_they_run_out(): void
    {
        $user = $this->customer(allowance: 0, credits: 4);
        $offer = $this->offer($user);
        $this->fakeMaps();

        app(LeadFinder::class)->queue($user, $offer, 25);

        $run = LeadRun::firstOrFail();
        // 4 credits cover two leads at the full 2-credit price; the lead with a
        // checked email costs 2, the phone-only one 1 — 3 in total.
        $this->assertSame(2, $run->saved);
        $this->assertSame(3, $run->credits_spent);
        $this->assertSame(0, $run->free_leads);
        $this->assertSame(1, $user->fresh()->credits_balance);
    }

    public function test_a_run_cannot_start_without_allowance_or_credits_or_without_a_source(): void
    {
        $user = $this->customer(allowance: 0, credits: 0);
        $offer = $this->offer($user);

        try {
            app(LeadFinder::class)->queue($user, $offer, 25);
            $this->fail('Expected the run to be refused.');
        } catch (LeadFinderException $e) {
            $this->assertStringContainsString('credit', $e->getMessage());
        }

        config(['services.google_places.api_key' => null]);
        $this->expectException(LeadFinderException::class);
        app(LeadFinder::class)->queue($this->customer(), $offer, 25);
    }

    public function test_apollo_people_are_enriched_and_free_mail_addresses_are_dropped(): void
    {
        config(['services.google_places.api_key' => null]);
        LeadSourceSetting::current()->update(['apollo_enabled' => true, 'credentials' => ['apollo_api_key' => 'apollo-key']]);
        $user = $this->customer();
        $offer = $this->offer($user);

        Http::fake([
            'api.apollo.io/api/v1/mixed_people/api_search*' => Http::response(['people' => [
                ['id' => 'p1', 'first_name' => 'Ada', 'title' => 'Clinic Owner', 'organization' => ['name' => 'Ada Dental']],
                ['id' => 'p2', 'first_name' => 'Bola', 'title' => 'Practice Manager', 'organization' => ['name' => 'Bola Care']],
            ]]),
            'api.apollo.io/api/v1/people/match?id=p1*' => Http::response(['person' => ['first_name' => 'Ada', 'last_name' => 'Obi', 'title' => 'Clinic Owner', 'email' => 'ada@adadental.test', 'linkedin_url' => 'https://linkedin.com/in/ada', 'organization' => ['name' => 'Ada Dental', 'primary_domain' => 'adadental.test'], 'city' => 'Lagos', 'country' => 'Nigeria']]),
            'api.apollo.io/api/v1/people/match?id=p2*' => Http::response(['person' => ['first_name' => 'Bola', 'email' => 'bola@gmail.com', 'organization' => ['name' => 'Bola Care']]]),
        ]);

        app(LeadFinder::class)->queue($user, $offer, 10);

        $this->assertSame(1, LeadRun::firstOrFail()->saved);
        $ada = CrmContact::firstOrFail();
        $this->assertSame('Ada Obi', $ada->name);
        $this->assertSame('ada@adadental.test', $ada->email);
        $this->assertSame('apollo', $ada->source);
        $this->assertSame('https://adadental.test', $ada->website);
        $this->assertContains('Apollo', $ada->tags);

        Http::assertSent(fn ($request) => str_contains($request->url(), 'mixed_people/api_search')
            && str_contains(urldecode($request->url()), 'person_titles[]=Clinic Owner')
            && $request->hasHeader('x-api-key', 'apollo-key'));
    }

    public function test_hunter_fills_in_a_business_email_when_the_website_lists_none(): void
    {
        LeadSourceSetting::current()->update(['hunter_enabled' => true, 'credentials' => ['hunter_api_key' => 'hunter-key']]);
        $user = $this->customer();
        $offer = $this->offer($user);
        $this->fakeMaps([
            'api.hunter.io/v2/domain-search*' => Http::response(['data' => ['emails' => [['value' => 'dr.sola@brightteeth.test', 'first_name' => 'Sola', 'last_name' => 'Ade', 'position' => 'Owner', 'confidence' => 92]]]]),
            'api.hunter.io/v2/email-verifier*' => Http::response(['data' => ['status' => 'valid']]),
        ]);

        app(LeadFinder::class)->queue($user, $offer, 25);

        $bright = CrmContact::where('company', 'Bright Teeth')->firstOrFail();
        $this->assertSame('dr.sola@brightteeth.test', $bright->email);
        $this->assertSame('Sola Ade', $bright->name);
        $this->assertSame('hunter', $bright->raw_data['email_source']);
        $this->assertSame('verified', $bright->raw_data['email_status']);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'api.hunter.io') && $request->hasHeader('X-API-KEY', 'hunter-key') && ! str_contains($request->url(), 'hunter-key'));
    }

    public function test_finishing_research_with_find_leads_ticked_starts_a_lead_search(): void
    {
        $user = $this->customer();
        $offer = $this->offer($user);
        $offer->update(['find_leads' => true]);
        Bus::fake([FindLeads::class]);

        $job = new RunOfferResearch($offer);
        $job->offer->status = 'ready';
        // Research itself is covered elsewhere; only the follow-on lead search matters here.
        $this->partialMock(OfferResearchService::class, fn ($mock) => $mock->shouldReceive('research')->once());
        $job->handle(app(OfferResearchService::class));

        Bus::assertDispatched(FindLeads::class, fn (FindLeads $job) => $job->run->trigger === 'research' && $job->run->offer_id === $offer->id);
    }

    public function test_the_research_form_and_product_page_offer_lead_finding(): void
    {
        $user = $this->customer(allowance: 100);
        $offer = $this->offer($user);
        Bus::fake([FindLeads::class]);

        $this->actingAs($user)->get(route('offers.create'))->assertOk()->assertSee('Also find B2B buyers (leads)')->assertSee('100 free leads left this month');

        $this->actingAs($user)->get(route('offers.show', $offer))->assertOk()->assertSee('Find B2B buyers for this product');

        $this->actingAs($user)->post(route('offers.leads.store', $offer), ['target' => 50])->assertRedirect()->assertSessionHas('success');
        $this->assertDatabaseHas('lead_runs', ['offer_id' => $offer->id, 'target' => 50, 'trigger' => 'manual']);
        Bus::assertDispatched(FindLeads::class);

        $this->actingAs($user)->post(route('offers.leads.store', $offer), ['target' => 50])->assertSessionHas('error');
        $this->actingAs($user)->post(route('offers.leads.store', $offer), ['target' => 7])->assertSessionHasErrors('target');

        $stranger = $this->customer();
        $this->actingAs($stranger)->post(route('offers.leads.store', $offer), ['target' => 10])->assertNotFound();
    }

    public function test_the_csv_export_includes_tags_and_scores(): void
    {
        $user = $this->customer();
        CrmContact::create(['user_id' => $user->id, 'name' => 'Ada', 'source' => 'apollo', 'status' => 'new', 'tags' => ['AffilStack lead', 'High fit'], 'lead_score' => 91, 'raw_data' => ['score_reason' => 'Owner of a clinic']]);

        $csv = $this->actingAs($user)->get(route('crm.export'))->assertOk()->getContent();

        $this->assertStringContainsString('Tags,Lead score,Score reason', $csv);
        $this->assertStringContainsString('"AffilStack lead; High fit","91","Owner of a clinic"', $csv);
    }

    public function test_the_website_email_finder_never_fetches_a_private_address(): void
    {
        OutboundUrlGuard::resolveUsing(fn (string $host): array => ['127.0.0.1']);
        Http::fake();

        $this->assertSame([], app(WebsiteEmailFinder::class)->find('http://internal.example'));
        Http::assertNothingSent();
    }

    public function test_admins_save_provider_keys_masked_and_test_them(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('super-admin');
        Http::fake(['api.hunter.io/v2/account' => Http::response(['data' => ['requests' => ['searches' => ['available' => 48]]]])]);

        Livewire::actingAs($admin)->test(LeadSourceSettings::class)
            ->set('data.hunter_enabled', true)
            ->set('data.hunter_api_key', 'hunter-secret')
            ->call('testHunter')
            ->assertDontSee('hunter-secret');

        $settings = LeadSourceSetting::current();
        $this->assertTrue($settings->hunterReady());
        $this->assertSame('success', $settings->hunter_test_status);
        $this->assertStringContainsString('48', $settings->hunter_test_message);
    }
}
