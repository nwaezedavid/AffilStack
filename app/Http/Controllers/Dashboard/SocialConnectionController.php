<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\GoogleOauthSetting;
use App\Models\SocialConnection;
use App\Services\Social\SocialConnectionVerifier;
use App\Services\Social\SocialOAuthProvider;
use App\Services\Social\SocialProviderRegistry;
use App\Services\Social\SocialPublishProvider;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * A single "Connected Accounts" hub for every user-owned social OAuth
 * connection — LinkedIn (task #3, identity-only) and YouTube/TikTok/
 * Instagram (task #2, real publish-capable connections — see
 * SocialPublishProvider and Dashboard\SocialPublishController). The list of
 * providers and their copy lives in SocialProviderRegistry.
 *
 * Every connection is verified live (SocialConnectionVerifier) right after
 * connecting, whenever the user clicks "Test connection", and daily by
 * social-connections:verify, so each card shows whether it really works.
 */
class SocialConnectionController extends Controller
{
    protected const SESSION_STATE_KEY = 'social_connect_state';

    protected const SESSION_PROVIDER_KEY = 'social_connect_provider';

    public function __construct(
        protected SocialProviderRegistry $registry,
        protected SocialConnectionVerifier $verifier,
    ) {}

    protected function resolveProvider(string $provider): SocialOAuthProvider
    {
        return $this->registry->get($provider);
    }

    /**
     * YouTube uses Google's sensitive scopes, so while the admin limits them
     * to review test accounts, nobody else sees "Connect YouTube".
     */
    protected function canConnect(SocialOAuthProvider $provider): bool
    {
        if (! $provider->isAvailable()) {
            return false;
        }

        return $provider->key() !== 'youtube'
            || GoogleOauthSetting::current()->sensitiveConnectionsOpenTo(auth()->user());
    }

    public function index(): View
    {
        $connections = auth()->user()->socialConnections()->get()->keyBy('provider');
        $providers = collect($this->registry->all())->map(fn (SocialOAuthProvider $p) => [
            ...$this->registry->meta($p->key()),
            'key' => $p->key(),
            'available' => $this->canConnect($p),
            'approved' => $p instanceof SocialPublishProvider ? $p->isApprovedForPublishing() : null,
            'publishes' => $p instanceof SocialPublishProvider,
        ]);

        $summary = [
            'connected' => $connections->count(),
            'verified' => $connections->where('verification_status', SocialConnection::STATUS_VERIFIED)->count(),
            'attention' => $connections->filter(fn (SocialConnection $c) => $c->isBroken())->count(),
            'available' => $providers->where('available', true)->count(),
        ];

        return view('dashboard.social-connections.index', compact('connections', 'providers', 'summary'));
    }

    public function redirectToProvider(Request $request, string $provider): RedirectResponse
    {
        try {
            $service = $this->resolveProvider($provider);
        } catch (InvalidArgumentException) {
            abort(404);
        }

        if (! $this->canConnect($service)) {
            return back()->with('error', $this->registry->meta($provider)['label'].' connections are not available right now.');
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

        $connection = SocialConnection::updateOrCreate(
            ['user_id' => auth()->id(), 'provider' => $provider],
            [
                'credentials' => $result['credentials'],
                'account_name' => $result['account_name'],
                'account_id' => $result['account_id'],
                'avatar_url' => $result['avatar_url'] ?? null,
                'granted_scopes' => $result['scopes'] ?? null,
                'connected_at' => now(),
                'last_verified_at' => null,
                'verification_status' => null,
                'verification_message' => null,
                'broken_notified_at' => null,
            ]
        );

        $label = $this->registry->meta($provider)['label'];

        // Prove the fresh token actually works before calling it done. A
        // check that blows up unexpectedly must never undo a good connect —
        // the card simply stays "Connected" until the next check.
        try {
            $check = $this->verifier->verify($connection, notify: false);
        } catch (Throwable $e) {
            report($e);

            return redirect()->route('social-connections.index')->with('success', "{$label} connected as {$result['account_name']}.");
        }

        return $check->isVerified()
            ? redirect()->route('social-connections.index')->with('success', "{$label} connected and verified as {$connection->fresh()->account_name}.")
            : redirect()->route('social-connections.index')->with('error', "{$label} connected, but the first check failed: {$check->message}");
    }

    /**
     * "Test connection" — a live check the user asked for, so the result is
     * shown inline rather than also sent to the bell.
     */
    public function verify(string $provider): RedirectResponse
    {
        abort_unless($this->registry->has($provider), 404);

        $connection = auth()->user()->socialConnections()->where('provider', $provider)->first();
        $label = $this->registry->meta($provider)['label'];

        if (! $connection) {
            return redirect()->route('social-connections.index')->with('error', "{$label} isn't connected yet.");
        }

        $check = $this->verifier->verify($connection, notify: false);

        return redirect()->route('social-connections.index')->with(
            $check->isVerified() ? 'success' : 'error',
            $check->isVerified() ? "{$label} is working. {$check->message}" : "{$label} check failed: {$check->message}",
        );
    }

    public function disconnect(string $provider): RedirectResponse
    {
        auth()->user()->socialConnections()->where('provider', $provider)->delete();

        $label = $this->registry->has($provider) ? $this->registry->meta($provider)['label'] : ucfirst($provider);

        return redirect()->route('social-connections.index')->with('success', "{$label} disconnected.");
    }
}
