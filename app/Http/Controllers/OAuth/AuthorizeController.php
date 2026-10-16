<?php

namespace App\Http\Controllers\OAuth;

use App\Http\Controllers\Controller;
use App\Models\AddonInstall;
use App\Services\Marketplace\OAuthServer;
use App\Services\Payments\CheckoutCountryResolver;
use App\Services\Payments\PaymentGatewayManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

/**
 * The "Connect with AffilStack" screen: a connected app asks for
 * permissions, the customer allows or denies. Paid apps ask the customer
 * to subscribe first.
 */
class AuthorizeController extends Controller
{
    protected const PARAMS = ['client_id', 'redirect_uri', 'response_type', 'scope', 'state', 'code_challenge', 'code_challenge_method'];

    public function __construct(protected OAuthServer $server) {}

    public function show(Request $request, PaymentGatewayManager $gateways, CheckoutCountryResolver $countries): View|RedirectResponse
    {
        $params = $this->params($request);
        $result = $this->server->validateAuthorization($params, $request->user());

        if (isset($result['error'])) {
            return view('oauth.error', ['message' => $result['error']]);
        }

        if (isset($result['redirect_error'])) {
            return $this->backToApp($params, ['error' => $result['redirect_error']]);
        }

        $addon = $result['addon'];
        $version = $addon->versionFor($request->user());
        $install = AddonInstall::query()->where('addon_id', $addon->id)->where('user_id', $request->user()->id)->first();
        $mustPay = $version->isPaid() && $addon->developer?->user_id !== $request->user()->id && ! $install?->isUsable();

        return view('oauth.authorize', [
            'addon' => $addon->load('developer'),
            'version' => $version,
            'scopes' => collect($result['scopes'])->mapWithKeys(fn ($scope) => [$scope => config('marketplace.scopes.'.$scope)])->all(),
            'params' => $params,
            'mustPay' => $mustPay,
            'gateways' => $mustPay ? $gateways->enabledForCountry($countries->resolve($request)) : [],
            'testing' => ! $addon->isListed(),
        ]);
    }

    public function approve(Request $request): View|RedirectResponse
    {
        $params = $this->params($request);
        $result = $this->server->validateAuthorization($params, $request->user());

        if (isset($result['error'])) {
            return view('oauth.error', ['message' => $result['error']]);
        }

        if (isset($result['redirect_error'])) {
            return $this->backToApp($params, ['error' => $result['redirect_error']]);
        }

        try {
            $code = $this->server->approve($request->user(), $result['addon'], $result['scopes'], $params['redirect_uri'], $params['code_challenge'] ?? null);
        } catch (RuntimeException $e) {
            return redirect()->route('oauth.authorize', $params)->with('error', $e->getMessage());
        }

        return $this->backToApp($params, ['code' => $code]);
    }

    public function deny(Request $request): View|RedirectResponse
    {
        $params = $this->params($request);
        $result = $this->server->validateAuthorization($params, $request->user());

        if (isset($result['error'])) {
            return view('oauth.error', ['message' => $result['error']]);
        }

        return $this->backToApp($params, ['error' => 'access_denied']);
    }

    /**
     * @return array<string, string>
     */
    protected function params(Request $request): array
    {
        return collect(self::PARAMS)
            ->mapWithKeys(fn ($key) => [$key => $request->input($key)])
            ->filter(fn ($value) => is_string($value) && $value !== '')
            ->map(fn ($value) => mb_substr($value, 0, 2000))
            ->all();
    }

    /**
     * @param  array<string, string>  $params
     * @param  array<string, string>  $result
     */
    protected function backToApp(array $params, array $result): RedirectResponse
    {
        if (isset($params['state'])) {
            $result['state'] = $params['state'];
        }

        $uri = $params['redirect_uri'];

        return redirect()->away($uri.(str_contains($uri, '?') ? '&' : '?').http_build_query($result));
    }
}
