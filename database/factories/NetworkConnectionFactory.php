<?php

namespace Database\Factories;

use App\Models\NetworkConnection;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NetworkConnection>
 */
class NetworkConnectionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'network' => 'awin',
            'credentials' => ['api_token' => 'test-token'],
            'account_id' => '45628',
            'account_name' => 'Test Publisher',
            'status' => 'connected',
        ];
    }

    public function network(string $network, array $credentials, ?string $accountId = null): static
    {
        return $this->state(fn () => ['network' => $network, 'credentials' => $credentials, 'account_id' => $accountId]);
    }
}
