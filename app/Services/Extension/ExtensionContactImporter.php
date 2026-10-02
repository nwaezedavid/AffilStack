<?php

namespace App\Services\Extension;

use App\Models\CrmContact;
use App\Models\CrmList;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Files businesses captured from Google Maps (and people/companies captured
 * from LinkedIn) by the Chrome extension into the CRM — see
 * ExtensionProspectingController.
 *
 * Every contact is deduplicated per user — by Google place id / CID /
 * feature id, LinkedIn URL, phone, website and email — so clicking "Add to
 * CRM" twice, or adding a business Lead Finder already imported, never
 * creates a second contact. Existing and new contacts alike are filed into
 * the chosen list through CrmList::addContact(), which fires
 * CrmContactAddedToList for genuinely new members so the list's campaign
 * autopilot picks them up.
 *
 * Partial success, like the general API's bulk endpoint: one malformed item
 * never blocks the rest of the batch.
 */
class ExtensionContactImporter
{
    /**
     * Hosts many unrelated businesses share (a Facebook page, a Linktree…),
     * where the path — not the host — identifies the business.
     *
     * @var array<int, string>
     */
    protected const SHARED_HOSTS = [
        'facebook.com', 'm.facebook.com', 'instagram.com', 'linktr.ee', 'wa.me', 'api.whatsapp.com',
        'business.site', 'sites.google.com', 'linkedin.com', 'twitter.com', 'x.com', 'tiktok.com',
        'youtube.com', 'bit.ly', 'g.page', 'google.com', 'maps.google.com', 'beacons.ai', 'taplink.cc',
    ];

    /**
     * Index of the user's existing contacts, keyed by dedupe key type.
     *
     * @var array<string, array<string, CrmContact>>
     */
    protected array $index = [];

    /**
     * Validation rules for one captured contact.
     *
     * @return array<string, array<int, mixed>|string>
     */
    public static function itemRules(): array
    {
        return [
            'source' => 'nullable|string|in:google_maps,linkedin',
            'name' => 'required|string|max:255',
            'category' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:500',
            'phone' => 'nullable|string|max:60',
            'website' => 'nullable|string|max:2048',
            'email' => 'nullable|email|max:255',
            'rating' => 'nullable|numeric|min:0|max:5',
            'review_count' => 'nullable|integer|min:0',
            'maps_url' => 'nullable|string|max:2048',
            'place_id' => ['nullable', 'string', 'max:255', 'regex:/^[A-Za-z0-9_-]+$/'],
            'cid' => ['nullable', 'string', 'max:40', 'regex:/^\d+$/'],
            'ftid' => ['nullable', 'string', 'max:100', 'regex:/^0x[0-9a-fA-F]+:0x[0-9a-fA-F]+$/'],
            'linkedin_url' => 'nullable|string|max:2048',
            'title' => 'nullable|string|max:255',
            'company' => 'nullable|string|max:255',
            'location' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:2000',
        ];
    }

    /**
     * @param  array<int, mixed>  $items
     * @return array{
     *     results: array<int, array<string, mixed>>,
     *     created: int, duplicates: int, failed: int, added_to_list: int, limit_reached: bool
     * }
     */
    public function import(User $user, CrmList $list, array $items): array
    {
        $this->buildIndex($user);

        $limit = (int) ($user->activeSubscription?->plan?->contact_limit ?? 0);
        $remaining = $limit > 0 ? max(0, $limit - $user->crmContacts()->count()) : null;

        $summary = ['results' => [], 'created' => 0, 'duplicates' => 0, 'failed' => 0, 'added_to_list' => 0, 'limit_reached' => false];

        foreach (array_values($items) as $index => $item) {
            $validator = Validator::make(is_array($item) ? $item : [], static::itemRules());

            if ($validator->fails()) {
                $summary['failed']++;
                $summary['results'][] = ['index' => $index, 'status' => 'failed', 'errors' => $validator->errors()->toArray()];

                continue;
            }

            $data = $this->normalise($validator->validated());
            [$existing, $matchedBy] = $this->findExisting($data);

            if ($existing) {
                $added = $list->addContact($existing);
                $summary['duplicates']++;
                $summary['added_to_list'] += $added ? 1 : 0;
                $summary['results'][] = [
                    'index' => $index,
                    'status' => 'duplicate',
                    'matched_by' => $matchedBy,
                    'contact_id' => $existing->id,
                    'name' => $existing->name,
                    'added_to_list' => $added,
                ];

                continue;
            }

            if ($remaining !== null && $remaining <= 0) {
                $summary['limit_reached'] = true;
                $summary['failed']++;
                $summary['results'][] = [
                    'index' => $index,
                    'status' => 'limit_reached',
                    'errors' => ['limit' => ["You've reached your plan's CRM contact limit."]],
                ];

                continue;
            }

            $contact = CrmContact::create($this->attributes($user, $data));
            $this->remember($contact);

            if ($remaining !== null) {
                $remaining--;
            }

            $added = $list->addContact($contact);
            $summary['created']++;
            $summary['added_to_list'] += $added ? 1 : 0;
            $summary['results'][] = [
                'index' => $index,
                'status' => 'created',
                'contact_id' => $contact->id,
                'name' => $contact->name,
                'added_to_list' => $added,
            ];
        }

        return $summary;
    }

    /**
     * Which of the given captured items are already in the user's CRM, and
     * on which lists — the popup's "Already in your CRM" badges.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array{index: int, contact_id: int, name: string, matched_by: string, lists: array<int, array{id: int, name: string}>}>
     */
    public function lookup(User $user, array $items): array
    {
        $this->buildIndex($user);

        $matches = [];

        foreach (array_values($items) as $index => $item) {
            if (! is_array($item)) {
                continue;
            }

            $item = array_map(fn ($value) => is_scalar($value) ? (string) $value : null, $item);
            [$existing, $matchedBy] = $this->findExisting($this->normalise($item));

            if ($existing) {
                $matches[] = [
                    'index' => $index,
                    'contact_id' => $existing->id,
                    'name' => (string) $existing->name,
                    'matched_by' => (string) $matchedBy,
                    'lists' => [],
                ];
            }
        }

        if ($matches !== []) {
            $lists = CrmContact::whereKey(array_column($matches, 'contact_id'))
                ->with('lists:id,name')
                ->get()
                ->mapWithKeys(fn (CrmContact $contact) => [
                    $contact->id => $contact->lists->map(fn (CrmList $list) => ['id' => $list->id, 'name' => $list->name])->values()->all(),
                ]);

            foreach ($matches as &$match) {
                $match['lists'] = $lists[$match['contact_id']] ?? [];
            }
        }

        return $matches;
    }

    /**
     * Loads the user's real (non-sandbox) contacts once per request and
     * indexes them by every dedupe key.
     */
    protected function buildIndex(User $user): void
    {
        $this->index = [];

        $user->crmContacts()
            ->where('is_sandbox', false)
            ->get(['id', 'user_id', 'name', 'email', 'phone', 'website', 'raw_data'])
            ->each(fn (CrmContact $contact) => $this->remember($contact));
    }

    protected function remember(CrmContact $contact): void
    {
        $raw = is_array($contact->raw_data) ? $contact->raw_data : [];

        $keys = [
            'place_id' => $raw['google_place_id'] ?? null,
            'cid' => $raw['google_cid'] ?? null,
            'ftid' => $raw['google_ftid'] ?? null,
            'linkedin_url' => static::normaliseLinkedInUrl($raw['linkedin_url'] ?? null),
            'phone' => static::normalisePhone($contact->phone),
            'website' => static::normaliseWebsite($contact->website),
            'email' => $contact->email ? mb_strtolower(trim($contact->email)) : null,
        ];

        foreach ($keys as $type => $value) {
            if ($value !== null && $value !== '' && ! isset($this->index[$type][$value])) {
                $this->index[$type][$value] = $contact;
            }
        }
    }

    /**
     * @param  array<string, mixed>  $data  normalised item
     * @return array{0: ?CrmContact, 1: ?string}
     */
    protected function findExisting(array $data): array
    {
        $candidates = [
            'place_id' => $data['place_id'] ?? null,
            'cid' => $data['cid'] ?? null,
            'ftid' => $data['ftid'] ?? null,
            'linkedin_url' => static::normaliseLinkedInUrl($data['linkedin_url'] ?? null),
            'phone' => static::normalisePhone($data['phone'] ?? null),
            'website' => static::normaliseWebsite($data['website'] ?? null),
            'email' => isset($data['email']) ? mb_strtolower(trim((string) $data['email'])) : null,
        ];

        foreach ($candidates as $type => $value) {
            if ($value !== null && $value !== '' && isset($this->index[$type][$value])) {
                return [$this->index[$type][$value], $type];
            }
        }

        return [null, null];
    }

    /**
     * Trims every string, adds a scheme to a bare website and lower-cases
     * the email, so dedupe and storage see one consistent shape.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function normalise(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_string($value)) {
                $value = trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
                $data[$key] = $value === '' ? null : $value;
            }
        }

        if (! empty($data['website']) && ! preg_match('#^https?://#i', (string) $data['website'])) {
            $data['website'] = 'https://'.$data['website'];
        }

        if (! empty($data['website']) && ! filter_var($data['website'], FILTER_VALIDATE_URL)) {
            $data['website'] = null;
        }

        if (! empty($data['email'])) {
            $data['email'] = mb_strtolower((string) $data['email']);
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function attributes(User $user, array $data): array
    {
        $source = $data['source'] ?? 'google_maps';

        if ($source === 'linkedin') {
            return [
                'user_id' => $user->id,
                'name' => $data['name'],
                'title' => isset($data['title']) ? Str::limit((string) $data['title'], 250, '') : null,
                'company' => $data['company'] ?? null,
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? null,
                'website' => $data['website'] ?? null,
                'location' => $data['location'] ?? null,
                'notes' => $data['notes'] ?? null,
                'source' => 'linkedin',
                'status' => 'new',
                'raw_data' => array_filter([
                    'linkedin_url' => $data['linkedin_url'] ?? null,
                    'captured_via' => 'chrome_extension',
                ]),
            ];
        }

        // Same shape Lead Finder writes (source google_maps, raw_data
        // google_place_id/type) so both tools recognise each other's imports.
        return [
            'user_id' => $user->id,
            'name' => $data['name'],
            'company' => $data['company'] ?? $data['name'],
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'website' => $data['website'] ?? null,
            'location' => isset($data['address']) ? Str::limit((string) $data['address'], 250, '') : ($data['location'] ?? null),
            'notes' => $data['notes'] ?? null,
            'source' => 'google_maps',
            'status' => 'new',
            'raw_data' => array_filter([
                'google_place_id' => $data['place_id'] ?? null,
                'google_cid' => $data['cid'] ?? null,
                'google_ftid' => $data['ftid'] ?? null,
                'type' => $data['category'] ?? null,
                'rating' => isset($data['rating']) ? (float) $data['rating'] : null,
                'review_count' => isset($data['review_count']) ? (int) $data['review_count'] : null,
                'maps_url' => $data['maps_url'] ?? null,
                'captured_via' => 'chrome_extension',
            ], fn ($value) => $value !== null),
        ];
    }

    /**
     * Digits only, compared on the last 9 so "+234 803 123 4567" and
     * "0803 123 4567" are recognised as the same number.
     */
    public static function normalisePhone(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone) ?? '';

        if (strlen($digits) < 7) {
            return null;
        }

        return strlen($digits) > 9 ? substr($digits, -9) : $digits;
    }

    /**
     * The business's own domain ("www.joes.ng/menu" → "joes.ng"); for a
     * shared host (Facebook, Linktree…) the first path segment is kept too.
     */
    public static function normaliseWebsite(?string $website): ?string
    {
        $website = trim((string) $website);

        if ($website === '') {
            return null;
        }

        if (! preg_match('#^https?://#i', $website)) {
            $website = 'https://'.$website;
        }

        $host = mb_strtolower((string) parse_url($website, PHP_URL_HOST));
        $host = (string) preg_replace('/^www\./', '', $host);

        if ($host === '') {
            return null;
        }

        if (in_array($host, self::SHARED_HOSTS, true)) {
            $segment = Str::of((string) parse_url($website, PHP_URL_PATH))->trim('/')->before('/')->lower()->toString();

            return $segment === '' ? null : $host.'/'.$segment;
        }

        return $host;
    }

    /**
     * "https://www.linkedin.com/in/jane-doe-123/?trk=x" → "in/jane-doe-123".
     */
    public static function normaliseLinkedInUrl(?string $url): ?string
    {
        if (! $url || ! preg_match('#linkedin\.com/(in|company)/([^/?\#]+)#i', $url, $matches)) {
            return null;
        }

        return mb_strtolower($matches[1].'/'.rawurldecode($matches[2]));
    }
}
