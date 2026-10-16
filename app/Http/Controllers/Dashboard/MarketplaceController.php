<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Addon;
use App\Models\AddonInstall;
use App\Services\Marketplace\AddonBilling;
use App\Services\Marketplace\AddonInstaller;
use App\Services\Payments\CheckoutCountryResolver;
use App\Services\Payments\PaymentGatewayManager;
use App\Support\Marketplace\MarketplaceCatalog;
use App\Support\Marketplace\MarketplaceSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

/**
 * The marketplace inside the dashboard: browse, add a free add-on, or
 * subscribe to a paid one.
 */
class MarketplaceController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless(MarketplaceSettings::enabled(), 404);

        return view('dashboard.marketplace.index', [
            'addons' => MarketplaceCatalog::browse($request),
            'types' => config('marketplace.types'),
            'categories' => config('marketplace.categories'),
            'installs' => $request->user()->addonInstalls()->usable()->pluck('addon_id')->flip(),
        ]);
    }

    public function show(Request $request, Addon $addon, PaymentGatewayManager $gateways, CheckoutCountryResolver $countries): View
    {
        $user = $request->user();
        $isOwner = $addon->developer?->user_id === $user->id;
        abort_unless(MarketplaceSettings::enabled() && ($addon->isListed() || $isOwner), 404);

        return view('dashboard.marketplace.show', [
            'addon' => $addon->load('developer'),
            'version' => $addon->versionFor($user),
            'install' => AddonInstall::query()->where('addon_id', $addon->id)->where('user_id', $user->id)->first(),
            'gateways' => $gateways->enabledForCountry($countries->resolve($request)),
            'isOwner' => $isOwner,
        ]);
    }

    public function install(Request $request, Addon $addon, AddonInstaller $installer): RedirectResponse
    {
        $user = $request->user();
        $version = $addon->versionFor($user);
        abort_unless($version && ($addon->isListed() || $addon->developer?->user_id === $user->id), 404);

        // A connected app is added from the app itself ("Connect with
        // AffilStack"), which asks for the customer's permission.
        if ($addon->isApp()) {
            return $version->install_url
                ? redirect()->away($version->install_url)
                : back()->with('error', 'This app has not set up connecting yet.');
        }

        $install = $installer->install($user, $addon);

        if (! $install->isUsable()) {
            return redirect()->route('marketplace.addon', $addon)->with('error', 'Subscribe to add this add-on.');
        }

        return redirect()->route('addons.show', $install)->with('success', $version->name.' has been added to your account.');
    }

    public function subscribe(Request $request, Addon $addon, AddonBilling $billing, PaymentGatewayManager $gateways, CheckoutCountryResolver $countries): RedirectResponse
    {
        $enabled = $gateways->enabledForCountry($countries->resolve($request));

        if ($enabled === []) {
            return back()->with('error', 'Payments are not available right now. Please try again later.');
        }

        $gateway = collect($enabled)->first(fn ($candidate) => $candidate->key() === $request->input('gateway')) ?? $enabled[0];

        // Back to the app's "Connect" screen after paying.
        $return = (string) $request->input('return', '');
        $request->session()->forget('addon_return');

        if ($return !== '' && str_starts_with($return, route('oauth.authorize').'?')) {
            $request->session()->put('addon_return', $return);
        }

        try {
            $checkout = $billing->startCheckout($request->user(), $addon, $gateway);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        } catch (Throwable $e) {
            report($e);

            return back()->with('error', 'We could not start the payment. Please try again.');
        }

        return redirect()->away($checkout['link']);
    }
}
