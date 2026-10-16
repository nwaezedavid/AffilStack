<?php

namespace App\Services\Networks;

use App\Models\Earning;
use App\Models\NetworkConnection;
use App\Models\NetworkProgram;
use App\Models\Offer;
use App\Models\TrackedLink;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/**
 * Brings a connected network up to date: its programs, and its
 * commissions as Earnings. Every commission is matched to AffilStack
 * content by its sub-ID (the code of the tracked link the buyer clicked,
 * which knows its product and piece of content), or failing that to the
 * product added from the same program. Re-running is safe: commissions
 * are updated in place by the network's own id, so status changes
 * (pending → approved → paid, or reversed) come through.
 */
class NetworkSyncService
{
    /**
     * @return array{new: int, updated: int, programs: int}
     *
     * @throws NetworkException
     */
    public function sync(NetworkConnection $connection): array
    {
        $adapter = NetworkRegistry::adapter($connection->network);

        try {
            $programs = $this->syncPrograms($connection, $adapter->programs($connection));

            $to = CarbonImmutable::now();
            $from = $connection->last_synced_at
                ? CarbonImmutable::parse($connection->last_synced_at)->subDays((int) config('networks.resync_days', 60))
                : $to->subDays((int) config('networks.initial_sync_days', 90));

            [$new, $updated] = $this->syncEarnings($connection, $adapter->earnings($connection, $from, $to));
        } catch (NetworkException $e) {
            $connection->update([
                'status' => $e->credentialsRejected ? 'error' : $connection->status,
                'last_error' => Str::limit($e->getMessage(), 490, ''),
            ]);

            throw $e;
        }

        $connection->update([
            'status' => 'connected',
            'last_error' => null,
            'last_synced_at' => now(),
            'programs_synced_at' => now(),
            'last_sync_new' => $new,
            'last_sync_updated' => $updated,
        ]);

        return ['new' => $new, 'updated' => $updated, 'programs' => $programs];
    }

    /**
     * @param  array<int, array<string, mixed>>  $programs
     */
    public function syncPrograms(NetworkConnection $connection, array $programs): int
    {
        $seen = [];

        foreach ($programs as $data) {
            $program = $this->saveProgram($connection, $data);
            $seen[] = $program->id;
        }

        // Programs that disappeared (left, or dropped from the marketplace)
        // go, unless a product uses them or the customer added them by hand.
        $connection->programs()
            ->whereNotIn('id', $seen)
            ->whereDoesntHave('offers')
            ->get()
            ->reject(fn (NetworkProgram $program) => (bool) ($program->meta['manual'] ?? false))
            ->each->delete();

        return count($seen);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function saveProgram(NetworkConnection $connection, array $data, bool $manual = false): NetworkProgram
    {
        $program = NetworkProgram::firstOrNew([
            'network_connection_id' => $connection->id,
            'external_id' => Str::limit((string) $data['external_id'], 120, ''),
        ]);

        $meta = array_merge((array) ($program->meta ?? []), (array) ($data['meta'] ?? []));

        if ($manual) {
            $meta['manual'] = true;
        }

        $program->fill([
            'name' => Str::limit((string) ($data['name'] ?? $data['external_id']), 190, ''),
            'advertiser_name' => $data['advertiser_name'] ?? null,
            'status' => in_array($data['status'] ?? '', ['joined', 'pending', 'available'], true) ? $data['status'] : 'joined',
            'homepage_url' => $data['homepage_url'] ?? $program->homepage_url,
            'tracking_url' => $data['tracking_url'] ?? $program->tracking_url,
            'logo_url' => $data['logo_url'] ?? $program->logo_url,
            'category' => $data['category'] ?? null,
            'currency' => $data['currency'] ?? null,
            'commission' => $data['commission'] ?? null,
            'description' => $data['description'] ?? $program->description,
            'deep_linking' => (bool) ($data['deep_linking'] ?? false),
            'meta' => $meta ?: null,
            'last_seen_at' => now(),
        ])->save();

        return $program;
    }

    /**
     * @param  array<int, array<string, mixed>>  $earnings
     * @return array{0: int, 1: int}
     */
    public function syncEarnings(NetworkConnection $connection, array $earnings): array
    {
        $ownerId = $connection->user_id;
        $new = 0;
        $updated = 0;

        foreach ($earnings as $data) {
            $ref = Str::limit((string) $data['external_ref'], 190, '');

            if ($ref === '') {
                continue;
            }

            $earning = Earning::query()
                ->where('user_id', $ownerId)
                ->where('network', $connection->network)
                ->where('external_ref', $ref)
                ->first();

            if ($data['status'] === 'declined') {
                if ($earning && $earning->status !== 'declined') {
                    $earning->update(['status' => 'declined']);
                    $updated++;
                }

                continue;
            }

            $attribution = $this->attribute($connection, $data['sub_id'] ?? null, $data['program_external_id'] ?? null);

            $values = [
                'amount_cents' => max(0, (int) $data['amount_cents']),
                'currency' => (string) $data['currency'],
                'status' => (string) $data['status'],
                'converted_at' => (string) $data['converted_at'],
                'description' => isset($data['description']) ? Str::limit((string) $data['description'], 190, '') : null,
            ];

            if ($earning) {
                // A match found now fills in an earning; one the customer
                // assigned by hand isn't overwritten by "no match".
                if ($attribution['offer_id'] !== null) {
                    $values += $attribution;
                }

                $earning->fill($values);

                if ($earning->isDirty()) {
                    $earning->save();
                    $updated++;
                }

                continue;
            }

            Earning::create($values + $attribution + [
                'user_id' => $ownerId,
                'network' => $connection->network,
                'source' => 'network_sync',
                'external_ref' => $ref,
            ]);
            $new++;
        }

        return [$new, $updated];
    }

    /**
     * @return array{tracked_link_id: ?int, offer_id: ?int, generation_id: ?int}
     */
    public function attribute(NetworkConnection $connection, ?string $subId, ?string $programExternalId): array
    {
        $subId = strtolower(trim((string) $subId));

        if ($subId !== '' && preg_match('/^[a-z0-9]{4,16}$/', $subId)) {
            $link = TrackedLink::query()->where('code', $subId)->where('user_id', $connection->user_id)->first();

            if ($link) {
                return ['tracked_link_id' => $link->id, 'offer_id' => $link->offer_id, 'generation_id' => $link->generation_id];
            }
        }

        if ($programExternalId !== null && $programExternalId !== '') {
            $offerId = Offer::query()
                ->where('user_id', $connection->user_id)
                ->whereHas('networkProgram', fn ($query) => $query
                    ->where('network_connection_id', $connection->id)
                    ->where('external_id', $programExternalId))
                ->value('id');

            if ($offerId) {
                return ['tracked_link_id' => null, 'offer_id' => (int) $offerId, 'generation_id' => null];
            }
        }

        return ['tracked_link_id' => null, 'offer_id' => null, 'generation_id' => null];
    }
}
