<?php

namespace App\Services\Networks;

use App\Models\NetworkConnection;
use App\Models\NetworkProgram;
use Carbon\CarbonImmutable;

/**
 * One affiliate network's API, translated into AffilStack's terms.
 *
 * @phpstan-type ProgramData array{external_id: string, name: string, advertiser_name?: ?string, status: 'joined'|'pending'|'available', homepage_url?: ?string, tracking_url?: ?string, logo_url?: ?string, category?: ?string, currency?: ?string, commission?: ?string, description?: ?string, deep_linking?: bool, meta?: array<string, mixed>}
 * @phpstan-type EarningData array{external_ref: string, amount_cents: int, currency: string, status: 'pending'|'approved'|'paid'|'declined', converted_at: string, sub_id: ?string, program_external_id: ?string, description: ?string}
 */
interface NetworkAdapter
{
    /**
     * Checks the keys the customer pasted.
     *
     * @param  array<string, string>  $credentials
     * @return array{account_id: string, account_name: string, credentials: array<string, string>}
     *
     * @throws NetworkException
     */
    public function verify(array $credentials): array;

    /**
     * Programs the customer has joined (and, where the network shares its
     * marketplace, ones they could join).
     *
     * @return array<int, ProgramData>
     *
     * @throws NetworkException
     */
    public function programs(NetworkConnection $connection): array;

    /**
     * Commissions in the date range, one entry per sale.
     *
     * @return array<int, EarningData>
     *
     * @throws NetworkException
     */
    public function earnings(NetworkConnection $connection, CarbonImmutable $from, CarbonImmutable $to): array;

    /**
     * The network's tracked link for a program (deep-linked to the
     * destination when the program allows it), without a sub-ID.
     *
     * @throws NetworkException
     */
    public function trackingLink(NetworkConnection $connection, NetworkProgram $program, ?string $destination = null): ?string;

    /**
     * Adds AffilStack's sub-ID to one of this network's tracked links, in
     * the parameter the network reports back.
     */
    public function withSubId(string $url, string $subId): string;

    /**
     * Whether a link was made by this network.
     */
    public function recognises(string $url): bool;
}
