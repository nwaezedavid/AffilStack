<?php

namespace App\Services\Account;

use App\Models\MobileDevice;
use App\Models\User;
use App\Services\Auth\AppleSignInService;
use App\Services\Payments\StripeGateway;

/**
 * "Delete my account" — shared by the website profile page and the phone
 * app (Apple requires in-app deletion). The account is soft-deleted and
 * purged after 30 days, as before; this stops everything that would keep
 * running without a browser: the subscription (so Stripe stops charging),
 * API and app tokens, signed-in phones, a Sign in with Apple link, and the
 * owner's team seats.
 */
class AccountDeactivator
{
    public function __construct(protected StripeGateway $stripe, protected AppleSignInService $apple) {}

    public function deactivate(User $user): void
    {
        $subscription = $user->activeSubscription;

        if ($subscription) {
            if ($subscription->gateway === 'stripe' && $subscription->gateway_subscription_id) {
                try {
                    $this->stripe->cancelSubscription($subscription->gateway_subscription_id);
                } catch (\Throwable $e) {
                    report($e);
                }
            }

            $subscription->update(['status' => 'canceled', 'canceled_at' => now()]);
        }

        // Apple requires apps that offer Sign in with Apple to unlink the
        // Apple ID when the account is deleted.
        if ($user->apple_refresh_token) {
            $this->apple->revoke($user->apple_refresh_token);
            $user->forceFill(['apple_refresh_token' => null])->save();
        }

        MobileDevice::revokeAllFor($user);
        $user->apiTokens()->delete();
        $user->seats()->update(['is_suspended' => true]);

        $user->delete();
    }
}
