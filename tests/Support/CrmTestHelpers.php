<?php

namespace Tests\Support;

use App\Models\CrmContact;
use App\Models\CrmList;
use App\Models\EmailConnection;
use App\Models\Offer;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Crm\DomainAuthCheck;
use Database\Seeders\RolesSeeder;
use Mockery\MockInterface;

/**
 * Shared fixtures for the CRM campaign / inbox / email-connection tests.
 */
trait CrmTestHelpers
{
    protected function crmUser(array $attributes = [], int $contactLimit = 0): User
    {
        $this->seed(RolesSeeder::class);
        $user = User::factory()->create(['credits_balance' => 100] + $attributes);
        $user->assignRole('user');
        Subscription::factory()->create([
            'user_id' => $user->id,
            'plan_id' => Plan::factory()->create(['contact_limit' => $contactLimit, 'channels' => ['research', 'google_maps']])->id,
        ]);

        $this->mock(DomainAuthCheck::class, fn (MockInterface $mock) => $mock->shouldReceive('check')->andReturn(null));

        return $user;
    }

    protected function offerFor(User $user, string $name = 'Acme CRM'): Offer
    {
        return Offer::create([
            'user_id' => $user->id, 'product_name' => $name, 'product_url' => 'https://acme.example',
            'affiliate_network' => 'ShareASale', 'status' => 'ready',
            'ideal_customer_summary' => 'Dentists who miss calls', 'recommended_angle' => 'Never miss a lead',
            'research_data' => ['pain_points' => ['Missed calls', 'No follow-up']],
        ]);
    }

    protected function contactFor(User $user, array $attributes = []): CrmContact
    {
        static $n = 0;
        $n++;

        return CrmContact::create($attributes + [
            'user_id' => $user->id, 'name' => "Ada Obi{$n}", 'company' => "Sunrise Dental {$n}",
            'email' => "ada{$n}@sunrise.example", 'location' => '12 Allen Ave, Ikeja, Lagos, Nigeria',
            'website' => 'https://sunrise.example', 'source' => 'manual', 'status' => 'new',
        ]);
    }

    protected function listWithContacts(User $user, Offer $offer, int $count = 2): CrmList
    {
        $list = CrmList::forOffer($offer);

        for ($i = 0; $i < $count; $i++) {
            $list->addContact($this->contactFor($user));
        }

        return $list;
    }

    protected function verifiedSmtp(User $user, array $attributes = []): EmailConnection
    {
        return EmailConnection::create($attributes + [
            'user_id' => $user->id,
            'provider' => 'smtp',
            'preset' => 'hostinger',
            'credentials' => ['host' => 'smtp.hostinger.com', 'port' => 465, 'encryption' => 'ssl', 'username' => 'me@mybiz.example', 'password' => 'secret', 'from_email' => 'me@mybiz.example', 'from_name' => 'Me Sender'],
            'connected_email' => 'me@mybiz.example',
            'verification_status' => 'success',
            'verified_at' => now(),
            'footer_business_name' => 'My Biz Ltd',
            'footer_address' => '1 Marina, Lagos',
        ]);
    }
}
