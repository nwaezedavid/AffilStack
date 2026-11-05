<?php

namespace Tests\Feature;

use App\Models\CrmContact;
use App\Models\CrmEmailSend;
use App\Models\Earning;
use App\Models\Generation;
use App\Models\Offer;
use App\Models\TrackedLink;
use App\Models\User;
use App\Services\Dashboard\ProgressCounter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The dashboard's "What AffilStack has done for you" progress counter.
 */
class DashboardProgressCounterTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array<string, mixed>>
     */
    protected function tiles(User $user): array
    {
        return collect(app(ProgressCounter::class)->for($user)['tiles'])->keyBy('key')->all();
    }

    public function test_it_counts_what_affilstack_has_done_for_the_account(): void
    {
        $user = User::factory()->create();
        $offer = Offer::create(['user_id' => $user->id, 'product_name' => 'Widget', 'product_url' => 'https://example.com/w', 'affiliate_network' => 'ShareASale', 'status' => 'ready']);

        Generation::create(['user_id' => $user->id, 'offer_id' => $offer->id, 'module' => 'research', 'status' => 'completed']);
        Generation::create(['user_id' => $user->id, 'offer_id' => $offer->id, 'module' => 'blog_article', 'status' => 'completed']);
        Generation::create(['user_id' => $user->id, 'offer_id' => $offer->id, 'module' => 'blog_article', 'status' => 'failed']);

        $contact = CrmContact::create(['user_id' => $user->id, 'name' => 'Ada', 'email' => 'ada@example.com', 'source' => 'manual', 'status' => 'new']);
        CrmEmailSend::create(['user_id' => $user->id, 'crm_contact_id' => $contact->id, 'kind' => 'manual', 'subject' => 'Hi', 'body' => 'Hello', 'status' => 'sent', 'sent_at' => now(), 'tracking_token' => 'tok-a']);
        CrmEmailSend::create(['user_id' => $user->id, 'crm_contact_id' => $contact->id, 'kind' => 'manual', 'subject' => 'Hi', 'body' => 'Hello', 'status' => 'queued', 'tracking_token' => 'tok-b']);

        TrackedLink::create(['user_id' => $user->id, 'offer_id' => $offer->id, 'module' => 'blog_article', 'code' => 'abc1234', 'destination_url' => 'https://example.com/w', 'clicks_count' => 12]);
        Earning::create(['user_id' => $user->id, 'network' => 'ShareASale', 'source' => 'manual', 'amount_cents' => 2500, 'currency' => 'USD', 'status' => 'approved', 'converted_at' => now()]);

        // Someone else's work never counts.
        $other = User::factory()->create();
        CrmContact::create(['user_id' => $other->id, 'name' => 'Other', 'source' => 'manual', 'status' => 'new']);

        $tiles = $this->tiles($user);

        $this->assertSame(1, $tiles['products']['raw']);
        $this->assertSame(1, $tiles['content']['raw']);
        $this->assertSame(1, $tiles['prospects']['raw']);
        $this->assertSame(1, $tiles['emails']['raw']);
        $this->assertSame(12, $tiles['clicks']['raw']);
        $this->assertSame(1, $tiles['sales']['raw']);
        $this->assertSame('$25 in commissions', $tiles['sales']['note']);
        // 90 minutes of research + 120 minutes of article = 3.5 hours, rounded down.
        $this->assertSame(3, $tiles['hours']['raw']);
        $this->assertSame(1, $tiles['content']['week']);
    }

    public function test_the_milestone_is_the_next_one_above_the_total(): void
    {
        $user = User::factory()->create();

        foreach (range(1, 15) as $i) {
            CrmContact::create(['user_id' => $user->id, 'name' => "Lead {$i}", 'source' => 'manual', 'status' => 'new']);
        }

        $progress = app(ProgressCounter::class)->for($user);

        $this->assertSame(15, $progress['total']);
        $this->assertSame(['next' => 25, 'previous' => 10, 'percent' => 33], $progress['milestone']);
    }

    public function test_the_dashboard_shows_the_progress_card(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('What AffilStack has done for you')
            ->assertSee('Hours of work saved (estimate)')
            ->assertSee('Research your first product');
    }
}
