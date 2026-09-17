<?php

namespace Tests\Feature;

use App\Console\Commands\Users\PurgeDeletedAccounts;
use App\Models\Offer;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Audit gap #4: self-service account deletion, soft-delete + 30-day grace
 * period. Requesting deletion (ProfileController::destroy()) immediately
 * deactivates the account — blocked login and no further credit use fall
 * out for free from Eloquent's SoftDeletes global scope excluding the row
 * from every normal User:: lookup, including Fortify's login query — and
 * PurgeDeletedAccounts hard-deletes it 30 days later unless an admin
 * restores it first.
 */
class AccountDeletionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
    }

    public function test_deleting_the_account_requires_the_correct_password(): void
    {
        $user = User::factory()->create(['password' => bcrypt('correct-horse-battery-staple')]);

        $this->actingAs($user)
            ->delete(route('profile.destroy'), ['password' => 'wrong-password'])
            ->assertSessionHasErrors('password');

        $this->assertNotSoftDeleted($user);
        $this->assertAuthenticatedAs($user);
    }

    public function test_deleting_the_account_soft_deletes_it_and_logs_the_user_out(): void
    {
        $user = User::factory()->create(['password' => bcrypt('correct-horse-battery-staple')]);

        $response = $this->actingAs($user)
            ->delete(route('profile.destroy'), ['password' => 'correct-horse-battery-staple']);

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('status');
        $this->assertGuest();
        $this->assertSoftDeleted($user);
    }

    public function test_a_deleted_account_can_no_longer_log_in(): void
    {
        $user = User::factory()->create(['password' => bcrypt('correct-horse-battery-staple')]);
        $user->assignRole('user');

        $this->actingAs($user)->delete(route('profile.destroy'), ['password' => 'correct-horse-battery-staple']);

        $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'correct-horse-battery-staple',
        ]);

        $this->assertGuest();
    }

    public function test_a_deleted_users_data_is_no_longer_reachable_through_normal_lookups(): void
    {
        $user = User::factory()->create();
        $offer = Offer::create([
            'user_id' => $user->id, 'product_name' => 'Widget', 'product_url' => 'https://example.com/widget',
            'affiliate_network' => 'ShareASale', 'status' => 'ready',
        ]);

        $user->delete();

        $this->assertNull(User::find($user->id));
        $this->assertNotNull(User::withTrashed()->find($user->id));
        // The offer row itself isn't touched by a soft-delete (only a real
        // forceDelete cascades) — it just belongs to a no-longer-visible user.
        $this->assertDatabaseHas('offers', ['id' => $offer->id]);
    }

    public function test_an_admin_can_restore_a_deleted_account_within_the_grace_period(): void
    {
        $user = User::factory()->create(['password' => bcrypt('correct-horse-battery-staple')]);
        $user->assignRole('user');
        $user->delete();

        $user->restore();

        $this->assertNotSoftDeleted($user->fresh());

        $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'correct-horse-battery-staple',
        ]);

        $this->assertAuthenticatedAs($user);
    }

    public function test_purge_command_permanently_deletes_accounts_past_the_grace_period(): void
    {
        $recentlyDeleted = User::factory()->create();
        $recentlyDeleted->delete();
        $recentlyDeleted->forceFill(['deleted_at' => now()->subDays(10)])->saveQuietly();

        $pastGracePeriod = User::factory()->create();
        $pastGracePeriod->delete();
        $pastGracePeriod->forceFill(['deleted_at' => now()->subDays(31)])->saveQuietly();

        $this->artisan(PurgeDeletedAccounts::class)->assertSuccessful();

        $this->assertNotNull(User::withTrashed()->find($recentlyDeleted->id));
        $this->assertNull(User::withTrashed()->find($pastGracePeriod->id));
    }

    public function test_purge_command_leaves_active_accounts_untouched(): void
    {
        $active = User::factory()->create();

        $this->artisan(PurgeDeletedAccounts::class)->assertSuccessful();

        $this->assertNotNull(User::find($active->id));
    }
}
