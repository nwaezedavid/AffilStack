<?php

namespace Tests\Feature;

use App\Models\Offer;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Audit gap #1: User::is_suspended previously only blocked the admin panel
 * (User::canAccessPanel()) — a suspended customer could keep using their
 * own dashboard indefinitely. See App\Http\Middleware\EnsureAccountNotSuspended.
 */
class EnsureAccountNotSuspendedTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
    }

    public function test_a_suspended_user_is_logged_out_and_redirected_from_the_dashboard(): void
    {
        $user = User::factory()->create(['is_suspended' => true]);
        $user->assignRole('user');

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('error');
        $this->assertGuest();
    }

    public function test_a_normal_user_is_unaffected(): void
    {
        $user = User::factory()->create(['is_suspended' => false]);
        $user->assignRole('user');

        $this->actingAs($user)->get(route('dashboard'))->assertOk();
    }

    public function test_a_team_seat_is_blocked_when_its_owner_is_suspended_even_if_the_seat_itself_is_not(): void
    {
        $owner = User::factory()->create(['is_suspended' => true]);
        $owner->assignRole('user');
        $offer = Offer::create(['user_id' => $owner->id, 'product_name' => 'A', 'product_url' => 'https://a.example', 'affiliate_network' => 'ShareASale', 'status' => 'ready']);
        $seat = User::factory()->create(['agency_owner_id' => $owner->id, 'seat_offer_id' => $offer->id, 'is_suspended' => false]);
        $seat->assignRole('user');

        $response = $this->actingAs($seat)->get(route('dashboard'));

        $response->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_the_login_page_shows_the_suspension_message_after_the_redirect(): void
    {
        $user = User::factory()->create(['is_suspended' => true]);
        $user->assignRole('user');

        $this->actingAs($user)
            ->followingRedirects()
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Your account has been suspended');
    }
}
