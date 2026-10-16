<?php

namespace App\Services\Networks;

use App\Jobs\SyncNetworkConnection;
use App\Models\NetworkConnection;
use App\Models\Offer;
use App\Models\User;
use App\Services\Networks\Adapters\Digistore24Adapter;
use InvalidArgumentException;

/**
 * Connecting and disconnecting affiliate network accounts, within the
 * plan's allowance (plans.network_connections_limit: 0 = not included,
 * -1 = unlimited). Team seats share their owner's connections; only the
 * owner manages them.
 */
class NetworkConnectionService
{
    public function owner(User $user): User
    {
        return $user->billableUser();
    }

    public function limitFor(User $user): int
    {
        return (int) ($this->owner($user)->activeSubscription?->plan?->network_connections_limit ?? 0);
    }

    public function included(User $user): bool
    {
        return $this->limitFor($user) !== 0;
    }

    public function usedFor(User $user): int
    {
        return $this->owner($user)->networkConnections()->active()->count();
    }

    public static function limitLabel(int $limit): string
    {
        return match (true) {
            $limit < 0 => 'Unlimited networks',
            $limit === 0 => 'Not included',
            $limit === 1 => '1 network',
            default => $limit.' networks',
        };
    }

    /**
     * @throws InvalidArgumentException
     */
    public function ensureCanConnect(User $user, string $network): void
    {
        if (! NetworkRegistry::exists($network)) {
            throw new InvalidArgumentException('AffilStack doesn\'t connect to that network yet.');
        }

        $limit = $this->limitFor($user);

        if ($limit === 0) {
            throw new InvalidArgumentException('Your plan doesn\'t include affiliate network connections. Upgrade your plan to connect networks.');
        }

        $alreadyConnected = $this->owner($user)->networkConnections()->active()->where('network', $network)->exists();

        if (! $alreadyConnected && $limit > 0 && $this->usedFor($user) >= $limit) {
            throw new InvalidArgumentException('Your plan allows '.static::limitLabel($limit).'. Disconnect one or upgrade your plan to add another.');
        }
    }

    /**
     * Checks the keys with the network and saves the connection, then
     * starts the first sync.
     *
     * @param  array<string, string>  $credentials
     *
     * @throws InvalidArgumentException|NetworkException
     */
    public function connect(User $user, string $network, array $credentials): NetworkConnection
    {
        $this->ensureCanConnect($user, $network);

        $verified = NetworkRegistry::adapter($network)->verify($credentials);

        $connection = NetworkConnection::updateOrCreate(
            ['user_id' => $this->owner($user)->id, 'network' => $network],
            [
                'credentials' => $verified['credentials'],
                'account_id' => mb_substr($verified['account_id'], 0, 120),
                'account_name' => mb_substr($verified['account_name'], 0, 190),
                'status' => 'connected',
                'connect_token' => null,
                'last_error' => null,
            ],
        );

        SyncNetworkConnection::dispatch($connection);

        return $connection;
    }

    /**
     * Forgets the keys. Programs go with the connection; products and
     * earnings stay, and products fall back to the customer's own link.
     */
    public function disconnect(NetworkConnection $connection): void
    {
        if ($connection->network === 'digistore24' && $connection->status !== 'pending') {
            app(Digistore24Adapter::class)->unregister($connection);
        }

        $programIds = $connection->programs()->pluck('id');

        foreach (Offer::query()->whereIn('network_program_id', $programIds)->get() as $offer) {
            $offer->update(['use_network_link' => false]);
        }

        $connection->delete();
    }
}
