<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\AddonInstall;
use App\Services\Marketplace\AddonBilling;
use App\Services\Marketplace\AddonInstaller;
use App\Services\Payments\CheckoutCountryResolver;
use App\Services\Payments\PaymentGatewayManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

/**
 * "My add-ons": what a customer has installed, their subscriptions, and
 * switching add-ons off.
 */
class AddonInstallController extends Controller
{
    public function index(Request $request): View
    {
        return view('dashboard.addons.index', [
            'installs' => $request->user()->addonInstalls()
                ->whereIn('status', ['active', 'past_due', 'pending_payment'])
                ->with('addon.liveVersion', 'addon.developer')
                ->latest('installed_at')
                ->get(),
        ]);
    }

    public function show(Request $request, AddonInstall $install, PaymentGatewayManager $gateways, CheckoutCountryResolver $countries): View
    {
        $this->authorizeInstall($request, $install);

        return view('dashboard.addons.show', [
            'install' => $install->load('addon.developer', 'paymentMethod'),
            'version' => $install->addon->versionFor($request->user()),
            'templates' => $install->templates()->orderBy('kind')->orderBy('title')->get(),
            'connected' => $install->apiTokens()->exists() || $install->refreshTokens()->whereNull('revoked_at')->where('expires_at', '>', now())->exists(),
            'gateways' => $gateways->enabledForCountry($countries->resolve($request)),
        ]);
    }

    public function cancel(Request $request, AddonInstall $install, AddonBilling $billing): RedirectResponse
    {
        $this->authorizeInstall($request, $install);
        $billing->cancel($install);
        $install->refresh();

        return $install->status === 'uninstalled'
            ? redirect()->route('addons.index')->with('success', 'Add-on removed.')
            : redirect()->route('addons.show', $install)->with('success', 'Subscription cancelled. You can keep using it until '.$install->current_period_end?->format('M j, Y').'.');
    }

    public function resume(Request $request, AddonInstall $install, AddonBilling $billing): RedirectResponse
    {
        $this->authorizeInstall($request, $install);
        $billing->resume($install);

        return redirect()->route('addons.show', $install)->with('success', 'Your subscription will renew as normal.');
    }

    public function pay(Request $request, AddonInstall $install, AddonBilling $billing, PaymentGatewayManager $gateways, CheckoutCountryResolver $countries): RedirectResponse
    {
        $this->authorizeInstall($request, $install);

        $enabled = $gateways->enabledForCountry($countries->resolve($request));

        if ($enabled === []) {
            return back()->with('error', 'Payments are not available right now. Please try again later.');
        }

        $gateway = collect($enabled)->first(fn ($candidate) => $candidate->key() === $request->input('gateway')) ?? $enabled[0];

        try {
            $checkout = $billing->startCheckout($request->user(), $install->addon, $gateway);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        } catch (Throwable $e) {
            report($e);

            return back()->with('error', 'We could not start the payment. Please try again.');
        }

        return redirect()->away($checkout['link']);
    }

    public function destroy(Request $request, AddonInstall $install, AddonInstaller $installer): RedirectResponse
    {
        $this->authorizeInstall($request, $install);

        $installer->uninstall($install);

        return redirect()->route('addons.index')->with('success', 'Add-on removed. Any app connection has been switched off.');
    }

    /**
     * An automation recipe's blueprint file, to import into Zapier, Make
     * or n8n.
     */
    public function blueprint(Request $request, AddonInstall $install): Response
    {
        $this->authorizeInstall($request, $install);
        abort_unless($install->isUsable() && $install->addon->type === 'automation', 404);

        $content = $install->addon->versionFor($request->user())?->content ?? [];
        abort_if(empty($content['blueprint']), 404);

        $name = preg_replace('/[^A-Za-z0-9._-]+/', '-', (string) ($content['blueprint_name'] ?? $install->addon->slug.'.json')) ?: 'blueprint.json';

        return response((string) $content['blueprint'], 200, [
            'Content-Type' => 'application/json',
            'Content-Disposition' => 'attachment; filename="'.$name.'"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    protected function authorizeInstall(Request $request, AddonInstall $install): void
    {
        abort_unless($install->user_id === $request->user()->id && $install->status !== 'uninstalled', 404);
    }
}
