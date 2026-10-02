<?php

namespace Tests\Feature\Api;

use App\Events\CrmContactAddedToList;
use App\Models\ApiToken;
use App\Models\CrmContact;
use App\Models\CrmList;
use App\Models\Offer;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * Chrome extension v1.2 — the Google Maps → CRM endpoints
 * (GET /api/crm/lists, POST /api/crm/contacts, POST /api/crm/contacts/lookup).
 */
class ExtensionProspectingApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Offer $offer;

    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);

        $this->user = $this->subscribedUser(['channels' => ['research', 'linkedin', 'google_maps'], 'contact_limit' => 0]);
        $this->offer = $this->offerFor($this->user, 'Acme POS');
        $this->token = ApiToken::generate($this->user, 'Laptop')['plainText'];
    }

    /**
     * @param  array<string, mixed>  $planAttributes
     */
    protected function subscribedUser(array $planAttributes = []): User
    {
        $plan = Plan::factory()->create($planAttributes);
        $user = User::factory()->create();
        $user->assignRole('user');
        Subscription::factory()->create(['user_id' => $user->id, 'plan_id' => $plan->id]);

        return $user;
    }

    protected function offerFor(User $user, string $name): Offer
    {
        return Offer::create([
            'user_id' => $user->id, 'product_name' => $name, 'product_url' => 'https://acme.example',
            'affiliate_network' => 'Impact', 'status' => 'ready',
        ]);
    }

    /**
     * @return array<string, string>
     */
    protected function headers(?string $token = null): array
    {
        return ['Authorization' => 'Bearer '.($token ?? $this->token)];
    }

    /**
     * @return array<string, mixed>
     */
    protected function business(array $overrides = []): array
    {
        return [
            'name' => 'Mama Put Kitchen',
            'category' => 'Restaurant',
            'address' => '12 Allen Avenue, Ikeja, Lagos',
            'phone' => '0803 123 4567',
            'website' => 'https://mamaput.ng/menu',
            'rating' => 4.6,
            'review_count' => 214,
            'maps_url' => 'https://www.google.com/maps/place/Mama+Put/data=!4m6!3m5!1s0x103b8f:0x2c5a!19sChIJabc123',
            'place_id' => 'ChIJabc123',
            'cid' => '11348',
            'ftid' => '0x103b8f:0x2c5a',
            ...$overrides,
        ];
    }

    public function test_every_endpoint_requires_a_valid_bearer_token(): void
    {
        $this->getJson('/api/crm/lists')->assertUnauthorized();
        $this->postJson('/api/crm/contacts', ['offer_id' => $this->offer->id, ...$this->business()])->assertUnauthorized();
        $this->postJson('/api/crm/contacts/lookup', ['contacts' => [$this->business()]], $this->headers('aff_wrong'))->assertUnauthorized();
    }

    public function test_lists_returns_the_users_lists_and_products_with_their_default_list(): void
    {
        $productList = CrmList::forOffer($this->offer);
        $customList = CrmList::create(['user_id' => $this->user->id, 'name' => 'Lagos restaurants']);
        $otherUser = User::factory()->create();
        CrmList::create(['user_id' => $otherUser->id, 'name' => 'Not mine']);
        $unlistedOffer = $this->offerFor($this->user, 'Second product');

        $response = $this->getJson('/api/crm/lists', $this->headers())->assertOk();

        $this->assertEqualsCanonicalizing([$productList->id, $customList->id], array_column($response->json('lists'), 'id'));
        $this->assertSame(route('crm.lists.show', $productList), collect($response->json('lists'))->firstWhere('id', $productList->id)['url']);
        $offers = collect($response->json('offers'))->keyBy('id');
        $this->assertSame($productList->id, $offers[$this->offer->id]['list_id']);
        $this->assertNull($offers[$unlistedOffer->id]['list_id']);
        $this->assertTrue($response->json('can_use_google_maps'));
    }

    public function test_a_single_business_is_added_and_filed_into_the_products_list(): void
    {
        Event::fake([CrmContactAddedToList::class]);

        $response = $this->postJson('/api/crm/contacts', ['offer_id' => $this->offer->id, ...$this->business()], $this->headers());

        $response->assertCreated()
            ->assertJsonPath('created', 1)
            ->assertJsonPath('results.0.status', 'created')
            ->assertJsonPath('results.0.added_to_list', true)
            ->assertJsonPath('list.name', 'Acme POS prospects');

        $contact = CrmContact::firstOrFail();
        $list = CrmList::where('offer_id', $this->offer->id)->firstOrFail();
        $this->assertSame('google_maps', $contact->source);
        $this->assertSame('Mama Put Kitchen', $contact->company);
        $this->assertSame('ChIJabc123', $contact->raw_data['google_place_id']);
        $this->assertSame('11348', $contact->raw_data['google_cid']);
        $this->assertSame('Restaurant', $contact->raw_data['type']);
        $this->assertSame('chrome_extension', $contact->raw_data['captured_via']);
        $this->assertTrue($list->contacts()->whereKey($contact->id)->exists());
        $this->assertSame(route('crm.lists.show', $list), $response->json('list.url'));
        $this->assertSame(route('crm.campaigns.create', ['list' => $list->id]), $response->json('list.campaign_url'));

        Event::assertDispatched(CrmContactAddedToList::class, fn (CrmContactAddedToList $event) => $event->list->is($list) && $event->contact->is($contact));
    }

    public function test_a_batch_from_a_results_list_reports_per_item_and_skips_invalid_items(): void
    {
        $response = $this->postJson('/api/crm/contacts', [
            'offer_id' => $this->offer->id,
            'contacts' => [
                $this->business(),
                $this->business(['name' => 'Bukka Hut', 'place_id' => 'ChIJxyz', 'cid' => null, 'ftid' => null, 'phone' => '+234 1 555 0000', 'website' => 'bukkahut.com']),
                ['category' => 'No name given'],
            ],
        ], $this->headers());

        $response->assertCreated()
            ->assertJsonPath('created', 2)
            ->assertJsonPath('failed', 1)
            ->assertJsonPath('results.2.status', 'failed');

        $this->assertArrayHasKey('name', $response->json('results.2.errors'));
        $this->assertSame('https://bukkahut.com', CrmContact::where('name', 'Bukka Hut')->value('website'));
        $this->assertSame(2, CrmList::forOffer($this->offer)->contacts()->count());
    }

    public function test_duplicates_are_detected_by_place_id_phone_and_website_and_still_filed_into_the_new_list(): void
    {
        Event::fake([CrmContactAddedToList::class]);

        // Imported earlier by Lead Finder — only a place id in raw_data.
        $leadFinderContact = CrmContact::create([
            'user_id' => $this->user->id, 'name' => 'Mama Put Kitchen', 'source' => 'google_maps', 'status' => 'new',
            'raw_data' => ['google_place_id' => 'ChIJabc123'],
        ]);
        $phoneContact = CrmContact::create(['user_id' => $this->user->id, 'name' => 'Suya Spot', 'phone' => '+234 803 999 1111', 'source' => 'manual', 'status' => 'new']);
        $websiteContact = CrmContact::create(['user_id' => $this->user->id, 'name' => 'Chicken Republic', 'website' => 'http://www.chickenrepublic.com', 'source' => 'import', 'status' => 'new']);
        $customList = CrmList::create(['user_id' => $this->user->id, 'name' => 'Ikeja food']);

        $response = $this->postJson('/api/crm/contacts', [
            'list_id' => $customList->id,
            'contacts' => [
                $this->business(['phone' => null, 'website' => null]),
                $this->business(['name' => 'Suya Spot Ikeja', 'place_id' => null, 'cid' => null, 'ftid' => null, 'phone' => '0803 999 1111', 'website' => null]),
                $this->business(['name' => 'Chicken Republic', 'place_id' => null, 'cid' => null, 'ftid' => null, 'phone' => null, 'website' => 'https://chickenrepublic.com/locations/ikeja']),
            ],
        ], $this->headers());

        $response->assertOk()
            ->assertJsonPath('created', 0)
            ->assertJsonPath('duplicates', 3)
            ->assertJsonPath('added_to_list', 3)
            ->assertJsonPath('results.0.matched_by', 'place_id')
            ->assertJsonPath('results.1.matched_by', 'phone')
            ->assertJsonPath('results.2.matched_by', 'website');

        $this->assertSame(3, CrmContact::count());
        $this->assertEqualsCanonicalizing(
            [$leadFinderContact->id, $phoneContact->id, $websiteContact->id],
            $customList->contacts()->pluck('crm_contacts.id')->all(),
        );
        Event::assertDispatchedTimes(CrmContactAddedToList::class, 3);

        // Adding them again is a no-op: nothing new, no autopilot event.
        Event::fake([CrmContactAddedToList::class]);
        $this->postJson('/api/crm/contacts', ['list_id' => $customList->id, ...$this->business()], $this->headers())
            ->assertOk()
            ->assertJsonPath('results.0.status', 'duplicate')
            ->assertJsonPath('results.0.added_to_list', false);
        Event::assertNotDispatched(CrmContactAddedToList::class);
    }

    public function test_the_same_business_twice_in_one_batch_is_created_once(): void
    {
        $this->postJson('/api/crm/contacts', [
            'offer_id' => $this->offer->id,
            'contacts' => [$this->business(), $this->business()],
        ], $this->headers())
            ->assertCreated()
            ->assertJsonPath('created', 1)
            ->assertJsonPath('duplicates', 1);

        $this->assertSame(1, CrmContact::count());
    }

    public function test_another_users_contacts_never_count_as_duplicates(): void
    {
        $other = User::factory()->create();
        CrmContact::create(['user_id' => $other->id, 'name' => 'Mama Put Kitchen', 'source' => 'google_maps', 'status' => 'new', 'raw_data' => ['google_place_id' => 'ChIJabc123']]);

        $this->postJson('/api/crm/contacts', ['offer_id' => $this->offer->id, ...$this->business()], $this->headers())
            ->assertCreated()
            ->assertJsonPath('created', 1);
    }

    public function test_a_product_or_list_is_required_and_must_belong_to_the_user(): void
    {
        $this->postJson('/api/crm/contacts', $this->business(), $this->headers())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['offer_id', 'list_id']);

        $foreignOffer = $this->offerFor(User::factory()->create(), 'Theirs');
        $foreignList = CrmList::create(['user_id' => $foreignOffer->user_id, 'name' => 'Theirs']);

        $this->postJson('/api/crm/contacts', ['offer_id' => $foreignOffer->id, ...$this->business()], $this->headers())->assertUnprocessable();
        $this->postJson('/api/crm/contacts', ['list_id' => $foreignList->id, ...$this->business()], $this->headers())->assertUnprocessable();
        $this->assertSame(0, CrmContact::count());
        $this->assertSame(0, CrmList::where('offer_id', $foreignOffer->id)->count());
    }

    public function test_a_plan_without_google_maps_is_refused_with_an_upgrade_link(): void
    {
        $user = $this->subscribedUser(['channels' => ['research', 'linkedin']]);
        $offer = $this->offerFor($user, 'Basic product');
        $token = ApiToken::generate($user, 'Laptop')['plainText'];

        $this->postJson('/api/crm/contacts', ['offer_id' => $offer->id, ...$this->business()], $this->headers($token))
            ->assertForbidden()
            ->assertJsonPath('code', 'plan_upgrade_required')
            ->assertJsonPath('upgrade_url', route('billing.index'));

        // LinkedIn people aren't Maps-gated.
        $this->postJson('/api/crm/contacts', [
            'offer_id' => $offer->id, 'source' => 'linkedin', 'name' => 'Ada Obi', 'title' => 'Head of Growth',
            'company' => 'Paystack', 'linkedin_url' => 'https://www.linkedin.com/in/ada-obi/?trk=x',
        ], $this->headers($token))->assertCreated();

        $person = CrmContact::firstOrFail();
        $this->assertSame('linkedin', $person->source);
        $this->assertSame('Head of Growth', $person->title);
        $this->assertSame('https://www.linkedin.com/in/ada-obi/?trk=x', $person->raw_data['linkedin_url']);

        // The same profile again (without tracking params) is a duplicate.
        $this->postJson('/api/crm/contacts', [
            'offer_id' => $offer->id, 'source' => 'linkedin', 'name' => 'Ada Obi', 'linkedin_url' => 'https://linkedin.com/in/ada-obi',
        ], $this->headers($token))->assertOk()->assertJsonPath('results.0.matched_by', 'linkedin_url');
    }

    public function test_the_plan_contact_limit_is_enforced(): void
    {
        $user = $this->subscribedUser(['channels' => ['google_maps'], 'contact_limit' => 1]);
        $offer = $this->offerFor($user, 'Limited');
        $token = ApiToken::generate($user, 'Laptop')['plainText'];

        $this->postJson('/api/crm/contacts', [
            'offer_id' => $offer->id,
            'contacts' => [$this->business(), $this->business(['name' => 'Second', 'place_id' => 'ChIJ2', 'cid' => '2', 'ftid' => null, 'phone' => '0701 000 0000', 'website' => 'second.ng'])],
        ], $this->headers($token))
            ->assertCreated()
            ->assertJsonPath('created', 1)
            ->assertJsonPath('results.1.status', 'limit_reached')
            ->assertJsonPath('limit_reached', true);

        $this->postJson('/api/crm/contacts', ['offer_id' => $offer->id, ...$this->business(['name' => 'Third', 'place_id' => 'ChIJ3', 'cid' => '3', 'ftid' => null, 'phone' => null, 'website' => null])], $this->headers($token))
            ->assertStatus(402)
            ->assertJsonPath('code', 'contact_limit_reached');

        $this->assertSame(1, CrmContact::where('user_id', $user->id)->count());
    }

    public function test_read_only_and_sandbox_tokens_cannot_add_contacts(): void
    {
        $readOnly = ApiToken::generate($this->user, 'Reports', scope: 'read_only')['plainText'];
        $sandbox = ApiToken::generate($this->user, 'Test', isSandbox: true)['plainText'];

        $this->postJson('/api/crm/contacts', ['offer_id' => $this->offer->id, ...$this->business()], $this->headers($readOnly))->assertForbidden();
        $this->postJson('/api/crm/contacts', ['offer_id' => $this->offer->id, ...$this->business()], $this->headers($sandbox))
            ->assertForbidden()
            ->assertJsonPath('code', 'sandbox_token');

        // Lookup stays available to a read-only token.
        $this->postJson('/api/crm/contacts/lookup', ['contacts' => [$this->business()]], $this->headers($readOnly))->assertOk();
        $this->assertSame(0, CrmContact::count());
    }

    public function test_a_team_seat_cannot_add_contacts(): void
    {
        $seat = User::factory()->create(['agency_owner_id' => $this->user->id]);
        $token = ApiToken::generate($seat, 'Seat')['plainText'];

        $this->postJson('/api/crm/contacts', ['offer_id' => $this->offer->id, ...$this->business()], $this->headers($token))
            ->assertForbidden()
            ->assertJsonPath('code', 'owner_only');
    }

    public function test_lookup_reports_which_captured_businesses_are_already_in_the_crm(): void
    {
        $contact = CrmContact::create(['user_id' => $this->user->id, 'name' => 'Mama Put Kitchen', 'source' => 'google_maps', 'status' => 'new', 'raw_data' => ['google_cid' => '11348']]);
        CrmList::forOffer($this->offer)->addContact($contact);

        $response = $this->postJson('/api/crm/contacts/lookup', [
            'contacts' => [
                ['name' => 'New place', 'place_id' => 'ChIJnew'],
                ['name' => 'Mama Put', 'cid' => '11348'],
            ],
        ], $this->headers())->assertOk();

        $this->assertCount(1, $response->json('matches'));
        $response->assertJsonPath('matches.0.index', 1)
            ->assertJsonPath('matches.0.contact_id', $contact->id)
            ->assertJsonPath('matches.0.matched_by', 'cid')
            ->assertJsonPath('matches.0.lists.0.name', 'Acme POS prospects');
    }

    public function test_batches_over_the_configured_maximum_are_rejected(): void
    {
        config(['extension.max_contacts_per_request' => 2]);

        $this->postJson('/api/crm/contacts', [
            'offer_id' => $this->offer->id,
            'contacts' => [$this->business(), $this->business(), $this->business()],
        ], $this->headers())->assertUnprocessable()->assertJsonValidationErrors('contacts');
    }

    public function test_me_stays_backward_compatible_and_adds_v12_fields(): void
    {
        $this->getJson('/api/me', $this->headers())
            ->assertOk()
            ->assertJsonPath('email', $this->user->email)
            ->assertJsonPath('name', $this->user->name)
            ->assertJsonPath('can_use_google_maps', true)
            ->assertJsonPath('extension_url', route('extension.index'))
            ->assertJsonStructure(['credits_balance', 'costs' => ['linkedin_message', 'reply_suggestion'], 'dashboard_url']);
    }

    public function test_v11_endpoints_still_work_unchanged(): void
    {
        $this->getJson('/api/offers', $this->headers())
            ->assertOk()
            ->assertJsonPath('offers.0.product_name', 'Acme POS');

        $this->postJson('/api/clips', [
            'source_url' => 'https://example.com/product', 'title' => 'Product', 'page_type' => 'product_page', 'offer_id' => $this->offer->id,
        ], $this->headers())->assertCreated();

        $this->assertDatabaseHas('research_clips', ['user_id' => $this->user->id, 'offer_id' => $this->offer->id]);
    }
}
