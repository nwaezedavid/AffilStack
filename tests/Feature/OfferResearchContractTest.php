<?php

namespace Tests\Feature;

use App\Models\Offer;
use App\Models\User;
use App\Services\AI\AIProvider;
use App\Services\Modules\OfferResearchService;
use App\Support\ResearchPresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * The extended research JSON contract (overview, who needs it, personas,
 * Google Maps / LinkedIn keywords, locations) — stored alongside every
 * original key so older offers and consumers keep working — and how the
 * results render, including the Maps/LinkedIn deep links.
 */
class OfferResearchContractTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    protected function extendedResult(): array
    {
        return [
            'ideal_customer_summary' => 'Small clinic owners who want more bookings.',
            'pain_points' => ['Empty appointment slots', 'No time for marketing'],
            'where_to_find' => ['r/dentistry', 'Dental practice owners Facebook group'],
            'recommended_channel' => 'google_maps',
            'recommended_channel_reason' => 'Clinics are listed on Maps.',
            'recommended_angle' => 'Fill your chair in 30 days.',
            'secondary_channel' => 'linkedin',
            'suggested_maps_niche' => 'dentists',
            'suggested_maps_location' => 'Lagos, Nigeria',
            'product_overview' => 'A booking widget that turns website visitors into appointments.',
            'who_needs_it' => ['individuals' => ['Solo practitioners'], 'businesses' => ['Dental clinics', 'Physio centres']],
            'buyer_personas' => [
                ['name' => 'The busy clinic owner', 'description' => 'Runs two chairs and hates admin.'],
                ['name' => 'The practice manager', 'description' => 'Measured on bookings.'],
            ],
            'google_maps_keywords' => [
                ['keyword' => 'dental clinic', 'why' => 'Needs steady bookings.'],
                ['keyword' => 'physiotherapy centre', 'why' => 'Appointment driven.'],
            ],
            'suggested_locations' => ['Lagos, Nigeria', 'Abuja, Nigeria'],
            'linkedin_keywords' => ['people' => ['Practice Manager', 'Clinic Owner'], 'companies' => ['Dental clinics']],
            'linkedin_search_tips' => ['Filter by 2nd-degree connections.'],
        ];
    }

    public function test_research_asks_for_the_extended_contract_with_the_users_location_and_stores_it(): void
    {
        $user = User::factory()->create(['credits_balance' => 100, 'city' => 'Lagos', 'country' => 'Nigeria']);
        $offer = Offer::create([
            'user_id' => $user->id, 'product_name' => 'BookWidget', 'product_url' => 'https://bookwidget.example',
            'affiliate_network' => 'PartnerStack', 'status' => 'queued',
        ]);

        $this->mock(AIProvider::class, function (MockInterface $mock) {
            $mock->shouldReceive('generateJson')->once()->withArgs(function (string $system, string $prompt) {
                return str_contains($system, '"google_maps_keywords"')
                    && str_contains($system, '"linkedin_keywords"')
                    && str_contains($system, '"buyer_personas"')
                    && str_contains($system, '"product_overview"')
                    && str_contains($system, '"suggested_maps_niche"')
                    && str_contains($prompt, 'Lagos, Nigeria');
            })->andReturn($this->extendedResult());
        });

        app(OfferResearchService::class)->research($offer);

        $offer->refresh();
        $this->assertSame('ready', $offer->status);
        // Every original column/key is still populated…
        $this->assertSame('Small clinic owners who want more bookings.', $offer->ideal_customer_summary);
        $this->assertSame("r/dentistry\nDental practice owners Facebook group", $offer->where_to_find);
        $this->assertSame('dentists', $offer->suggested_maps_niche);
        // …and the extended keys ride along in research_data.
        $this->assertSame('A booking widget that turns website visitors into appointments.', $offer->research_data['product_overview']);
        $this->assertSame('dental clinic', $offer->research_data['google_maps_keywords'][0]['keyword']);
        $this->assertSame(['Practice Manager', 'Clinic Owner'], $offer->research_data['linkedin_keywords']['people']);
    }

    public function test_an_old_style_result_without_the_new_keys_still_completes_and_renders(): void
    {
        $user = User::factory()->create(['credits_balance' => 100]);
        $offer = Offer::create([
            'user_id' => $user->id, 'product_name' => 'Acme Widget', 'product_url' => 'https://example.com',
            'affiliate_network' => 'Awin', 'status' => 'queued',
        ]);

        $this->mock(AIProvider::class, function (MockInterface $mock) {
            $mock->shouldReceive('generateJson')->once()->andReturn([
                'ideal_customer_summary' => 'Busy parents.',
                'pain_points' => ['no time'],
                'where_to_find' => ['r/parenting'],
                'recommended_channel' => 'blog',
                'recommended_angle' => 'Save time.',
            ]);
        });

        app(OfferResearchService::class)->research($offer);

        $this->assertSame('ready', $offer->fresh()->status);

        $this->actingAs($user)->get(route('offers.show', $offer))
            ->assertOk()
            ->assertSee('Busy parents.')
            ->assertSee('r/parenting')
            ->assertSee('Refresh research')
            ->assertDontSee('Google Maps keywords');
    }

    public function test_results_render_maps_and_linkedin_links_and_the_prospects_panel(): void
    {
        $user = User::factory()->create(['city' => 'Lagos', 'country' => 'Nigeria']);
        $result = $this->extendedResult();
        $offer = Offer::create([
            'user_id' => $user->id, 'product_name' => 'BookWidget', 'product_url' => 'https://bookwidget.example',
            'affiliate_network' => 'PartnerStack', 'status' => 'ready',
            'ideal_customer_summary' => $result['ideal_customer_summary'], 'recommended_channel' => 'google_maps',
            'recommended_angle' => $result['recommended_angle'], 'research_data' => $result,
            'suggested_maps_niche' => 'dentists', 'suggested_maps_location' => 'Lagos, Nigeria',
        ]);

        $response = $this->actingAs($user)->get(route('offers.show', $offer));

        $response->assertOk()
            ->assertSee('A booking widget that turns website visitors into appointments.')
            ->assertSee('The busy clinic owner')
            ->assertSee('Dental clinics')
            ->assertSee('Physio centres')
            ->assertSee('Empty appointment slots')
            ->assertSee('https://www.google.com/maps/search/dental+clinic+in+Lagos%2C+Nigeria', false)
            ->assertSee('data-maps-location="Abuja, Nigeria"', false)
            ->assertSee('https://www.linkedin.com/search/results/people/?keywords=Practice%20Manager', false)
            ->assertSee('https://www.linkedin.com/search/results/companies/?keywords=Dental%20clinics', false)
            ->assertSee('Filter by 2nd-degree connections.')
            ->assertSee('Your prospects for this product')
            ->assertSee(route('extension.index'), false);

        // Viewing the hub creates the product's default prospect list.
        $this->assertDatabaseHas('crm_lists', ['user_id' => $user->id, 'offer_id' => $offer->id]);
    }

    public function test_presenter_tolerates_strings_where_lists_were_expected(): void
    {
        $offer = new Offer([
            'status' => 'ready',
            'where_to_find' => "Forum A\nForum B",
            'research_data' => [
                'pain_points' => "- slow\n- costly",
                'who_needs_it' => ['Freelancers', 'Agencies'],
                'buyer_personas' => ['A busy freelancer'],
                'google_maps_keywords' => ['coworking space'],
                'linkedin_keywords' => ['Founder'],
            ],
        ]);

        $presenter = new ResearchPresenter($offer);

        $this->assertSame(['slow', 'costly'], $presenter->painPoints());
        $this->assertSame(['Forum A', 'Forum B'], $presenter->whereToFind());
        $this->assertSame(['individuals' => ['Freelancers', 'Agencies'], 'businesses' => []], $presenter->whoNeedsIt());
        $this->assertSame([['name' => '', 'description' => 'A busy freelancer']], $presenter->personas());
        $this->assertSame([['keyword' => 'coworking space', 'why' => '']], $presenter->mapsKeywords());
        $this->assertSame(['people' => ['Founder'], 'companies' => []], $presenter->linkedinKeywords());
    }

    public function test_locations_fall_back_to_the_viewers_profile_city(): void
    {
        $user = User::factory()->make(['city' => 'Nairobi', 'country' => 'Kenya']);
        $offer = new Offer(['status' => 'ready', 'research_data' => []]);

        $this->assertSame(['Nairobi, Kenya'], (new ResearchPresenter($offer, $user))->locations());
    }
}
