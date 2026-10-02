<?php

namespace App\Services\Social;

use App\Models\SocialConnection;
use App\Notifications\SocialConnectionBroken;
use Illuminate\Http\Client\ConnectionException;

/**
 * Runs a live check of a SocialConnection and records the result on it —
 * right after connecting, when the user clicks "Test connection", and
 * daily from social-connections:verify. When a working connection breaks,
 * the owner gets ONE bell notification for that break (broken_notified_at —
 * immediately for an expired token, after two failed checks in a row otherwise);
 * the next successful check clears it so a later break notifies again.
 */
class SocialConnectionVerifier
{
    public function __construct(protected SocialProviderRegistry $registry) {}

    /**
     * @param  bool  $notify  false when the user triggered the check and is
     *                        looking at the result already
     */
    public function verify(SocialConnection $connection, bool $notify = true): ConnectionCheck
    {
        $provider = $this->registry->has($connection->provider) ? $this->registry->get($connection->provider) : null;
        $label = $this->registry->has($connection->provider) ? $this->registry->meta($connection->provider)['label'] : ucfirst($connection->provider);

        if (! $provider instanceof VerifiesConnection) {
            $check = ConnectionCheck::failed("{$label} connections can't be checked automatically.");
        } elseif (! $provider->isAvailable()) {
            $check = ConnectionCheck::failed("{$label} connections are switched off on AffilStack right now, so this one can't be checked. It will be re-checked once they're back.");
        } else {
            try {
                $check = $provider->verify($connection);
            } catch (ConnectionException) {
                $check = ConnectionCheck::failed("Couldn't reach {$label} just now. We will try again automatically.");
            }
        }

        $this->record($connection, $check, $notify, $label);

        return $check;
    }

    protected function record(SocialConnection $connection, ConnectionCheck $check, bool $notify, string $label): void
    {
        $attributes = [
            'last_verified_at' => now(),
            'verification_status' => $check->status,
            'verification_message' => mb_substr($check->message, 0, 500),
        ];

        if ($check->isVerified()) {
            $attributes['broken_notified_at'] = null;
            $attributes['account_name'] = $check->accountName ?: $connection->account_name;
            $attributes['avatar_url'] = $check->avatarUrl ?: $connection->avatar_url;
        }

        // An expired token notifies straight away; a plain failure only once
        // it has happened twice in a row, so one provider hiccup during the
        // daily re-check doesn't ping the user.
        $confirmedBroken = $check->status === SocialConnection::STATUS_EXPIRED
            || ($check->status === SocialConnection::STATUS_FAILED && $connection->verification_status === SocialConnection::STATUS_FAILED);
        $shouldNotify = $notify && $confirmedBroken && $connection->broken_notified_at === null;

        if ($shouldNotify) {
            $attributes['broken_notified_at'] = now();
        }

        // The provider may have refreshed and re-saved the token mid-check.
        $connection->refresh()->update($attributes);

        if ($shouldNotify && $connection->user) {
            $connection->user->notify(new SocialConnectionBroken($connection->provider, $label, $check->status, $check->message));
        }
    }
}
