<?php

namespace App\Http\Controllers\Mobile;

use App\Http\Controllers\Controller;
use App\Services\Mobile\MobileAuthServer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * app.affilstack.com/mobile/authorize — the page the official AffilStack
 * app opens in the phone's secure browser. A signed-out visitor is sent
 * through the normal website sign-in first (password + two-factor, or
 * Google) and comes back here; then one tap on "Continue" hands the app a
 * one-time code. The tap is deliberate: it stops another app on the phone
 * from silently collecting a code for a customer who is already signed in.
 */
class MobileAuthorizeController extends Controller
{
    protected const PARAMS = ['client_id', 'redirect_uri', 'response_type', 'state', 'code_challenge', 'code_challenge_method'];

    public function __construct(protected MobileAuthServer $server) {}

    public function show(Request $request): View|RedirectResponse
    {
        $params = $this->params($request);
        $result = $this->server->validateAuthorization($params);

        if (isset($result['error'])) {
            return view('mobile.authorize-error', ['message' => $result['error']]);
        }

        if (isset($result['redirect_error'])) {
            return $this->backToApp($params, ['error' => $result['redirect_error']]);
        }

        if ($reason = $this->server->denialReason($request->user())) {
            return view('mobile.authorize-error', ['message' => $reason]);
        }

        return view('mobile.authorize', ['params' => $params]);
    }

    public function approve(Request $request): View|RedirectResponse
    {
        $params = $this->params($request);
        $result = $this->server->validateAuthorization($params);

        if (isset($result['error'])) {
            return view('mobile.authorize-error', ['message' => $result['error']]);
        }

        if (isset($result['redirect_error'])) {
            return $this->backToApp($params, ['error' => $result['redirect_error']]);
        }

        if ($reason = $this->server->denialReason($request->user())) {
            return view('mobile.authorize-error', ['message' => $reason]);
        }

        $code = $this->server->issueCode($request->user(), $params['redirect_uri'], $params['code_challenge']);

        return $this->backToApp($params, ['code' => $code]);
    }

    public function deny(Request $request): View|RedirectResponse
    {
        $params = $this->params($request);
        $result = $this->server->validateAuthorization($params);

        if (isset($result['error'])) {
            return view('mobile.authorize-error', ['message' => $result['error']]);
        }

        return $this->backToApp($params, ['error' => 'access_denied']);
    }

    /**
     * @return array<string, string>
     */
    protected function params(Request $request): array
    {
        return collect(self::PARAMS)
            ->mapWithKeys(fn (string $key): array => [$key => $request->input($key)])
            ->filter(fn (mixed $value): bool => is_string($value) && $value !== '')
            ->map(fn (string $value): string => mb_substr($value, 0, 2000))
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
