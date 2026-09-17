<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\SocialConnection;
use App\Services\Social\InstagramPublishingService;
use App\Services\Social\LinkedInOAuthService;
use App\Services\Social\SocialOAuthProvider;
use App\Services\Social\SocialPublishProvider;
use App\Services\Social\TikTokPublishingService;
use App\Services\Social\YouTubePublishingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use InvalidArgumentException;
use RuntimeException;

/**
 * A single "Connected Accounts" hub for every user-owned social OAuth
 * connection — LinkedIn (task #3, identity-only) and YouTube/TikTok/
 * Instagram (task #2, real publish-capable connections — see
 * SocialPublishProvider and Dashboard\SocialPublishController). Owner-only,
 * matching every other CRM/content-account-level feature — see
 * config('agency.seat_allowed_routes').
 */
class SocialConnectionController extends Controller
{
    protected const SESSION_STATE_KEY = 'social_connect_state';

    protected const SESSION_PROVIDER_KEY = 'social_connect_provider';

    /**
     * @return array<string, SocialOAuthProvider>
     */
    protected function providers(): array
    {
        return [
            'linkedin' => app(LinkedInOAuthService::class),
            'youtube' => app(YouTubePublishingService::class),
            'tiktok' => app(TikTokPublishingService::class),
            'instagram' => app(InstagramPublishingService::class),
        ];
    }

    protected function resolveProvider(string $provider): SocialOAuthProvider
    {
        return $this->providers()[$provider] ?? throw new InvalidArgumentException("Unknown social provider [{$provider}].");
    }

    public function index(): View
    {
        $connections = auth()->user()->socialConnections()->get()->keyBy('provider');
        $providers = collect($this->providers())->map(fn (SocialOAuthProvider $p) => [
            'key' => $p->key(),
            'available' => $p->isAvailable(),
            'approved' => $p instanceof SocialPublishProvider ? $p->isApprovedForPublishing() : null,
            'publishes' => $p instanceof SocialPublishProvider,
        ]);

        return view('dashboard.social-connections.index', compact('connections', 'providers'));
    }

    public function redirectToProvider(Request $request, string $provider): RedirectResponse
    {
        try {
            $service = $this->resolveProvider($provider);
        } catch (InvalidArgumentException) {
            abort(404);
        }

        if (! $service->isAvailable()) {
            return back()->with('error', ucfirst($provider).' connections are not available right now.');
        }

        $state = Str::random(40);
        $request->session()->put(self::SESSION_STATE_KEY, $state);
        $request->session()->put(self::SESSION_PROVIDER_KEY, $provider);

        return redirect()->away($service->authorizationUrl(route('social-connections.callback', $provider), $state));
    }

    public function callback(Request $request, string $provider): RedirectResponse
    {
        $state = $request->session()->pull(self::SESSION_STATE_KEY);
        $expectedProvider = $request->session()->pull(self::SESSION_PROVIDER_KEY);

        if (! auth()->check()) {
            return redirect()->route('login')->with('error', 'Please sign in, then try connecting again.');
        }

        if (! $state || $request->query('state') !== $state || $provider !== $expectedProvider || ! $request->query('code')) {
            return redirect()->route('social-connections.index')->with('error', 'Connection failed — please try again.');
        }

        try {
            $service = $this->resolveProvider($provider);
            $result = $service->exchangeCode((string) $request->query('code'), route('social-connections.callback', $provider));
        } catch (InvalidArgumentException|RuntimeException $e) {
            report($e);

            return redirect()->route('social-connections.index')->with('error', $e instanceof RuntimeException ? $e->getMessage() : 'That connection is not available.');
        }

        SocialConnection::updateOrCreate(
            ['user_id' => auth()->id(), 'provider' => $provider],
            [
                'credentials' => $result['credentials'],
                'account_name' => $result['account_name'],
                'account_id' => $result['account_id'],
                'connected_at' => now(),
            ]
        );

        return redirect()->route('social-connections.index')->with('success', ucfirst($provider)." connected as {$result['account_name']}.");
    }

    public function disconnect(string $provider): RedirectResponse
    {
        auth()->user()->socialConnections()->where('provider', $provider)->delete();

        return redirect()->route('social-connections.index')->with('success', ucfirst($provider).' disconnected.');
    }
}
