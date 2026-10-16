<?php

namespace App\Services\Marketplace;

use App\Models\Addon;
use App\Models\AddonInstall;
use App\Models\User;
use App\Models\UserTemplate;
use App\Models\WebhookEndpoint;
use Illuminate\Support\Facades\DB;

/**
 * Puts an add-on into a customer's account and takes it out again:
 * templates into their library, a connected app's permissions and
 * webhooks, and — when they leave — every token and webhook removed.
 */
class AddonInstaller
{
    /**
     * Installs (or reinstalls) an add-on. A paid add-on is only made
     * active by AddonBilling once paid.
     *
     * @param  array<int, string>  $scopes  for connected apps: the permissions the customer allowed
     */
    public function install(User $user, Addon $addon, array $scopes = [], string $status = 'active'): AddonInstall
    {
        return DB::transaction(function () use ($user, $addon, $scopes, $status) {
            $install = AddonInstall::query()->where('addon_id', $addon->id)->where('user_id', $user->id)->lockForUpdate()->first();
            $isNew = $install === null || $install->status === 'uninstalled' || $install->status === 'expired';

            $install ??= new AddonInstall(['addon_id' => $addon->id, 'user_id' => $user->id]);

            // A paid add-on stays waiting for payment until AddonBilling
            // activates it (developers can always try their own).
            $mustPay = $addon->versionFor($user)?->isPaid() && $addon->developer?->user_id !== $user->id;

            $install->fill([
                'status' => match (true) {
                    $install->exists && $install->isUsable() => $install->status,
                    $mustPay => 'pending_payment',
                    default => $status,
                },
                'installed_at' => $isNew ? now() : $install->installed_at,
                'uninstalled_at' => null,
            ]);

            if ($addon->isApp()) {
                $install->scopes = $this->allowedScopes($addon, $scopes, $user);
            }

            $install->save();

            if ($isNew && $install->status !== 'pending_payment') {
                $addon->increment('installs_count');
            }

            if ($install->status === 'active' || $install->status === 'past_due') {
                $this->provision($install);
            }

            return $install;
        });
    }

    /**
     * Gives the customer what the add-on contains.
     */
    public function provision(AddonInstall $install): void
    {
        $addon = $install->addon;
        $version = $addon->versionFor($install->user);

        if (! $version) {
            return;
        }

        if ($addon->type === 'template_pack' && ! $install->templates()->exists()) {
            $kinds = array_keys(config('marketplace.template_kinds'));

            foreach (array_slice($version->content['items'] ?? [], 0, 200) as $item) {
                UserTemplate::create([
                    'user_id' => $install->user_id,
                    'addon_install_id' => $install->id,
                    'kind' => in_array($item['kind'] ?? null, $kinds, true) ? $item['kind'] : 'prompt',
                    'title' => mb_substr((string) ($item['title'] ?? 'Template'), 0, 160),
                    'body' => (string) ($item['body'] ?? ''),
                    'meta' => ['addon' => $addon->slug, 'version' => $version->version],
                ]);
            }
        }

        if ($addon->isApp()) {
            $this->syncWebhook($install);
        }
    }

    /**
     * The app's webhook for this customer: the events it asked for that
     * the customer's permissions cover.
     */
    public function syncWebhook(AddonInstall $install): void
    {
        $version = $install->addon->versionFor($install->user);
        $map = config('marketplace.webhook_events');
        $events = collect($version?->webhook_events ?? [])
            ->filter(fn ($event) => isset($map[$event]) && in_array($map[$event], $install->scopes ?? [], true))
            ->values()
            ->all();

        if (! $version?->webhook_url || $events === [] || ! $install->isUsable()) {
            $install->webhookEndpoints()->delete();

            return;
        }

        $endpoint = $install->webhookEndpoints()->first() ?? new WebhookEndpoint(['user_id' => $install->user_id, 'addon_install_id' => $install->id]);

        $endpoint->fill([
            'url' => $version->webhook_url,
            'secret' => $install->addon->webhook_secret ?? WebhookEndpoint::generateSecret(),
            'events' => $events,
            'is_active' => true,
        ])->save();
    }

    /**
     * Takes the add-on out of the customer's account.
     */
    public function uninstall(AddonInstall $install, string $status = 'uninstalled'): void
    {
        DB::transaction(function () use ($install, $status) {
            $install->update([
                'status' => $status,
                'uninstalled_at' => $status === 'uninstalled' ? now() : $install->uninstalled_at,
                'cancel_at_period_end' => false,
            ]);

            $this->revokeAccess($install);
            $install->templates()->delete();
        });
    }

    /**
     * Signs a connected app out of the customer's account.
     */
    public function revokeAccess(AddonInstall $install): void
    {
        $install->apiTokens()->delete();
        $install->refreshTokens()->whereNull('revoked_at')->update(['revoked_at' => now()]);
        $install->webhookEndpoints()->delete();
    }

    /**
     * After an approved version drops permissions: take them back from
     * every customer.
     *
     * @param  array<int, string>  $scopes
     */
    public function limitScopes(Addon $addon, array $scopes): void
    {
        $addon->installs()->usable()->each(function (AddonInstall $install) use ($scopes) {
            $kept = array_values(array_intersect($install->scopes ?? [], $scopes));

            if ($kept === ($install->scopes ?? [])) {
                return;
            }

            $install->update(['scopes' => $kept]);

            foreach ($install->apiTokens as $token) {
                $token->update(['scopes' => array_values(array_intersect($token->scopes ?? [], $kept))]);
            }

            $this->syncWebhook($install);
        });
    }

    /**
     * @param  array<int, string>  $requested
     * @return array<int, string>
     */
    public function allowedScopes(Addon $addon, array $requested, ?User $user = null): array
    {
        $offered = $addon->versionFor($user)?->scopes ?? [];

        return array_values(array_intersect(array_keys(config('marketplace.scopes')), $offered, $requested));
    }
}
