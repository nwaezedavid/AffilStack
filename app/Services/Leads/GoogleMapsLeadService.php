<?php

namespace App\Services\Leads;

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
     * One Places API "Text Search" call regardless of how many results
     * come back — no phone/website yet, see details() for why that's a
     * separate, per-place call made only for a place the user imports.
     *
     * @return array<int, array{place_id: string, name: string, address: string, rating: ?float, ratings_total: ?int, type: ?string, maps_url: string}>
     */
    public function search(string $niche, string $location): array
    {
        if (! $this->apiKey) {
            throw new GoogleMapsException('Google Maps isn\'t configured yet — add GOOGLE_PLACES_API_KEY to the environment.');
        }

        $response = Http::timeout(15)->get(config('google_maps.search_endpoint'), [
            'query' => trim("{$niche} in {$location}"),
            'key' => $this->apiKey,
        ]);

        if ($response->failed()) {
            throw new GoogleMapsException('Google Maps search failed: '.$response->body());
        }

        $body = $response->json();
        $status = $body['status'] ?? 'UNKNOWN_ERROR';

        if ($status === 'ZERO_RESULTS') {
            return [];
        }

        if ($status !== 'OK') {
            throw new GoogleMapsException('Google Maps search failed: '.($body['error_message'] ?? $status));
        }

        $limit = (int) config('google_maps.results_limit');

        return collect($body['results'] ?? [])
            ->take($limit)
            ->map(fn (array $place) => [
                'place_id' => $place['place_id'],
                'name' => $place['name'] ?? 'Unnamed business',
                'address' => $place['formatted_address'] ?? '',
                'rating' => $place['rating'] ?? null,
                'ratings_total' => $place['user_ratings_total'] ?? null,
                'type' => $this->primaryType($place['types'] ?? []),
                'maps_url' => "https://www.google.com/maps/place/?q=place_id:{$place['place_id']}",
            ])
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
        if (! $this->apiKey) {
            throw new GoogleMapsException('Google Maps isn\'t configured yet — add GOOGLE_PLACES_API_KEY to the environment.');
        }

        $response = Http::timeout(15)->get(config('google_maps.details_endpoint'), [
            'place_id' => $placeId,
            'fields' => 'formatted_phone_number,website',
            'key' => $this->apiKey,
        ]);

        if ($response->failed()) {
            throw new GoogleMapsException('Fetching business details failed: '.$response->body());
        }

        $body = $response->json();

        if (($body['status'] ?? 'UNKNOWN_ERROR') !== 'OK') {
            throw new GoogleMapsException('Fetching business details failed: '.($body['error_message'] ?? $body['status'] ?? 'unknown error'));
        }

        return [
            'phone' => $body['result']['formatted_phone_number'] ?? null,
            'website' => $body['result']['website'] ?? null,
        ];
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
