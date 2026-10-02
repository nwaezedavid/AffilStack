<?php

namespace App\Support\Analytics;

use App\Models\PageView;
use Illuminate\Support\Str;

/**
 * Agent 04: where a visit came from — UTM tags, advertising click ids and
 * the referrer — resolved once per landing so sessions and visitors can
 * carry first- and last-touch attribution. The channel reuses
 * PageView::classifySource() so the new insights and the existing Traffic
 * & Stats page always agree on what "Organic Search" or "Social" means.
 */
final class Attribution
{
    /**
     * Ad platforms' click-id query parameters, keyed by the platform label.
     *
     * @var array<string, string>
     */
    public const CLICK_IDS = [
        'gclid' => 'google',
        'gbraid' => 'google',
        'wbraid' => 'google',
        'fbclid' => 'meta',
        'ttclid' => 'tiktok',
        'msclkid' => 'microsoft',
        'li_fat_id' => 'linkedin',
    ];

    public function __construct(
        public readonly string $channel,
        public readonly ?string $source,
        public readonly ?string $medium,
        public readonly ?string $campaign,
        public readonly ?string $term,
        public readonly ?string $content,
        public readonly ?string $referrerHost,
        public readonly ?string $clickIdType,
        public readonly ?string $clickId,
    ) {}

    public static function resolve(?string $referrer, ?string $queryString, string $appHost): self
    {
        parse_str(ltrim((string) $queryString, '?'), $query);
        $query = array_change_key_case(is_array($query) ? $query : [], CASE_LOWER);

        $referrerHost = $referrer ? strtolower((string) parse_url($referrer, PHP_URL_HOST)) : null;
        $referrerHost = $referrerHost ?: null;
        $isInternal = $referrerHost && ($referrerHost === strtolower($appHost) || str_ends_with($referrerHost, '.'.strtolower($appHost)));

        $clickIdType = null;
        $clickId = null;
        foreach (self::CLICK_IDS as $param => $platform) {
            if (! empty($query[$param]) && is_string($query[$param])) {
                $clickIdType = $platform;
                $clickId = Str::limit($query[$param], 255, '');
                break;
            }
        }

        $channel = Str::limit(PageView::classifySource($referrer, (string) $queryString, $appHost), 40, '');

        // A click id without UTM tags is still paid traffic from that
        // platform — label it so instead of "Referral"/"Direct".
        if ($clickIdType && empty($query['utm_medium']) && empty($query['utm_source'])) {
            $channel = 'Paid';
        }

        $source = self::clean($query['utm_source'] ?? null)
            ?? ($clickIdType ?: ($isInternal ? null : $referrerHost))
            ?? ($channel === 'Direct' ? '(direct)' : null);

        return new self(
            channel: $channel,
            source: $source,
            medium: self::clean($query['utm_medium'] ?? null) ?? ($clickIdType ? 'cpc' : ($referrerHost && ! $isInternal ? 'referral' : ($channel === 'Direct' ? '(none)' : null))),
            campaign: self::clean($query['utm_campaign'] ?? null, 150),
            term: self::clean($query['utm_term'] ?? null, 150),
            content: self::clean($query['utm_content'] ?? null, 150),
            referrerHost: $isInternal ? null : ($referrerHost ? Str::limit($referrerHost, 255, '') : null),
            clickIdType: $clickIdType,
            clickId: $clickId,
        );
    }

    /**
     * Does this landing carry an explicit campaign signal? A new campaign
     * mid-visit starts a new session (same rule as Google Analytics).
     */
    public function hasCampaign(): bool
    {
        return $this->campaign !== null || $this->clickIdType !== null || ($this->source !== null && $this->medium !== null && ! in_array($this->channel, ['Direct', 'Internal', 'Referral', 'Organic Search', 'Social'], true));
    }

    /**
     * Direct and internal navigation never overwrite last-touch — the
     * standard "last non-direct click" model.
     */
    public function isNonDirect(): bool
    {
        return ! in_array($this->channel, ['Direct', 'Internal'], true);
    }

    protected static function clean(mixed $value, int $limit = 100): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim(strip_tags($value));

        return $value === '' ? null : Str::limit(Str::lower($value), $limit, '');
    }
}
