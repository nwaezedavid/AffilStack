<?php

namespace Tests\Feature;

use App\Models\CrmContact;
use App\Models\CrmList;
use App\Models\Offer;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Leads\GoogleMapsLeadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Mockery\MockInterface;
use Tests\Support\CrmTestHelpers;
use Tests\TestCase;

/**
 * The CRM organised around lists: product lists (auto-created), custom
 * lists, bulk actions, filters, CSV import, and Local Leads filing.
 */
class CrmListsTest extends TestCase
{
    use CrmTestHelpers, RefreshDatabase;

    public function test_every_researched_product_gets_its_list_in_the_sidebar(): void
    {
        $user = $this->crmUser();
        $offer = $this->offerFor($user, 'Tidio Live Chat');

        $this->actingAs($user)->get(route('crm.index'))
            ->assertOk()
            ->assertSee('Tidio Live Chat prospects')
            ->assertSee('Create Campaign');

        $this->assertSame(1, CrmList::where('offer_id', $offer->id)->count());
    }

    public function test_filtering_by_list_and_source_narrows_the_contacts(): void
    {
        $user = $this->crmUser();
        $list = CrmList::forOffer($this->offerFor($user));
        $list->addContact($this->contactFor($user, ['name' => 'In The List', 'source' => 'google_maps']));
        $this->contactFor($user, ['name' => 'Not Listed', 'source' => 'manual']);

        $this->actingAs($user)->get(route('crm.index', ['list' => $list->id]))->assertSee('In The List')->assertDontSee('Not Listed');
        $this->actingAs($user)->get(route('crm.index', ['source' => 'manual']))->assertSee('Not Listed')->assertDontSee('In The List');
        $this->actingAs($user)->get(route('crm.index', ['list' => 'none']))->assertSee('Not Listed')->assertDontSee('In The List');
    }

    public function test_a_manually_added_contact_can_go_straight_into_a_list(): void
    {
        $user = $this->crmUser();
        $list = CrmList::forOffer($this->offerFor($user));

        $this->actingAs($user)->post(route('crm.store'), ['name' => 'New Person', 'email' => 'new@x.example', 'list_id' => $list->id])
            ->assertSessionHas('success');

        $this->assertTrue($list->contacts()->where('email', 'new@x.example')->exists());
    }

    public function test_a_contact_cannot_be_filed_into_someone_elses_list(): void
    {
        $user = $this->crmUser();
        $other = User::factory()->create();
        $foreign = CrmList::factory()->create(['user_id' => $other->id]);

        $this->actingAs($user)->post(route('crm.store'), ['name' => 'X', 'list_id' => $foreign->id])->assertSessionHasErrors('list_id');
    }

    public function test_custom_lists_can_be_created_renamed_and_deleted_but_product_lists_cannot_be_deleted(): void
    {
        $user = $this->crmUser();

        $this->actingAs($user)->post(route('crm.lists.store'), ['name' => 'Webinar leads', 'color' => 'violet'])->assertRedirect();
        $custom = CrmList::where('name', 'Webinar leads')->firstOrFail();
        $this->assertSame('violet', $custom->color);

        $this->actingAs($user)->patch(route('crm.lists.update', $custom), ['name' => 'Webinar 2026', 'color' => 'teal'])->assertSessionHas('success');
        $this->assertSame('Webinar 2026', $custom->fresh()->name);

        $product = CrmList::forOffer($this->offerFor($user));
        $this->actingAs($user)->delete(route('crm.lists.destroy', $product))->assertSessionHas('error');
        $this->assertModelExists($product);

        $this->actingAs($user)->delete(route('crm.lists.destroy', $custom))->assertRedirect(route('crm.index'));
        $this->assertModelMissing($custom);
    }

    public function test_the_list_page_shows_members_and_its_campaigns_and_is_private(): void
    {
        $user = $this->crmUser();
        $list = $this->listWithContacts($user, $this->offerFor($user), 2);

        $this->actingAs($user)->get(route('crm.lists.show', $list))
            ->assertOk()
            ->assertSee($list->contacts()->first()->name)
            ->assertSee(route('crm.campaigns.create', ['list' => $list->id]), false);

        $stranger = User::factory()->create();
        $stranger->assignRole('user');
        $this->actingAs($stranger)->get(route('crm.lists.show', $list))->assertForbidden();
    }

    public function test_bulk_actions_add_remove_delete_and_export(): void
    {
        $user = $this->crmUser();
        $list = CrmList::factory()->create(['user_id' => $user->id, 'name' => 'Hot leads']);
        $a = $this->contactFor($user, ['name' => 'Alpha']);
        $b = $this->contactFor($user, ['name' => 'Bravo']);
        $foreign = CrmContact::create(['user_id' => User::factory()->create()->id, 'name' => 'Foreign', 'source' => 'manual', 'status' => 'new']);

        $this->actingAs($user)->post(route('crm.contacts.bulk'), ['action' => 'add_to_list', 'list_id' => $list->id, 'contact_ids' => [$a->id, $b->id, $foreign->id]])
            ->assertSessionHas('success');
        $this->assertEqualsCanonicalizing([$a->id, $b->id], $list->contacts()->pluck('crm_contacts.id')->all());

        $this->actingAs($user)->post(route('crm.contacts.bulk'), ['action' => 'remove_from_list', 'list_id' => $list->id, 'contact_ids' => [$a->id]]);
        $this->assertSame([$b->id], $list->contacts()->pluck('crm_contacts.id')->all());

        $csv = $this->actingAs($user)->post(route('crm.contacts.bulk'), ['action' => 'export', 'contact_ids' => [$b->id]])->assertOk()->getContent();
        $this->assertStringContainsString('Bravo', $csv);
        $this->assertStringContainsString('Hot leads', $csv);
        $this->assertStringNotContainsString('Alpha', $csv);

        $this->actingAs($user)->post(route('crm.contacts.bulk'), ['action' => 'delete', 'contact_ids' => [$a->id, $foreign->id]]);
        $this->assertModelMissing($a);
        $this->assertModelExists($foreign);
    }

    public function test_csv_import_previews_guessed_columns_then_imports_into_the_list_without_duplicates(): void
    {
        $user = $this->crmUser();
        $list = CrmList::forOffer($this->offerFor($user));
        $existing = $this->contactFor($user, ['email' => 'dupe@clinic.example', 'name' => 'Existing']);

        $csv = "First Name,Last Name,E-mail Address,Company,Website\n"
            ."Ngozi,Eze,ngozi@clinic.example,Eze Dental,ezedental.ng\n"
            ."Dupe,Person,DUPE@clinic.example,Dupe Co,\n"
            .",,,,\n";
        $file = UploadedFile::fake()->createWithContent('contacts.csv', $csv);

        $response = $this->actingAs($user)->post(route('crm.import.upload'), ['file' => $file, 'list_id' => $list->id]);
        $response->assertRedirect();
        $previewUrl = $response->headers->get('Location');

        $this->actingAs($user)->get($previewUrl)
            ->assertOk()
            ->assertViewHas('mapping', ['first_name', 'last_name', 'email', 'company', 'website'])
            ->assertSee('Eze Dental');

        $token = basename(parse_url($previewUrl, PHP_URL_PATH));
        $this->actingAs($user)->post(route('crm.import.store', $token), [
            'list_id' => $list->id,
            'mapping' => ['first_name', 'last_name', 'email', 'company', 'website'],
        ])->assertRedirect(route('crm.lists.show', $list))->assertSessionHas('success');

        $ngozi = CrmContact::where('email', 'ngozi@clinic.example')->firstOrFail();
        $this->assertSame('Ngozi Eze', $ngozi->name);
        $this->assertSame('https://ezedental.ng', $ngozi->website);
        $this->assertSame('import', $ngozi->source);
        $this->assertSame(2, $user->crmContacts()->count());
        $this->assertTrue($list->contacts()->whereKey($existing->id)->exists());
        $this->assertTrue($list->contacts()->whereKey($ngozi->id)->exists());
    }

    public function test_csv_import_stops_at_the_plan_contact_limit(): void
    {
        $user = $this->crmUser([], contactLimit: 1);
        $list = CrmList::factory()->create(['user_id' => $user->id]);
        $file = UploadedFile::fake()->createWithContent('c.csv', "Email\na@x.example\nb@x.example\n");

        $previewUrl = $this->actingAs($user)->post(route('crm.import.upload'), ['file' => $file, 'list_id' => $list->id])->headers->get('Location');
        $token = basename(parse_url($previewUrl, PHP_URL_PATH));

        $this->actingAs($user)->post(route('crm.import.store', $token), ['list_id' => $list->id, 'mapping' => ['email']]);

        $this->assertSame(1, $user->crmContacts()->count());
    }

    public function test_local_leads_import_files_the_business_into_the_chosen_products_list(): void
    {
        $user = $this->crmUser();
        $offer = $this->offerFor($user, 'Tidio');
        $this->mock(GoogleMapsLeadService::class, fn (MockInterface $mock) => $mock->shouldReceive('details')->andReturn(['phone' => '+234 1', 'website' => 'https://clinic.example']));

        $this->actingAs($user)->post(route('leads.import'), [
            'place_id' => 'place-1', 'name' => 'Clinic One', 'address' => 'Ikeja, Lagos', 'type' => 'dentist', 'offer_id' => $offer->id,
        ])->assertSessionHas('success', '"Clinic One" added to Tidio prospects.');

        $contact = CrmContact::where('name', 'Clinic One')->firstOrFail();
        $this->assertTrue(CrmList::forOffer($offer)->contacts()->whereKey($contact->id)->exists());
    }

    public function test_local_leads_ignores_an_offer_that_is_not_the_users(): void
    {
        $user = $this->crmUser();
        $foreignOffer = Offer::create(['user_id' => User::factory()->create()->id, 'product_name' => 'X', 'product_url' => 'https://x.example', 'affiliate_network' => 'Y', 'status' => 'ready']);
        $this->mock(GoogleMapsLeadService::class, fn (MockInterface $mock) => $mock->shouldReceive('details')->andReturn(['phone' => null, 'website' => null]));

        $this->actingAs($user)->post(route('leads.import'), ['place_id' => 'p2', 'name' => 'Clinic Two', 'offer_id' => $foreignOffer->id]);

        $this->assertSame(0, CrmList::where('offer_id', $foreignOffer->id)->count());
    }

    public function test_team_seats_cannot_reach_crm_lists_campaigns_or_the_inbox(): void
    {
        $owner = $this->crmUser();
        $plan = Plan::factory()->create(['seat_mode' => 'isolated', 'team_seats' => 3]);
        Subscription::factory()->create(['user_id' => $owner->id, 'plan_id' => $plan->id]);
        $offer = $this->offerFor($owner);
        $list = CrmList::forOffer($offer);
        $seat = User::factory()->create(['agency_owner_id' => $owner->id, 'seat_offer_id' => $offer->id]);
        $seat->assignRole('user');

        $this->actingAs($seat)->get(route('crm.lists.show', $list))->assertForbidden();
        $this->actingAs($seat)->get(route('crm.campaigns.index'))->assertForbidden();
        $this->actingAs($seat)->get(route('crm.inbox'))->assertForbidden();
    }
}
