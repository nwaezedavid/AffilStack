<?php

namespace Tests\Feature;

use App\Models\Offer;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Audit gap #3: the Offers list had no search or filter at all.
 */
class OfferSearchAndFilterTest extends TestCase
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

    protected function offer(string $name, string $status = 'ready'): Offer
    {
        return Offer::create(['user_id' => $this->user->id, 'product_name' => $name, 'product_url' => 'https://example.com/'.Str::slug($name), 'affiliate_network' => 'ShareASale', 'status' => $status]);
    }

    public function test_searching_by_product_name_narrows_the_list(): void
    {
        $this->offer('Acme Widget');
        $this->offer('Zenith Blender');

        $response = $this->actingAs($this->user)->get(route('offers.index', ['q' => 'acme']));

        $response->assertOk()->assertSee('Acme Widget')->assertDontSee('Zenith Blender');
    }

    public function test_filtering_by_status_narrows_the_list(): void
    {
        $this->offer('Acme Widget', 'ready');
        $this->offer('Zenith Blender', 'archived');

        $response = $this->actingAs($this->user)->get(route('offers.index', ['status' => 'archived']));

        $response->assertOk()->assertSee('Zenith Blender')->assertDontSee('Acme Widget');
    }

    public function test_an_invalid_status_value_is_ignored_rather_than_erroring(): void
    {
        $this->offer('Acme Widget');

        $this->actingAs($this->user)
            ->get(route('offers.index', ['status' => 'not-a-real-status; DROP TABLE offers;']))
            ->assertOk()
            ->assertSee('Acme Widget');
    }

    public function test_a_search_with_no_matches_shows_the_no_results_message(): void
    {
        $this->offer('Acme Widget');

        $this->actingAs($this->user)
            ->get(route('offers.index', ['q' => 'nonexistent']))
            ->assertOk()
            ->assertSee('No offers match your search');
    }

    public function test_search_never_returns_another_users_offers(): void
    {
        $other = User::factory()->create();
        $other->assignRole('user');
        Offer::create(['user_id' => $other->id, 'product_name' => 'Acme Widget', 'product_url' => 'https://other.example', 'affiliate_network' => 'ShareASale', 'status' => 'ready']);

        $this->actingAs($this->user)
            ->get(route('offers.index', ['q' => 'acme']))
            ->assertOk()
            ->assertSee('No offers match your search');
    }
}
