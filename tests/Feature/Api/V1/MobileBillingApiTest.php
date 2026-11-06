<?php

namespace Tests\Feature\Api\V1;

use App\Models\AgentActivity;
use App\Models\ApiToken;
use App\Models\MobileDevice;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Credits\CreditManager;
use App\Services\CustomerWallet\CustomerWalletManager;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * More > Plan & credits in the phone app: view only, never a way to buy.
 */
class MobileBillingApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
    }

    /**
     * @return array<string, string>
     */
    protected function phone(User $user): array
    {
        $device = MobileDevice::factory()->for($user)->create();
        $plain = 'afmt_'.Str::random(48);

        ApiToken::create([
            'user_id' => $user->id,
            'mobile_device_id' => $device->id,
            'type' => 'mobile',
            'scope' => 'full',
            'name' => 'AffilStack app',
            'token_hash' => hash('sha256', $plain),
            'expires_at' => now()->addHour(),
        ]);

        return ['Authorization' => 'Bearer '.$plain];
    }

    protected function owner(): User
    {
        $user = User::factory()->create(['credits_balance' => 0]);
        $user->assignRole('user');
        Subscription::factory()->create(['user_id' => $user->id, 'plan_id' => Plan::factory()->create(['name' => 'Growth', 'credits_per_month' => 500])->id]);

        return $user;
    }

    public function test_the_owner_sees_plan_balances_and_recent_activity(): void
    {
        $user = $this->owner();
        app(CreditManager::class)->grant($user, 500, 'monthly_grant');
        app(CreditManager::class)->spend($user, 15, 'blog_article');
        app(CustomerWalletManager::class)->walletFor($user);

        $response = $this->getJson('/api/v1/mobile/billing', $this->phone($user))
            ->assertOk()
            ->assertJsonPath('data.account.plan.name', 'Growth')
            ->assertJsonPath('data.credits.balance', 485)
            ->assertJsonPath('data.credits.monthly_allowance', 500)
            ->assertJsonPath('data.shows_history', true)
            ->assertJsonPath('data.credit_activity.0.label', 'Blog article')
            ->assertJsonPath('data.credit_activity.0.amount', -15)
            ->assertJsonPath('data.credit_activity.1.label', 'Monthly plan credits')
            ->assertJsonPath('data.credit_activity.1.amount', 500);

        $this->assertArrayHasKey('formatted', $response->json('data.wallet'));
    }

    public function test_nothing_in_the_answer_points_to_buying(): void
    {
        $user = $this->owner();

        $body = $this->getJson('/api/v1/mobile/billing', $this->phone($user))->assertOk()->getContent();

        $this->assertStringNotContainsString(route('registration.pricing'), $body);
        $this->assertStringNotContainsString(route('billing.index'), $body);
        $this->assertStringNotContainsString(route('wallet.index'), $body);
    }

    public function test_team_seats_see_balances_but_not_the_owners_history(): void
    {
        $owner = $this->owner();
        app(CreditManager::class)->grant($owner, 200, 'monthly_grant');
        $seat = User::factory()->create(['agency_owner_id' => $owner->id]);
        $seat->assignRole('user');

        $this->getJson('/api/v1/mobile/billing', $this->phone($seat))
            ->assertOk()
            ->assertJsonPath('data.credits.balance', 200)
            ->assertJsonPath('data.shows_history', false)
            ->assertJsonCount(0, 'data.credit_activity')
            ->assertJsonCount(0, 'data.wallet_activity');
    }

    public function test_developer_tokens_cannot_read_billing_here(): void
    {
        $user = $this->owner();
        $plain = ApiToken::generate($user, 'Script')['plainText'];

        $this->getJson('/api/v1/mobile/billing', ['Authorization' => "Bearer {$plain}"])->assertForbidden();
    }

    public function test_the_app_config_carries_no_link_to_billing_or_pricing(): void
    {
        $body = $this->getJson('/api/v1/mobile/config')
            ->assertOk()
            ->assertJsonMissingPath('links.billing')
            ->assertJsonPath('features.in_app_purchases', false)
            ->getContent();

        $this->assertStringNotContainsString(route('billing.index'), $body);
        $this->assertStringNotContainsString(route('registration.pricing'), $body);
    }

    public function test_affilstacks_own_upgrade_and_win_back_messages_stay_out_of_the_agents_feed(): void
    {
        $user = $this->owner();
        AgentActivity::create(['user_id' => $user->id, 'agent' => 'upgrade_nudge', 'action' => 'credits', 'summary' => 'Reached 80% of their credits — sent an upgrade suggestion.', 'status' => AgentActivity::STATUS_DONE]);
        AgentActivity::create(['user_id' => $user->id, 'agent' => 'churn_saver', 'action' => 'inactive', 'summary' => 'Sent a personal check-in.', 'status' => AgentActivity::STATUS_DONE]);
        AgentActivity::create(['user_id' => $user->id, 'agent' => 'weekly_briefing', 'action' => 'briefing', 'summary' => 'Your week', 'status' => AgentActivity::STATUS_DONE]);

        $this->getJson('/api/v1/mobile/agents', $this->phone($user))
            ->assertOk()
            ->assertJsonCount(1, 'activity')
            ->assertJsonPath('activity.0.agent', 'weekly_briefing')
            ->assertJsonMissingPath('week.upgrade_nudge')
            ->assertJsonMissingPath('week.churn_saver');
    }
}
