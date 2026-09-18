<?php

namespace Tests\Feature;

use App\Models\Plan;
use Database\Seeders\PlansSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Item #2 of the pricing/payments audit: the repriced catalog, and the
 * standing rule that annual billing is always exactly 15% off — enforced
 * by Plan::yearlyPriceCentsFor() rather than typed twice. See PlansSeeder
 * for the cost/margin reasoning behind the actual numbers.
 */
class PlansSeederPricingTest extends TestCase
{
    use RefreshDatabase;

    public function test_yearly_price_is_always_exactly_fifteen_percent_off_twelve_months(): void
    {
        $this->assertSame(19380, Plan::yearlyPriceCentsFor(1900));
        $this->assertSame(0, Plan::yearlyPriceCentsFor(0));

        // 12 * (1 - 0.15) = 10.2 exactly, for any monthly price.
        foreach ([1900, 5900, 14900, 24900, 39900] as $monthly) {
            $this->assertSame((int) round($monthly * 10.2), Plan::yearlyPriceCentsFor($monthly));
        }
    }

    public function test_the_seeder_produces_the_repriced_catalog_with_the_correct_annual_discount(): void
    {
        $this->seed(PlansSeeder::class);

        $expected = [
            'starter' => 1900,
            'growth' => 5900,
            'pro' => 14900,
            'business' => 24900,
            'agency' => 39900,
        ];

        foreach ($expected as $slug => $monthlyCents) {
            $plan = Plan::where('slug', $slug)->firstOrFail();

            $this->assertSame($monthlyCents, $plan->price_monthly_cents, "{$slug} monthly price");
            $this->assertSame(Plan::yearlyPriceCentsFor($monthlyCents), $plan->price_yearly_cents, "{$slug} yearly price");

            // Every tier still clears a healthy margin against the
            // blended ~$0.008/credit estimate documented in PlansSeeder —
            // guards against a future price/credit edit accidentally
            // giving credits away below cost.
            $estimatedCostCents = (int) round($plan->credits_per_month * 0.8);
            $this->assertGreaterThan($estimatedCostCents * 4, $monthlyCents, "{$slug} margin");
        }
    }

    public function test_re_running_the_seeder_is_idempotent(): void
    {
        $this->seed(PlansSeeder::class);
        $this->seed(PlansSeeder::class);

        $this->assertSame(5, Plan::count());
    }
}
