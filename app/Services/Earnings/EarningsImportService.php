<?php

namespace App\Services\Earnings;

use App\Models\Earning;
use App\Models\TrackedLink;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Throwable;

/**
 * Feature 2 (conversion & earnings tracker). Every affiliate network
 * exports its CSV with different column names, so rather than asking the
 * user to hand-map columns, this auto-detects the common ones by header
 * text — good enough for the major networks' default exports, and any row
 * that doesn't parse cleanly is skipped rather than guessed at. A row
 * that doesn't carry a recognizable tracking code still gets imported
 * (it still counts toward the user's totals) — it's just left unmatched
 * for EarningsController::assignOffer() to fix up by hand.
 */
class EarningsImportService
{
    /** @var array<string, array<int, string>> */
    protected const HEADER_ALIASES = [
        'date' => ['date', 'conversion date', 'transaction date', 'conversion_date', 'transaction_date', 'created date'],
        'amount' => ['amount', 'commission', 'commission amount', 'payout', 'payout amount', 'earnings'],
        'currency' => ['currency', 'curr'],
        'status' => ['status', 'commission status', 'payout status'],
        'code' => ['subid', 'sub id', 'sub-id', 'tracking id', 'tracking_id', 'click id', 'clickid', 'aff_sub', 'affiliate sub id', 'ref'],
        'external_ref' => ['transaction id', 'conversion id', 'transaction_id', 'conversion_id', 'id'],
    ];

    /** @var array<int, string> */
    protected const DECLINED_STATUSES = ['declined', 'rejected', 'void', 'voided', 'reversed', 'cancelled', 'canceled', 'refused'];

    /** @var array<int, string> */
    protected const PAID_STATUSES = ['paid', 'completed', 'closed-paid'];

    /** @var array<int, string> */
    protected const APPROVED_STATUSES = ['approved', 'locked', 'confirmed', 'ready', 'payable'];

    /**
     * @return array{imported: int, matched: int, unmatched: int, skipped: int, declined: int}
     */
    public function import(User $user, UploadedFile $file, string $network): array
    {
        $handle = fopen($file->getRealPath(), 'r');

        if (! $handle) {
            return ['imported' => 0, 'matched' => 0, 'unmatched' => 0, 'skipped' => 0, 'declined' => 0];
        }

        $header = fgetcsv($handle, null, ',', '"', '\\');

        if (! $header) {
            fclose($handle);

            return ['imported' => 0, 'matched' => 0, 'unmatched' => 0, 'skipped' => 0, 'declined' => 0];
        }

        $columns = $this->detectColumns($header);
        $stats = ['imported' => 0, 'matched' => 0, 'unmatched' => 0, 'skipped' => 0, 'declined' => 0];

        while (($row = fgetcsv($handle, null, ',', '"', '\\')) !== false) {
            if (count(array_filter($row, fn ($v) => $v !== null && $v !== '')) === 0) {
                continue; // blank line
            }

            $result = $this->importRow($user, $network, $row, $columns);
            $stats[$result]++;

            if ($result === 'matched' || $result === 'unmatched') {
                $stats['imported']++;
            }
        }

        fclose($handle);

        return $stats;
    }

    /**
     * Manual single-entry path, used by the dashboard's "Add an entry" form.
     */
    public function createManual(User $user, array $data): Earning
    {
        $trackedLink = isset($data['tracked_link_id'])
            ? TrackedLink::where('user_id', $user->id)->find($data['tracked_link_id'])
            : null;

        return Earning::create([
            'user_id' => $user->id,
            'tracked_link_id' => $trackedLink?->id,
            'offer_id' => $trackedLink?->offer_id ?? $data['offer_id'] ?? null,
            'network' => $data['network'],
            'source' => 'manual',
            'amount_cents' => (int) round(((float) $data['amount']) * 100),
            'currency' => $data['currency'] ?? 'USD',
            'status' => $data['status'] ?? 'pending',
            'converted_at' => $data['converted_at'],
            'notes' => $data['notes'] ?? null,
        ]);
    }

    /**
     * @return array<string, int|null>
     */
    protected function detectColumns(array $header): array
    {
        $normalized = array_map(fn ($h) => strtolower(trim((string) $h)), $header);
        $columns = [];

        foreach (self::HEADER_ALIASES as $field => $aliases) {
            $columns[$field] = null;

            foreach ($normalized as $index => $label) {
                if (in_array($label, $aliases, true)) {
                    $columns[$field] = $index;

                    break;
                }
            }
        }

        return $columns;
    }

    /**
     * @return 'matched'|'unmatched'|'skipped'|'declined'
     */
    protected function importRow(User $user, string $network, array $row, array $columns): string
    {
        $rawStatus = $columns['status'] !== null ? strtolower(trim((string) ($row[$columns['status']] ?? ''))) : '';

        if (in_array($rawStatus, self::DECLINED_STATUSES, true)) {
            return 'declined';
        }

        $amountCents = $this->parseAmountCents($columns['amount'] !== null ? ($row[$columns['amount']] ?? null) : null);
        $convertedAt = $this->parseDate($columns['date'] !== null ? ($row[$columns['date']] ?? null) : null);

        if ($amountCents === null || $convertedAt === null) {
            return 'skipped';
        }

        $code = $columns['code'] !== null ? trim((string) ($row[$columns['code']] ?? '')) : '';
        $trackedLink = $code !== '' ? TrackedLink::where('user_id', $user->id)->where('code', $code)->first() : null;

        $externalRef = $columns['external_ref'] !== null ? trim((string) ($row[$columns['external_ref']] ?? '')) : '';

        $status = match (true) {
            in_array($rawStatus, self::PAID_STATUSES, true) => 'paid',
            in_array($rawStatus, self::APPROVED_STATUSES, true) => 'approved',
            default => 'pending',
        };

        $currency = $columns['currency'] !== null ? strtoupper(trim((string) ($row[$columns['currency']] ?? ''))) : '';

        try {
            Earning::create([
                'user_id' => $user->id,
                'tracked_link_id' => $trackedLink?->id,
                'offer_id' => $trackedLink?->offer_id,
                'network' => $network,
                'source' => 'csv_import',
                'external_ref' => $externalRef !== '' ? $externalRef : null,
                'amount_cents' => $amountCents,
                'currency' => $currency !== '' && strlen($currency) === 3 ? $currency : 'USD',
                'status' => $status,
                'converted_at' => $convertedAt,
            ]);
        } catch (Throwable $e) {
            // Unique(user_id, network, external_ref) collision — this exact
            // network transaction was already imported in a previous CSV.
            return 'skipped';
        }

        return $trackedLink ? 'matched' : 'unmatched';
    }

    protected function parseAmountCents(?string $raw): ?int
    {
        if ($raw === null || trim($raw) === '') {
            return null;
        }

        $cleaned = preg_replace('/[^0-9.\-]/', '', $raw);

        if ($cleaned === '' || $cleaned === null || ! is_numeric($cleaned)) {
            return null;
        }

        $value = (float) $cleaned;

        return $value > 0 ? (int) round($value * 100) : null;
    }

    protected function parseDate(?string $raw): ?string
    {
        if ($raw === null || trim($raw) === '') {
            return null;
        }

        try {
            return Carbon::parse(trim($raw))->toDateString();
        } catch (Throwable $e) {
            return null;
        }
    }
}
