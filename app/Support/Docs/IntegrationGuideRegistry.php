<?php

namespace App\Support\Docs;

use App\Filament\Pages\Documentation\IntegrationGuide;
use App\Models\AiEngineSetting;
use App\Models\GoogleOauthSetting;
use App\Models\LeadSourceSetting;
use App\Models\SiteSetting;
use App\Services\Blog\BlogFeed;
use App\Services\Settings\SettingsHealthChecker;
use App\Support\MobileApps;
use App\Support\SiteDomains;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Throwable;

/**
 * Everything the Integration Guides need besides their prose: the guide
 * metadata from config/integration_guides.php, a live status per guide
 * (read from SettingsHealthChecker — the same source Connections Health
 * uses — plus a few checks it doesn't cover, like the .env mail settings),
 * and every address the owner has to paste into a third-party console,
 * computed from this app's real routes so they're right on the live domain.
 */
class IntegrationGuideRegistry
{
    /**
     * Badge states, worst first — aggregation picks the first that applies.
     */
    public const STATES = [
        'failing' => 'Check failed',
        'not_set' => 'Not set up',
        'pending' => 'Awaiting approval',
        'configured' => 'Set up — not verified',
        'off' => 'Set up, switched off',
        'user' => 'Customers connect it',
        'working' => 'Working',
    ];

    /**
     * @var array<string, array<string, mixed>>|null
     */
    protected ?array $healthItems = null;

    /**
     * @return array<string, array<string, mixed>>
     */
    public function all(): array
    {
        $guides = [];

        foreach ((array) config('integration_guides.guides', []) as $key => $guide) {
            $guides[$key] = $guide + ['key' => $key];
        }

        return $guides;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, (array) config('integration_guides.guides', []));
    }

    /**
     * @return array<string, mixed>
     */
    public function get(string $key): array
    {
        return $this->all()[$key] ?? abort(404);
    }

    /**
     * Guides grouped into the launch-sequence phases, in config order, each
     * with a running step number across the whole checklist.
     *
     * @return array<string, array{title: string, description: string, guides: array<int, array<string, mixed>>}>
     */
    public function phases(): array
    {
        $phases = [];
        $number = 0;

        foreach ((array) config('integration_guides.phases', []) as $phaseKey => $phase) {
            $phases[$phaseKey] = $phase + ['guides' => []];
        }

        foreach ($this->all() as $guide) {
            $guide['number'] = ++$number;
            $phases[$guide['phase']]['guides'][] = $guide;
        }

        return $phases;
    }

    public function lastReviewed(): string
    {
        return (string) config('integration_guides.last_reviewed', 'October 2026');
    }

    public function guideUrl(string $key): string
    {
        return IntegrationGuide::getUrl(['guide' => $key]);
    }

    /**
     * Where the keys for a guide get pasted: a link to the admin (or
     * customer dashboard) page and/or the .env variable names.
     *
     * @return array{url: ?string, label: ?string, env: array<int, string>, user_side: bool}
     */
    public function adminTarget(string $key): array
    {
        $admin = (array) ($this->get($key)['admin'] ?? []);
        $route = $admin['route'] ?? null;

        return [
            'url' => $route && Route::has($route) ? route($route) : null,
            'label' => $admin['label'] ?? null,
            'env' => (array) ($admin['env'] ?? []),
            'user_side' => (bool) ($admin['user_side'] ?? false),
        ];
    }

    /**
     * Every URL a third-party console asks for, built from named routes.
     *
     * @return array<string, string>
     */
    public function urls(): array
    {
        $site = rtrim(SiteDomains::publicUrl(), '/');
        $blog = BlogFeed::baseUrl() ?: 'https://blog.'.$this->rootDomain();

        return [
            'site' => $site,
            'host' => (string) parse_url($site, PHP_URL_HOST),
            'root_domain' => $this->rootDomain(),
            'google_callback' => route('google.callback'),
            'gmail_callback' => route('email-connections.gmail.callback'),
            'youtube_callback' => route('social-connections.callback', ['provider' => 'youtube']),
            'site_analytics_callback' => route('google-site-analytics.callback'),
            'linkedin_callback' => route('social-connections.callback', ['provider' => 'linkedin']),
            'tiktok_callback' => route('social-connections.callback', ['provider' => 'tiktok']),
            'instagram_callback' => route('social-connections.callback', ['provider' => 'instagram']),
            'webhook_flutterwave' => route('webhooks.flutterwave'),
            'webhook_paystack' => route('webhooks.paystack'),
            'webhook_stripe' => route('webhooks.stripe'),
            'webhook_paypal' => route('webhooks.paypal'),
            'billing_callback' => route('billing.callback'),
            'privacy' => route('privacy'),
            'terms' => route('terms'),
            'cookie_policy' => route('cookie-policy'),
            'sitemap' => route('seo.sitemap'),
            'login' => route('login'),
            'email_sending' => route('email-connections.index'),
            'connected_accounts' => route('social-connections.index'),
            'extension' => route('extension.index'),
            'api_access' => route('api-access.index'),
            'api_base' => $site.'/api',
            'blog' => $blog,
            'blog_posts_json' => rtrim($blog, '/').'/wp-json/wp/v2/posts',
        ];
    }

    /**
     * Whether the computed URLs above can be trusted for the live site —
     * false on a local/test copy or a plain-http address, which Google,
     * Meta, TikTok and every payment gateway reject.
     */
    public function siteUrlLooksLive(): bool
    {
        $site = SiteDomains::publicUrl();
        $host = (string) parse_url($site, PHP_URL_HOST);

        return str_starts_with($site, 'https://')
            && ! in_array($host, ['localhost', '127.0.0.1'], true)
            && ! str_ends_with($host, '.test');
    }

    protected function rootDomain(): string
    {
        $host = (string) parse_url(SiteDomains::publicUrl(), PHP_URL_HOST);

        return preg_replace('/^www\./', '', $host) ?: 'affilstack.com';
    }

    /**
     * @return array{state: string, label: string, detail: ?string}
     */
    public function status(string $key): array
    {
        try {
            return $this->resolveStatus($this->get($key));
        } catch (Throwable) {
            return $this->make('not_set', 'Status unavailable right now.');
        }
    }

    /**
     * @param  array<string, mixed>  $guide
     * @return array{state: string, label: string, detail: ?string}
     */
    protected function resolveStatus(array $guide): array
    {
        $status = (array) ($guide['status'] ?? []);

        if (isset($status['health'])) {
            return $this->fromHealth((array) $status['health']);
        }

        return match ($status['resolver'] ?? null) {
            'mail' => $this->mailStatus(),
            'ai' => $this->aiStatus(),
            'blog' => $this->blogStatus(),
            'gmail_sending' => $this->gmailSendingStatus(),
            'gmail_reading' => $this->gmailReadingStatus(),
            'youtube' => $this->youtubeStatus(),
            'places' => filled(config('services.google_places.api_key'))
                ? $this->make('configured', 'GOOGLE_PLACES_API_KEY is set. Run a Local Leads search to confirm.')
                : $this->make('not_set', 'Add GOOGLE_PLACES_API_KEY to the server .env file.'),
            'lead_sources' => (function () {
                $settings = LeadSourceSetting::current();
                $on = array_keys(array_filter(['Apollo' => $settings->apolloReady(), 'Hunter' => $settings->hunterReady()]));

                return $on === []
                    ? $this->make('not_set', 'Optional. Lead finding works with Google Maps alone; add Apollo or Hunter for more leads with emails.')
                    : $this->make('configured', implode(' and ', $on).' switched on. Use the Test buttons on the Lead sources page to confirm.');
            })(),
            'chrome_store' => filled(config('extension.store_url'))
                ? $this->make('working', 'Store link set — the Browser Extension page shows "Add to Chrome".')
                : $this->make('not_set', 'Customers install from the zip until CHROME_EXTENSION_STORE_URL is set.'),
            'zapier' => filled(config('api_hub.zapier_invite_url'))
                ? $this->make('working', 'Invite link set — shown on customers\' API Access page.')
                : $this->make('not_set', 'Push the integration and set AFFILSTACK_ZAPIER_INVITE_URL.'),
            'mobile_apps' => $this->mobileAppsStatus(),
            'user_side' => $this->make('user', 'Nothing to set up here — each customer connects their own mailbox.'),
            default => $this->make('not_set', null),
        };
    }

    /**
     * Combines one or more SettingsHealthChecker items into one badge —
     * the worst state wins, with a "2 of 3" note when they differ.
     *
     * @param  array<int, string>  $keys
     * @return array{state: string, label: string, detail: ?string}
     */
    protected function fromHealth(array $keys): array
    {
        $items = $this->healthItems();
        $states = [];
        $details = [];

        foreach ($keys as $healthKey) {
            $item = $items[$healthKey] ?? null;

            if ($item === null) {
                continue;
            }

            $state = $this->healthState($item);
            $states[$healthKey] = $state;
            $details[] = $item['label'].': '.self::STATES[$state];
        }

        if ($states === []) {
            return $this->make('not_set', null);
        }

        $worst = collect(array_keys(self::STATES))->first(fn (string $state) => in_array($state, $states, true));

        if (count($states) > 1) {
            $working = count(array_filter($states, fn (string $state) => $state === 'working'));

            if ($worst === 'not_set' && $working + count(array_filter($states, fn (string $state) => in_array($state, ['configured', 'off', 'pending'], true))) > 0) {
                $worst = 'configured';
            }

            return $this->make($worst, implode(' · ', $details), $working === count($states) ? null : $working.' of '.count($states).' working');
        }

        $item = $items[array_key_first($states)];

        return $this->make($worst, $item['message'] ?? null);
    }

    /**
     * @param  array<string, mixed>  $item
     */
    protected function healthState(array $item): string
    {
        if (! $item['is_configured']) {
            return 'not_set';
        }

        if ($item['success'] === false) {
            return 'failing';
        }

        if (in_array($item['key'], ['tiktok', 'instagram'], true) && $item['success'] !== true) {
            return $item['is_enabled'] ? 'pending' : 'off';
        }

        if ($item['success'] === true) {
            return $item['is_enabled'] || $item['key'] === 'github_sync' ? 'working' : 'off';
        }

        return $item['is_enabled'] ? 'configured' : 'off';
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    protected function healthItems(): array
    {
        return $this->healthItems ??= collect(app(SettingsHealthChecker::class)->items())->keyBy('key')->all();
    }

    /**
     * @return array{state: string, label: string, detail: ?string}
     */
    protected function mailStatus(): array
    {
        $mailer = (string) config('mail.default');
        $host = (string) config('mail.mailers.smtp.host');

        if (in_array($mailer, ['log', 'array'], true)) {
            return $this->make('not_set', 'MAIL_MAILER is "'.$mailer.'" — emails are only written to the log, nobody receives them.');
        }

        if ($mailer === 'smtp' && in_array($host, ['', '127.0.0.1', 'localhost', 'mailpit'], true)) {
            return $this->make('not_set', 'MAIL_HOST still points at a local test server ('.($host ?: 'empty').').');
        }

        return $this->make('configured', 'Sending through '.($mailer === 'smtp' ? $host : $mailer).' from '.config('mail.from.address').'. Send yourself a password reset to confirm.');
    }

    /**
     * @return array{state: string, label: string, detail: ?string}
     */
    protected function aiStatus(): array
    {
        $settings = AiEngineSetting::current();
        $engine = $settings->text_engine === AiEngineSetting::ENGINE_ANTHROPIC ? 'anthropic' : 'openai';
        $hasEngine = $engine === 'anthropic' ? $settings->hasAnthropic() : $settings->hasOpenAi();
        $engineName = $engine === 'anthropic' ? 'Claude' : 'OpenAI';

        if (! $hasEngine) {
            return $this->make('not_set', 'Main engine is '.$engineName.', but its API key is missing.');
        }

        $verification = $settings->{$engine.'_verification_status'};
        $imageNote = $settings->hasOpenAi() ? '' : ' Images need an OpenAI key too.';

        return match (true) {
            $verification === 'success' => $this->make('working', $engineName.' writes the content and passed its last test.'.$imageNote),
            $verification !== null => $this->make('failing', $engineName.'\'s last test failed — open AI Engine and press "Test '.$engineName.'".'),
            default => $this->make('configured', $engineName.' key is set — press "Test '.$engineName.'" on the AI Engine page.'.$imageNote),
        };
    }

    /**
     * @return array{state: string, label: string, detail: ?string}
     */
    protected function blogStatus(): array
    {
        $base = BlogFeed::baseUrl();

        if (! $base) {
            return $this->make('not_set', 'No blog address saved yet.');
        }

        $cached = Cache::get(BlogFeed::CACHE_KEY);

        if (is_array($cached) && ($cached['posts'] ?? []) !== []) {
            return $this->make('working', $base.' — posts are showing on the homepage.');
        }

        return $this->make('configured', $base.' saved — press "Test blog connection" on the Homepage editor.');
    }

    /**
     * @return array{state: string, label: string, detail: ?string}
     */
    protected function gmailSendingStatus(): array
    {
        $settings = GoogleOauthSetting::current();

        if (! $settings->credential('client_id') || ! $settings->credential('client_secret')) {
            return $this->make('not_set', 'Set up Google Sign-in first — Gmail sending uses the same Google app.');
        }

        return $settings->gmail_sending_enabled
            ? $this->make('configured', '"Connect Gmail" is showing to customers. Make sure Google has verified the gmail.send scope.')
            : $this->make('off', 'The "Allow users to connect Gmail" toggle is off.');
    }

    /**
     * @return array{state: string, label: string, detail: ?string}
     */
    protected function gmailReadingStatus(): array
    {
        $settings = GoogleOauthSetting::current();

        return $settings->gmail_reading_enabled
            ? $this->make('configured', 'Inbox reading is ON — only correct once Google approved CASA for gmail.readonly.')
            : $this->make('off', 'Toggle is off (correct until CASA is approved). Customers use IMAP meanwhile.');
    }

    /**
     * @return array{state: string, label: string, detail: ?string}
     */
    protected function youtubeStatus(): array
    {
        $settings = GoogleOauthSetting::current();

        if (! $settings->credential('client_id')) {
            return $this->make('not_set', 'Set up Google Sign-in first — YouTube uses the same Google app.');
        }

        if (! $settings->youtube_publishing_enabled) {
            return $this->make('off', '"Allow users to connect YouTube" is off.');
        }

        return match ($settings->youtube_approval_status) {
            'approved' => $this->make('working', 'Enabled and marked approved.'),
            'pending' => $this->make('pending', 'Enabled — Google review pending.'),
            default => $this->make('configured', 'Enabled — verification/audit not submitted yet.'),
        };
    }

    /**
     * @return array{state: string, label: string, detail: ?string}
     */
    protected function mobileAppsStatus(): array
    {
        $links = array_filter([MobileApps::playStoreUrl(), MobileApps::appStoreUrl()]);

        return match (count($links)) {
            2 => $this->make('working', 'Both store links are live on the homepage.'),
            1 => $this->make('configured', 'One store link set.'),
            default => $this->make('not_set', SiteSetting::get('mobile_app_coming_soon') ? '"Coming soon" badges are showing.' : 'No store links yet.'),
        };
    }

    /**
     * @return array{state: string, label: string, detail: ?string}
     */
    protected function make(string $state, ?string $detail, ?string $label = null): array
    {
        return [
            'state' => $state,
            'label' => $label ? self::STATES[$state].' · '.$label : self::STATES[$state],
            'detail' => $detail,
        ];
    }
}
