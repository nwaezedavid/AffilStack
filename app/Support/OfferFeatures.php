<?php

namespace App\Support;

use App\Models\Generation;
use App\Models\Offer;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Every built-in feature a researched product can be promoted with — the
 * single source of truth for the product hub's feature tiles, each
 * dedicated feature page (route offers.feature), and their gating.
 *
 * `channel` is the Plan::$channels key the feature's controller already
 * enforces via User::canUseChannel() (null = not plan-gated today);
 * `owner_only` mirrors config('agency.seat_allowed_routes') — the features
 * whose actions a team seat can't reach (CRM-dependent email nurture and
 * Google Maps lead-finding).
 */
class OfferFeatures
{
    /**
     * @return array<string, array{
     *     label: string, short: string, icon: string, tone: string, channel: ?string,
     *     owner_only: bool, modules: array<int, string>, cost_keys: array<int, string>,
     *     tagline: string, description: string, outcome: string, steps: array<int, string>
     * }>
     */
    public static function all(): array
    {
        return [
            'ugc' => [
                'label' => 'UGC video',
                'short' => 'Generate UGC video',
                'icon' => 'video-camera',
                'tone' => 'rose',
                'channel' => 'ugc',
                'owner_only' => false,
                'modules' => ['ugc_angles', 'ugc_content', 'ugc_video'],
                'cost_keys' => ['ugc_angles', 'ugc_content', 'ugc_video'],
                'tagline' => 'A talking-avatar video that sells this product — no camera needed.',
                'description' => 'Pick a proven creative angle, choose the AI presenter and voice you like, let AI write the script, tweak it in your own words, then render a vertical video ready for TikTok, Reels and Shorts.',
                'outcome' => 'A finished 9:16 video plus captions and hashtags for every platform.',
                'steps' => [
                    'Get 5 scroll-stopping angles and pick the one that fits you.',
                    'Choose your character — an AI avatar and a voice.',
                    'Generate the script, then edit any line you like.',
                    'Create the video and download or publish it.',
                ],
            ],
            'youtube' => [
                'label' => 'YouTube',
                'short' => 'YouTube video script',
                'icon' => 'play-circle',
                'tone' => 'red',
                'channel' => 'youtube',
                'owner_only' => false,
                'modules' => ['youtube_script', 'youtube_metadata'],
                'cost_keys' => ['youtube_script', 'youtube_metadata'],
                'tagline' => 'A full review/tutorial script, then a title, description and tags to rank it.',
                'description' => 'AI writes a beat-by-beat YouTube script for this product with a strong hook and B-roll ideas, then writes the title options, description (with your link and disclosure), keywords and tags to match.',
                'outcome' => 'A record-ready script plus upload-ready metadata.',
                'steps' => [
                    'Generate the video script.',
                    'Record it (or read it on camera with B-roll).',
                    'Generate the title, description and tags.',
                    'Upload and add it to your content calendar.',
                ],
            ],
            'blog' => [
                'label' => 'Blog article',
                'short' => 'Write a blog article',
                'icon' => 'document-text',
                'tone' => 'brand',
                'channel' => null,
                'owner_only' => false,
                'modules' => ['blog_article'],
                'cost_keys' => ['blog_article'],
                'tagline' => 'An SEO article that ranks on Google and converts readers into buyers.',
                'description' => 'Give it a keyword (or let AI choose) and get a long-form, SEO-structured article with a meta description, your cloaked affiliate link and the right disclosure already in place.',
                'outcome' => 'A publish-ready article for your blog, Medium or LinkedIn.',
                'steps' => [
                    'Type the keyword you want to rank for (optional).',
                    'Generate the article — it takes about a minute.',
                    'Copy it into your blog or Medium and publish.',
                    'Schedule it on your content calendar.',
                ],
            ],
            'linkedin' => [
                'label' => 'LinkedIn content',
                'short' => 'LinkedIn posts & DMs',
                'icon' => 'briefcase',
                'tone' => 'sky',
                'channel' => null,
                'owner_only' => false,
                'modules' => ['linkedin_keywords', 'linkedin_dm_sequence', 'linkedin_post', 'linkedin_article'],
                'cost_keys' => ['linkedin_keywords', 'linkedin_dm_sequence', 'linkedin_post', 'linkedin_article'],
                'tagline' => 'Find the right people on LinkedIn and start real conversations.',
                'description' => 'Get the exact search keywords and Boolean strings to find buyers, a soft DM sequence, a post and a long-form article — all written for this product. You send everything yourself; AffilStack never auto-messages anyone.',
                'outcome' => 'Search terms, a DM sequence, a post and an article you can use today.',
                'steps' => [
                    'Generate search keywords and find buyers on LinkedIn.',
                    'Generate a DM sequence and connect with them.',
                    'Post content so they see you as the expert.',
                    'Use the reply assistant when they answer.',
                ],
            ],
            'x' => [
                'label' => 'X thread',
                'short' => 'X (Twitter) thread',
                'icon' => 'chat-bubble-bottom-center-text',
                'tone' => 'slate',
                'channel' => 'x',
                'owner_only' => false,
                'modules' => ['x_thread'],
                'cost_keys' => ['x_thread'],
                'tagline' => 'A value-packed thread with hooks to A/B test.',
                'description' => 'A complete X thread that teaches before it sells, the disclosure on the first post, alternative opening hooks to test and the best time to post.',
                'outcome' => 'A ready-to-post thread and three alternative hooks.',
                'steps' => [
                    'Generate the thread.',
                    'Pick the hook you like best.',
                    'Post it and pin it to your profile.',
                ],
            ],
            'tiktok' => [
                'label' => 'TikTok',
                'short' => 'TikTok video package',
                'icon' => 'musical-note',
                'tone' => 'fuchsia',
                'channel' => 'tiktok',
                'owner_only' => false,
                'modules' => ['tiktok_video'],
                'cost_keys' => ['tiktok_video'],
                'tagline' => 'Script, on-screen text cues and caption in one pass.',
                'description' => 'A short-form script you can film on your phone, timed on-screen text cues, a caption with your link and the hashtags to reach buyers.',
                'outcome' => 'Everything you need to film and post one TikTok.',
                'steps' => [
                    'Generate the video package.',
                    'Film it on your phone using the on-screen cues.',
                    'Post with the caption and hashtags.',
                ],
            ],
            'pinterest' => [
                'label' => 'Pinterest pins',
                'short' => 'Pinterest pins',
                'icon' => 'photo',
                'tone' => 'red',
                'channel' => 'pinterest',
                'owner_only' => false,
                'modules' => ['pinterest_pin'],
                'cost_keys' => ['pinterest_pin'],
                'tagline' => 'Three pin variants that drive evergreen clicks.',
                'description' => 'Three pins to test — each with a title, keyword-rich description, an image prompt for Canva/AI and alt text — plus the board to pin them to.',
                'outcome' => 'Three pins ready to design and schedule.',
                'steps' => [
                    'Generate the pins.',
                    'Create the images in Canva using the image prompts.',
                    'Pin them to the suggested board.',
                ],
            ],
            'nurture' => [
                'label' => 'Email nurture',
                'short' => 'Email nurture sequence',
                'icon' => 'envelope-open',
                'tone' => 'emerald',
                'channel' => null,
                'owner_only' => true,
                'modules' => ['email_nurture'],
                'cost_keys' => ['email_nurture'],
                'tagline' => 'A 5-email sequence written for one real prospect.',
                'description' => 'Pick a contact from your CRM and AI writes five emails personalised to them — warm intro, value, proof, offer and follow-up — which you can send from your connected inbox.',
                'outcome' => 'A personalised 5-email sequence you can send in one click per step.',
                'steps' => [
                    'Add the prospect to your CRM (the Chrome extension does it for you).',
                    'Pick them below and generate the sequence.',
                    'Send email 1 now and follow the suggested timing.',
                ],
            ],
            'competitor' => [
                'label' => 'Competitor angles',
                'short' => 'Scan competitor angles',
                'icon' => 'eye',
                'tone' => 'amber',
                'channel' => null,
                'owner_only' => false,
                'modules' => ['competitor_angles'],
                'cost_keys' => ['competitor_angles'],
                'tagline' => 'See which angles other affiliates overuse — and where the gap is.',
                'description' => 'AI scans the angles other affiliates typically use for this product, rates how saturated each one is and suggests a fresh angle nobody is using yet.',
                'outcome' => 'A saturation map and a differentiated angle to use everywhere.',
                'steps' => [
                    'Run the scan.',
                    'Avoid the high-saturation angles.',
                    'Use the differentiation opportunity in your next piece of content.',
                ],
            ],
            'maps' => [
                'label' => 'Local leads',
                'short' => 'Find local business leads',
                'icon' => 'map-pin',
                'tone' => 'teal',
                'channel' => 'google_maps',
                'owner_only' => true,
                'modules' => [],
                'cost_keys' => [],
                'tagline' => 'Find local businesses on Google Maps that need this product.',
                'description' => 'Use the Google Maps keywords from your research to find local businesses, save them to this product\'s CRM list with the Chrome extension (or the built-in Local Leads search), then email the whole list with one AI campaign.',
                'outcome' => 'A list of real local prospects and a campaign sent to them.',
                'steps' => [
                    'Copy a Google Maps keyword (or open it in Maps).',
                    'Click "Add to CRM" on each business with the Chrome extension.',
                    'Click "Create Campaign" to email the whole list.',
                ],
            ],
            'localize' => [
                'label' => 'Localization',
                'short' => 'Localize for a market',
                'icon' => 'globe-europe-africa',
                'tone' => 'violet',
                'channel' => null,
                'owner_only' => false,
                'modules' => [],
                'cost_keys' => ['localization'],
                'tagline' => 'Adapt any finished piece for Nigeria, the UK, Kenya and more.',
                'description' => 'Take content you already generated for this product and rewrite it for another market — local spelling, currency, cultural references and the right disclosure wording.',
                'outcome' => 'Market-ready copies of your best content.',
                'steps' => [
                    'Generate content in any other feature first.',
                    'Pick the piece and the market below.',
                    'Publish the localized copy to that audience.',
                ],
            ],
        ];
    }

    public static function exists(string $key): bool
    {
        return array_key_exists($key, static::all());
    }

    /**
     * @return array<string, mixed>
     */
    public static function get(string $key): array
    {
        return static::all()[$key];
    }

    /**
     * The feature page a generation module belongs to.
     */
    public static function keyForModule(string $module): ?string
    {
        foreach (static::all() as $key => $feature) {
            if (in_array($module, $feature['modules'], true)) {
                return $key;
            }
        }

        return null;
    }

    /**
     * Where a finished generation lives — its feature page, or the product
     * hub for a module without one. Used by every generation job's
     * "your content is ready" notification link.
     */
    public static function urlForGeneration(Generation $generation): string
    {
        $key = static::keyForModule((string) $generation->module);

        return $key
            ? route('offers.feature', [$generation->offer_id, $key])
            : route('offers.show', $generation->offer_id);
    }

    /**
     * The feature page for one of the research's recommendable channels.
     */
    public static function keyForChannel(?string $channel): ?string
    {
        return match ($channel) {
            'google_maps' => 'maps',
            'blog', 'linkedin', 'youtube', 'ugc', 'pinterest', 'x', 'tiktok' => $channel,
            default => null,
        };
    }

    /**
     * Credit cost shown on a feature's tile — the cheapest action on it.
     */
    public static function startingCost(string $key): ?int
    {
        $costs = collect(static::get($key)['cost_keys'])
            ->map(fn (string $costKey) => config("credits.costs.{$costKey}"))
            ->filter();

        return $costs->isEmpty() ? null : (int) $costs->min();
    }

    /**
     * Whether $user's plan includes the feature.
     */
    public static function planAllows(User $user, string $key): bool
    {
        $channel = static::get($key)['channel'];

        return $channel === null || $user->canUseChannel($channel);
    }

    /**
     * Whether $user may act on the feature at all given team-seat rules.
     */
    public static function seatAllows(User $user, string $key): bool
    {
        return ! ($user->isSeat() && static::get($key)['owner_only']);
    }

    /**
     * Every feature as a tile for $offer, with its live state for $user.
     *
     * @return Collection<string, array<string, mixed>>
     */
    public static function tilesFor(User $user, Offer $offer): Collection
    {
        $generations = $offer->relationLoaded('generations') ? $offer->generations : $offer->generations()->get();
        $secondary = $offer->research_data['secondary_channel'] ?? null;

        return collect(static::all())->map(function (array $feature, string $key) use ($user, $offer, $generations, $secondary) {
            $count = $key === 'localize'
                ? $generations->whereNotNull('target_market')->count()
                : $generations->whereIn('module', $feature['modules'])->where('status', 'completed')->count();

            return $feature + [
                'key' => $key,
                'url' => route('offers.feature', [$offer, $key]),
                'cost' => static::startingCost($key),
                'count' => $count,
                'plan_locked' => ! static::planAllows($user, $key),
                'seat_locked' => ! static::seatAllows($user, $key),
                'recommended' => static::keyForChannel($offer->recommended_channel) === $key,
                'alternative' => static::keyForChannel($secondary) === $key,
            ];
        });
    }

    /**
     * Tailwind classes for each tone, written out in full so the compiler
     * sees them.
     *
     * @return array{icon: string, ring: string, glow: string, hero: string, soft: string, text: string}
     */
    public static function tone(string $tone): array
    {
        return match ($tone) {
            'rose' => ['icon' => 'bg-gradient-to-br from-rose-500 to-pink-600 text-white', 'ring' => 'hover:ring-rose-300', 'glow' => 'from-rose-50', 'hero' => 'rose', 'soft' => 'bg-rose-50 text-rose-700', 'text' => 'text-rose-600'],
            'red' => ['icon' => 'bg-gradient-to-br from-red-500 to-orange-500 text-white', 'ring' => 'hover:ring-red-300', 'glow' => 'from-red-50', 'hero' => 'rose', 'soft' => 'bg-red-50 text-red-700', 'text' => 'text-red-600'],
            'sky' => ['icon' => 'bg-gradient-to-br from-sky-500 to-blue-700 text-white', 'ring' => 'hover:ring-sky-300', 'glow' => 'from-sky-50', 'hero' => 'navy', 'soft' => 'bg-sky-50 text-sky-700', 'text' => 'text-sky-600'],
            'slate' => ['icon' => 'bg-gradient-to-br from-slate-700 to-slate-950 text-white', 'ring' => 'hover:ring-slate-300', 'glow' => 'from-slate-100', 'hero' => 'navy', 'soft' => 'bg-slate-100 text-slate-700', 'text' => 'text-slate-700'],
            'fuchsia' => ['icon' => 'bg-gradient-to-br from-fuchsia-500 to-violet-600 text-white', 'ring' => 'hover:ring-fuchsia-300', 'glow' => 'from-fuchsia-50', 'hero' => 'violet', 'soft' => 'bg-fuchsia-50 text-fuchsia-700', 'text' => 'text-fuchsia-600'],
            'emerald' => ['icon' => 'bg-gradient-to-br from-emerald-500 to-teal-600 text-white', 'ring' => 'hover:ring-emerald-300', 'glow' => 'from-emerald-50', 'hero' => 'emerald', 'soft' => 'bg-emerald-50 text-emerald-700', 'text' => 'text-emerald-600'],
            'amber' => ['icon' => 'bg-gradient-to-br from-amber-400 to-orange-500 text-white', 'ring' => 'hover:ring-amber-300', 'glow' => 'from-amber-50', 'hero' => 'gold', 'soft' => 'bg-amber-50 text-amber-700', 'text' => 'text-amber-600'],
            'teal' => ['icon' => 'bg-gradient-to-br from-teal-500 to-cyan-600 text-white', 'ring' => 'hover:ring-teal-300', 'glow' => 'from-teal-50', 'hero' => 'emerald', 'soft' => 'bg-teal-50 text-teal-700', 'text' => 'text-teal-600'],
            'violet' => ['icon' => 'bg-gradient-to-br from-violet-500 to-indigo-600 text-white', 'ring' => 'hover:ring-violet-300', 'glow' => 'from-violet-50', 'hero' => 'violet', 'soft' => 'bg-violet-50 text-violet-700', 'text' => 'text-violet-600'],
            default => ['icon' => 'bg-gradient-to-br from-brand-500 to-navy-800 text-white', 'ring' => 'hover:ring-brand-100', 'glow' => 'from-brand-50', 'hero' => 'navy', 'soft' => 'bg-brand-50 text-brand-700', 'text' => 'text-brand-600'],
        };
    }
}
