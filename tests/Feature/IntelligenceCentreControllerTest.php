<?php

namespace Tests\Feature;

use App\Models\Offer;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\AI\AIProvider;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * Task #7: the "Intelligence Centre" dashboard page — owner-only, like
 * CRM/earnings/referrals/billing. See IntelligenceCentreService for the
 * actual assessment logic.
 */
class IntelligenceCentreControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
        $this->user = User::factory()->create(['credits_balance' => 1000]);
        $this->user->assignRole('user');
        $plan = Plan::factory()->create(['sort_order' => 2]);
        Subscription::factory()->create(['user_id' => $this->user->id, 'plan_id' => $plan->id, 'status' => 'active']);
    }

    public function test_the_index_page_shows_an_empty_state_before_any_assessment_has_run(): void
    {
        $this->actingAs($this->user)
            ->get(route('intelligence-centre.index'))
            ->assertOk()
            ->assertSee('No assessment yet');
    }

    public function test_running_an_assessment_shows_the_result(): void
    {
        $this->mock(AIProvider::class, function (MockInterface $mock) {
            $mock->shouldReceive('generateJson')->once()->andReturn([
                'health_score' => 70,
                'headline' => 'Strong momentum on blog content.',
                'strengths' => ['Consistently publishing'],
                'gaps' => ['No email sending connected'],
                'next_steps' => ['Connect Gmail for CRM nurture emails'],
                'upgrade_recommended' => false,
            ]);
        });

        $response = $this->actingAs($this->user)->post(route('intelligence-centre.store'));

        $response->assertRedirect(route('intelligence-centre.index'));
        $response->assertSessionHas('success');

        $this->actingAs($this->user)
            ->get(route('intelligence-centre.index'))
            ->assertOk()
            ->assertSee('Strong momentum on blog content.')
            ->assertSee('70');

        $this->assertSame(1000 - config('credits.costs.intelligence_centre'), $this->user->fresh()->credits_balance);
    }

    public function test_running_an_assessment_without_enough_credits_fails_gracefully(): void
    {
        $this->user->update(['credits_balance' => 0]);

        $response = $this->actingAs($this->user)->post(route('intelligence-centre.store'));

        $response->assertSessionHas('error');
        $this->assertSame(0, $this->user->fresh()->intelligenceCentreReport()->count());
    }

    public function test_an_isolated_plan_seat_cannot_reach_the_intelligence_centre(): void
    {
        $offer = Offer::create(['user_id' => $this->user->id, 'product_name' => 'A', 'product_url' => 'https://a.example', 'affiliate_network' => 'ShareASale', 'status' => 'ready']);
        $seat = User::factory()->create(['agency_owner_id' => $this->user->id, 'seat_offer_id' => $offer->id]);
        $seat->assignRole('user');

        $this->actingAs($seat)->get(route('intelligence-centre.index'))->assertForbidden();
        $this->actingAs($seat)->post(route('intelligence-centre.store'))->assertForbidden();
    }
}
