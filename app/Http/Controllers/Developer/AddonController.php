<?php

namespace App\Http\Controllers\Developer;

use App\Http\Controllers\Controller;
use App\Models\Addon;
use App\Models\AddonVersion;
use App\Models\DeveloperProfile;
use App\Rules\PublicUrl;
use App\Services\Marketplace\AddonReviewService;
use App\Services\Marketplace\DeveloperPayouts;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * A developer's add-ons: create, edit the listing and settings (always on
 * a draft version), submit for review, credentials and retiring.
 */
class AddonController extends Controller
{
    public function __construct(protected AddonReviewService $reviews) {}

    public function index(Request $request, DeveloperPayouts $payouts): View
    {
        $developer = $this->developer($request);

        return view('developer.dashboard', [
            'developer' => $developer,
            'addons' => $developer->addons()->with(['liveVersion', 'workingVersion'])->latest()->get(),
            'balance' => $payouts->balance($developer),
        ]);
    }

    public function create(): View
    {
        return view('developer.addons.create', ['types' => config('marketplace.types')]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(config('marketplace.types')))],
            'name' => ['required', 'string', 'min:2', 'max:60'],
        ]);

        $addon = $this->reviews->create($this->developer($request), $data['type'], $data['name']);
        $redirect = redirect()->route('developer.addons.edit', $addon)->with('success', 'Add-on created. Fill in the listing, then submit it for review.');

        // The client secret is only ever shown now.
        if ($addon->isApp()) {
            $redirect->with('client_secret', $addon->issueClientCredentials());
        }

        return $redirect;
    }

    public function show(Request $request, Addon $addon): View
    {
        $this->authorizeAddon($request, $addon);

        $working = $addon->workingVersion()->first();

        return view('developer.addons.show', [
            'addon' => $addon->load('liveVersion'),
            'working' => $working,
            'problems' => $working && $working->isEditable() ? $this->reviews->problems($working) : [],
            'versions' => $addon->versions()->latest('id')->limit(10)->get(),
            'activeInstalls' => $addon->installs()->usable()->count(),
            'earned' => (int) $addon->earnings()->whereIn('status', ['pending', 'approved', 'paid'])->sum('amount_cents'),
        ]);
    }

    public function edit(Request $request, Addon $addon): View|RedirectResponse
    {
        $this->authorizeAddon($request, $addon);

        if ($addon->workingVersion()->where('status', 'submitted')->exists()) {
            return redirect()->route('developer.addons.show', $addon)->with('error', 'This version is waiting for review. Withdraw it to make more changes.');
        }

        return view('developer.addons.edit', [
            'addon' => $addon,
            'version' => $this->reviews->draftFor($addon),
            'scopes' => config('marketplace.scopes'),
            'events' => config('marketplace.webhook_events'),
            'categories' => config('marketplace.categories'),
            'kinds' => config('marketplace.template_kinds'),
            'platforms' => config('marketplace.automation_platforms'),
        ]);
    }

    public function update(Request $request, Addon $addon): RedirectResponse
    {
        $this->authorizeAddon($request, $addon);

        $version = $addon->workingVersion()->first();

        if (! $version || ! $version->isEditable()) {
            return redirect()->route('developer.addons.show', $addon)->with('error', 'This version can no longer be edited.');
        }

        $data = $request->validate($this->rules($addon));

        $attributes = [
            'version' => $data['version'],
            'name' => $data['name'],
            'tagline' => $data['tagline'] ?? '',
            'description' => $data['description'] ?? '',
            'category' => $data['category'],
            'pricing' => $data['pricing'],
            'price_cents' => $data['pricing'] === 'monthly' ? (int) round(((float) ($data['price'] ?? 0)) * 100) : 0,
            'website_url' => $data['website_url'] ?? null,
            'support_url' => $data['support_url'] ?? null,
            'changelog' => $data['changelog'] ?? null,
            'status' => 'draft',
        ];

        if ($request->hasFile('icon')) {
            $attributes['icon_path'] = $request->file('icon')->store('marketplace/'.$addon->id, 'public');
        }

        $screenshots = array_values(array_diff($version->screenshots ?? [], $data['remove_screenshots'] ?? []));

        foreach ($request->file('screenshots', []) as $file) {
            $screenshots[] = $file->store('marketplace/'.$addon->id, 'public');
        }

        $attributes['screenshots'] = array_slice($screenshots, 0, 6);

        if ($addon->isApp()) {
            $attributes += [
                'privacy_url' => $data['privacy_url'] ?? null,
                'install_url' => $data['install_url'] ?? null,
                'redirect_uris' => $this->lines($data['redirect_uris'] ?? ''),
                'scopes' => array_values(array_intersect(array_keys(config('marketplace.scopes')), $data['scopes'] ?? [])),
                'webhook_url' => $data['webhook_url'] ?? null,
                'webhook_events' => array_values(array_intersect(array_keys(config('marketplace.webhook_events')), $data['webhook_events'] ?? [])),
            ];
        } elseif ($addon->type === 'template_pack') {
            $attributes['content'] = ['items' => collect($data['items'] ?? [])
                ->filter(fn ($item) => trim((string) ($item['title'] ?? '')) !== '' && trim((string) ($item['body'] ?? '')) !== '')
                ->map(fn ($item) => ['kind' => $item['kind'], 'title' => trim($item['title']), 'body' => trim($item['body'])])
                ->values()
                ->all()];
        } else {
            $content = $version->content ?? [];
            $content['platform'] = $data['platform'] ?? null;
            $content['share_url'] = $data['share_url'] ?? null;
            $content['steps'] = $data['steps'] ?? '';

            if ($request->hasFile('blueprint')) {
                $content['blueprint'] = (string) file_get_contents($request->file('blueprint')->getRealPath());
                $content['blueprint_name'] = $request->file('blueprint')->getClientOriginalName();
            } elseif ($request->boolean('remove_blueprint')) {
                unset($content['blueprint'], $content['blueprint_name']);
            }

            $attributes['content'] = $content;
        }

        $this->deleteReplacedFiles($version, $attributes);
        $version->update($attributes);

        if ($request->input('action') === 'submit') {
            return $this->submit($request, $addon);
        }

        return redirect()->route('developer.addons.edit', $addon)->with('success', 'Draft saved.');
    }

    public function submit(Request $request, Addon $addon): RedirectResponse
    {
        $this->authorizeAddon($request, $addon);

        $version = $addon->workingVersion()->first();

        if (! $version) {
            return redirect()->route('developer.addons.show', $addon)->with('error', 'There are no changes to submit.');
        }

        try {
            $this->reviews->submit($version);
        } catch (ValidationException $e) {
            return redirect()->route('developer.addons.show', $addon)->withErrors($e->errors());
        }

        return redirect()->route('developer.addons.show', $addon)->with('success', 'Submitted for review. We\'ll email you when it has been reviewed — usually within two working days.');
    }

    public function withdraw(Request $request, Addon $addon): RedirectResponse
    {
        $this->authorizeAddon($request, $addon);

        $addon->versions()->where('status', 'submitted')->update(['status' => 'draft', 'submitted_at' => null]);

        return redirect()->route('developer.addons.edit', $addon)->with('success', 'Withdrawn from review. You can edit it again.');
    }

    public function regenerateSecret(Request $request, Addon $addon): RedirectResponse
    {
        $this->authorizeAddon($request, $addon);
        abort_unless($addon->isApp(), 404);

        return redirect()->route('developer.addons.show', $addon)
            ->with('client_secret', $addon->issueClientCredentials())
            ->with('success', 'New client secret created. The old one stops working now — update your app.');
    }

    public function retire(Request $request, Addon $addon): RedirectResponse
    {
        $this->authorizeAddon($request, $addon);

        $addon->update(['status' => 'retired']);

        // Paid customers keep it until the end of the month they paid for.
        $addon->installs()->where('status', 'active')->where('price_cents', '>', 0)->update(['cancel_at_period_end' => true]);

        return redirect()->route('developer.addons.show', $addon)->with('success', 'Retired. It is no longer in the marketplace, and paid subscriptions end at the end of their current month.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(Addon $addon): array
    {
        $rules = [
            'version' => ['required', 'regex:/^\d{1,3}\.\d{1,3}\.\d{1,4}$/'],
            'name' => ['required', 'string', 'min:2', 'max:60'],
            'tagline' => ['nullable', 'string', 'max:140'],
            'description' => ['nullable', 'string', 'max:8000'],
            'category' => ['required', Rule::in(array_keys(config('marketplace.categories')))],
            'pricing' => ['required', Rule::in(['free', 'monthly'])],
            'price' => ['nullable', 'required_if:pricing,monthly', 'numeric', 'min:'.(config('marketplace.min_price_cents') / 100), 'max:'.(config('marketplace.max_price_cents') / 100)],
            'website_url' => ['nullable', 'url:https,http', 'max:255'],
            'support_url' => ['nullable', 'url:https,http,mailto', 'max:255'],
            'changelog' => ['nullable', 'string', 'max:2000'],
            'icon' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:1024', 'dimensions:min_width=128,min_height=128'],
            'screenshots' => ['nullable', 'array', 'max:6'],
            'screenshots.*' => ['image', 'mimes:png,jpg,jpeg,webp', 'max:4096'],
            'remove_screenshots' => ['nullable', 'array'],
            'remove_screenshots.*' => ['string'],
        ];

        return $rules + match ($addon->type) {
            'app' => [
                'privacy_url' => ['nullable', 'url:https', 'max:255'],
                'install_url' => ['nullable', 'url:https,http', 'max:255'],
                'redirect_uris' => ['nullable', 'string', 'max:2000', function (string $attribute, mixed $value, \Closure $fail) {
                    foreach ($this->lines((string) $value) as $uri) {
                        $isLocal = (bool) preg_match('#^http://(localhost|127\.0\.0\.1)(:\d+)?(/|$)#', $uri);

                        if ((! str_starts_with($uri, 'https://') && ! $isLocal) || ! filter_var($uri, FILTER_VALIDATE_URL) || str_contains($uri, '#')) {
                            $fail("\"{$uri}\" isn't a valid redirect URL. Use https:// (or http://localhost while testing), without a #fragment.");
                        }
                    }
                }],
                'scopes' => ['nullable', 'array'],
                'scopes.*' => [Rule::in(array_keys(config('marketplace.scopes')))],
                'webhook_url' => ['nullable', 'url:https', 'max:255', new PublicUrl],
                'webhook_events' => ['nullable', 'array'],
                'webhook_events.*' => [Rule::in(array_keys(config('marketplace.webhook_events')))],
            ],
            'template_pack' => [
                'items' => ['nullable', 'array', 'max:200'],
                'items.*.kind' => ['required', Rule::in(array_keys(config('marketplace.template_kinds')))],
                'items.*.title' => ['nullable', 'string', 'max:160'],
                'items.*.body' => ['nullable', 'string', 'max:10000'],
            ],
            default => [
                'platform' => ['nullable', Rule::in(array_keys(config('marketplace.automation_platforms')))],
                'share_url' => ['nullable', 'url:https', 'max:500'],
                'steps' => ['nullable', 'string', 'max:6000'],
                'blueprint' => ['nullable', 'file', 'mimetypes:application/json,text/plain', 'max:512', function (string $attribute, mixed $value, \Closure $fail) {
                    json_decode((string) file_get_contents($value->getRealPath()));

                    if (json_last_error() !== JSON_ERROR_NONE) {
                        $fail('The blueprint must be a valid JSON file exported from your automation platform.');
                    }
                }],
            ],
        };
    }

    /**
     * @return array<int, string>
     */
    protected function lines(string $text): array
    {
        return array_values(array_unique(array_slice(array_filter(array_map('trim', preg_split('/\R/', $text) ?: [])), 0, 10)));
    }

    /**
     * Removes uploads a new version no longer uses, unless an approved
     * version still shows them.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function deleteReplacedFiles(AddonVersion $version, array $attributes): void
    {
        $before = array_filter(array_merge([$version->icon_path], $version->screenshots ?? []));
        $after = array_filter(array_merge([$attributes['icon_path'] ?? $version->icon_path], $attributes['screenshots'] ?? []));
        $inUse = $version->addon->versions()->whereKeyNot($version->id)->get()
            ->flatMap(fn (AddonVersion $other) => array_filter(array_merge([$other->icon_path], $other->screenshots ?? [])))
            ->all();

        foreach (array_diff($before, $after, $inUse) as $path) {
            Storage::disk('public')->delete($path);
        }
    }

    protected function developer(Request $request): DeveloperProfile
    {
        return $request->user()->developerProfile;
    }

    protected function authorizeAddon(Request $request, Addon $addon): void
    {
        abort_unless($addon->developer_profile_id === $this->developer($request)->id, 404);
    }
}
