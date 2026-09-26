<?php

namespace App\Services\Leads;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Core feature 7 (Phase 2): finds candidate local businesses for a
 * niche + location via the Google Places API, feeding AffilStack's
 * existing CRM (CrmContact already had `source`/`raw_data` columns from
 * Phase 1, unused until now) — the "no live web-browsing" limitation noted
 * on item 4 (competitor angle scanner) doesn't apply here, since this
 * calls a real, live third-party API rather than asking the AI provider to
 * guess.
 *
 * Talks to Google's HTTP API directly (Laravel's own HTTP client) rather
 * than a Google Maps SDK package, per the project's
 * no-new-dependencies-without-approval rule — the same approach already
 * used for the AI provider (OpenAIProvider) and the browser extension's
 * bearer-token auth.
 */
class GoogleMapsLeadService
{
    public function __construct(protected ?string $apiKey = null)
    {
        $this->apiKey ??= config('services.google_places.api_key');
    }

    /**
     * One Places API (New) "Text Search" call regardless of how many
     * results come back — no phone/website yet, see details() for why
     * that's a separate, per-place call made only for a place the user
     * imports. The field mask keeps this on the cheaper Text Search tier.
     *
     * @return array<int, array{place_id: string, name: string, address: string, rating: ?float, ratings_total: ?int, type: ?string, maps_url: string}>
     */
    public function search(string $niche, string $location): array
    {
        $this->ensureConfigured();

        $limit = (int) config('google_maps.results_limit');

        $response = $this->client('places.id,places.displayName,places.formattedAddress,places.rating,places.userRatingCount,places.primaryType,places.types,places.googleMapsUri')
            ->post((string) config('google_maps.search_endpoint'), [
                'textQuery' => trim("{$niche} in {$location}"),
                'pageSize' => max(1, min(20, $limit)),
            ]);

        if ($response->failed()) {
            throw $this->failure('Google Maps search failed', $response);
        }

        return collect($response->json('places') ?? [])
            ->filter(fn (mixed $place) => is_array($place) && ! empty($place['id']))
            ->take($limit)
            ->map(fn (array $place) => [
                'place_id' => (string) $place['id'],
                'name' => (string) data_get($place, 'displayName.text', 'Unnamed business'),
                'address' => (string) ($place['formattedAddress'] ?? ''),
                'rating' => isset($place['rating']) ? (float) $place['rating'] : null,
                'ratings_total' => isset($place['userRatingCount']) ? (int) $place['userRatingCount'] : null,
                'type' => $this->primaryType(array_values(array_filter([$place['primaryType'] ?? null, ...($place['types'] ?? [])]))),
                'maps_url' => (string) ($place['googleMapsUri'] ?? "https://www.google.com/maps/place/?q=place_id:{$place['id']}"),
            ])
            ->values()
            ->all();
    }

    /**
     * Phone/website for one place — fetched only when the user actually
     * imports it, so a 20-result search never costs 21 Places API calls.
     *
     * @return array{phone: ?string, website: ?string}
     */
    public function details(string $placeId): array
    {
        $this->ensureConfigured();

        $response = $this->client('nationalPhoneNumber,internationalPhoneNumber,websiteUri')
            ->get(rtrim((string) config('google_maps.details_endpoint'), '/').'/'.rawurlencode($placeId));

        if ($response->failed()) {
            throw $this->failure('Fetching business details failed', $response);
        }

        return [
            'phone' => $response->json('nationalPhoneNumber') ?? $response->json('internationalPhoneNumber'),
            'website' => $response->json('websiteUri'),
        ];
    }

    protected function ensureConfigured(): void
    {
        if (! $this->apiKey) {
            throw new GoogleMapsException('Google Maps isn\'t configured yet — add GOOGLE_PLACES_API_KEY to the environment.');
        }
    }

    /**
     * The key travels in a header rather than the query string, so it
     * never lands in a proxy or server access log.
     */
    protected function client(string $fieldMask): PendingRequest
    {
        return Http::timeout(15)
            ->acceptJson()
            ->withHeaders([
                'X-Goog-Api-Key' => (string) $this->apiKey,
                'X-Goog-FieldMask' => $fieldMask,
            ]);
    }

    /**
     * Google's raw error stays in the log; the customer gets a short,
     * actionable message rather than a JSON dump.
     */
    protected function failure(string $prefix, Response $response): GoogleMapsException
    {
        $reason = (string) ($response->json('error.message') ?? $response->reason());

        report(new GoogleMapsException("{$prefix}: HTTP {$response->status()} {$reason}"));

        return new GoogleMapsException($response->status() === 429
            ? 'Google Maps is busy right now — please try again in a minute.'
            : "{$prefix} — please try again, or contact support if it keeps happening.");
    }

    /**
     * @param  array<int, string>  $types
     */
    protected function primaryType(array $types): ?string
    {
        $type = collect($types)->first(fn (string $t) => ! in_array($t, ['point_of_interest', 'establishment'], true));

        return $type ? str_replace('_', ' ', $type) : null;
    }
}
