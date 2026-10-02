<?php

namespace App\Support;

use App\Models\Offer;
use App\Models\User;

/**
 * Reads an offer's research_data for display. The AI contract
 * (OfferResearchService) promises a shape, but older offers predate the
 * extended keys and a model can still return a string where a list was
 * asked for — every accessor here tolerates both, so a view never has to.
 */
class ResearchPresenter
{
    public function __construct(protected Offer $offer, protected ?User $viewer = null) {}

    /**
     * @return array<string, mixed>
     */
    protected function data(): array
    {
        return is_array($this->offer->research_data) ? $this->offer->research_data : [];
    }

    public function overview(): ?string
    {
        return $this->string('product_overview') ?? $this->offer->ideal_customer_summary;
    }

    public function idealCustomer(): ?string
    {
        return $this->offer->ideal_customer_summary ?: null;
    }

    /**
     * @return array{individuals: array<int, string>, businesses: array<int, string>}
     */
    public function whoNeedsIt(): array
    {
        $raw = $this->data()['who_needs_it'] ?? [];

        if (! is_array($raw) || array_is_list($raw)) {
            return ['individuals' => $this->stringList($raw), 'businesses' => []];
        }

        return [
            'individuals' => $this->stringList($raw['individuals'] ?? []),
            'businesses' => $this->stringList($raw['businesses'] ?? []),
        ];
    }

    /**
     * @return array<int, array{name: string, description: string}>
     */
    public function personas(): array
    {
        return collect((array) ($this->data()['buyer_personas'] ?? []))
            ->map(function ($persona) {
                if (is_string($persona)) {
                    return ['name' => '', 'description' => $persona];
                }

                if (! is_array($persona)) {
                    return null;
                }

                return [
                    'name' => (string) ($persona['name'] ?? $persona['title'] ?? ''),
                    'description' => (string) ($persona['description'] ?? $persona['summary'] ?? ''),
                ];
            })
            ->filter(fn ($persona) => $persona && ($persona['name'] !== '' || $persona['description'] !== ''))
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public function painPoints(): array
    {
        return $this->stringList($this->data()['pain_points'] ?? []);
    }

    /**
     * @return array<int, string>
     */
    public function whereToFind(): array
    {
        $list = $this->stringList($this->data()['where_to_find'] ?? []);

        if ($list !== []) {
            return $list;
        }

        return $this->stringList(preg_split('/\R/', (string) $this->offer->where_to_find) ?: []);
    }

    /**
     * @return array<int, array{keyword: string, why: string}>
     */
    public function mapsKeywords(): array
    {
        $keywords = collect((array) ($this->data()['google_maps_keywords'] ?? []))
            ->map(function ($item) {
                if (is_string($item)) {
                    return ['keyword' => trim($item), 'why' => ''];
                }

                return is_array($item)
                    ? ['keyword' => trim((string) ($item['keyword'] ?? $item['term'] ?? '')), 'why' => trim((string) ($item['why'] ?? $item['reason'] ?? ''))]
                    : null;
            })
            ->filter(fn ($item) => $item && $item['keyword'] !== '')
            ->values();

        if ($keywords->isEmpty() && $this->offer->suggested_maps_niche) {
            $keywords->push(['keyword' => $this->offer->suggested_maps_niche, 'why' => '']);
        }

        return $keywords->all();
    }

    /**
     * Cities to start a Maps search in — the AI's suggestions, then the
     * legacy single suggestion, then the viewer's own profile city.
     *
     * @return array<int, string>
     */
    public function locations(): array
    {
        $locations = collect($this->stringList($this->data()['suggested_locations'] ?? []));

        if ($this->offer->suggested_maps_location) {
            $locations->push($this->offer->suggested_maps_location);
        }

        if ($home = $this->viewerHome()) {
            $locations->push($home);
        }

        return $locations->unique(fn (string $location) => mb_strtolower($location))->values()->all();
    }

    public function viewerHome(): ?string
    {
        if (! $this->viewer) {
            return null;
        }

        $parts = array_filter([$this->viewer->city, $this->viewer->country]);

        return $parts === [] ? null : implode(', ', $parts);
    }

    /**
     * @return array{people: array<int, string>, companies: array<int, string>}
     */
    public function linkedinKeywords(): array
    {
        $raw = $this->data()['linkedin_keywords'] ?? [];

        if (! is_array($raw) || array_is_list($raw)) {
            return ['people' => $this->stringList($raw), 'companies' => []];
        }

        return [
            'people' => $this->stringList($raw['people'] ?? []),
            'companies' => $this->stringList($raw['companies'] ?? []),
        ];
    }

    /**
     * @return array<int, string>
     */
    public function linkedinTips(): array
    {
        return $this->stringList($this->data()['linkedin_search_tips'] ?? []);
    }

    public function channelReason(): ?string
    {
        return $this->string('recommended_channel_reason');
    }

    public function secondaryChannel(): ?string
    {
        return $this->string('secondary_channel');
    }

    /**
     * Whether this offer was researched before the extended contract
     * (overview, Maps/LinkedIn keywords…) existed.
     */
    public function isLegacy(): bool
    {
        return $this->offer->status === 'ready'
            && ! array_key_exists('google_maps_keywords', $this->data())
            && ! array_key_exists('linkedin_keywords', $this->data());
    }

    public static function channelLabel(?string $channel): string
    {
        return match ($channel) {
            'linkedin' => 'LinkedIn',
            'blog' => 'Blog / Medium article',
            'youtube' => 'YouTube',
            'ugc' => 'UGC video',
            'pinterest' => 'Pinterest',
            'x' => 'X (Twitter)',
            'tiktok' => 'TikTok',
            'google_maps' => 'Local businesses on Google Maps',
            default => ucfirst(str_replace('_', ' ', (string) $channel)),
        };
    }

    public static function mapsUrl(string $keyword, ?string $location = null): string
    {
        $query = $location ? "{$keyword} in {$location}" : $keyword;

        return 'https://www.google.com/maps/search/'.str_replace('%20', '+', rawurlencode($query));
    }

    /**
     * @param  'people'|'companies'  $type
     */
    public static function linkedinUrl(string $type, string $keyword): string
    {
        return 'https://www.linkedin.com/search/results/'.$type.'/?keywords='.rawurlencode($keyword);
    }

    protected function string(string $key): ?string
    {
        $value = $this->data()[$key] ?? null;

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    /**
     * @return array<int, string>
     */
    protected function stringList(mixed $value): array
    {
        if (is_string($value)) {
            $value = preg_split('/\R/', $value) ?: [];
        }

        return collect(is_array($value) ? $value : [])
            ->map(fn ($item) => is_scalar($item) ? trim((string) $item) : '')
            ->map(fn (string $item) => ltrim($item, "-•* \t"))
            ->filter()
            ->values()
            ->all();
    }
}
