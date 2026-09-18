<?php

namespace App\Services\Settings;

use App\Models\HeyGenSetting;
use App\Models\IntegrationFundingSetting;
use App\Models\User;
use App\Notifications\FundingAlert;
use App\Services\Referrals\PayoutWalletService;
use App\Services\Video\HeyGenClient;
use Illuminate\Support\Facades\Notification;

/**
 * Vault, the funding-monitor agent: the third-party accounts and internal
 * balances an admin has to keep funded so a user's action never fails
 * mid-flight (a "Generate video" click HeyGen can't render, a payout the
 * wallet can't cover). Powers the Filament "Funding Watch" page and the
 * integrations:check-funding-health scheduled command.
 *
 * Deliberately split into two kinds, same as SettingsHealthChecker's
 * live_checkable split:
 *  - "live": a real balance this app can query and compare to a
 *    threshold — today, only HeyGen (GET /v2/user/remaining_quota) and the
 *    payout wallet (an internal ledger sum, no external call at all).
 *  - "manual": no balance API exists to query at all — Anthropic's
 *    standard Messages API key has no balance/usage endpoint, and this
 *    app never holds a Meta access token of its own (only an MCP server
 *    URL/token — see BrainAgentSetting). These get an admin-set reminder
 *    cadence instead, exactly the same honest "can't check this, here's
 *    what to do instead" pattern SettingsHealthChecker uses for
 *    LinkedIn/TikTok/Instagram.
 */
class FundingHealthChecker
{
    public function __construct(
        protected PayoutWalletService $wallet,
    ) {}

    /**
     * Fast, no-network snapshot — safe to call on every page load.
     *
     * @return array<int, array<string, mixed>>
     */
    public function items(): array
    {
        $funding = IntegrationFundingSetting::current();

        return [
            $this->heygenItem($funding),
            ...$this->walletItems($funding),
            $this->anthropicItem($funding),
            $this->metaAdsItem($funding),
        ];
    }

    /**
     * Re-checks HeyGen's live balance and every wallet currency against
     * their thresholds, and evaluates whether either manual reminder has
     * fallen due — persisting state and notifying every full admin exactly
     * once per crossing (never once per check), the same "don't repeat
     * yourself" rule SettingsHealthChecker's own live checks don't need
     * because those are only ever run by an explicit button click, not a
     * schedule. Called from integrations:check-funding-health (daily) and
     * from the page's own "Check now" action.
     */
    public function runChecks(): void
    {
        $funding = IntegrationFundingSetting::current();

        $this->checkHeyGenBalance($funding);
        $this->checkWalletBalances($funding);
        $this->checkManualReminder(
            $funding,
            dueColumn: 'anthropic_reminder_notified_at',
            isDue: $funding->fresh()->isAnthropicReminderDue(...),
            title: 'Claude/Anthropic API usage reminder',
            body: "It's been ".$funding->anthropic_reminder_days.' days (or more) since you last confirmed your Anthropic API billing is funded — Brain (the Marketing Agent) stops being able to draft or launch campaigns the moment that account runs dry.',
            settingKey: 'anthropic',
        );
        $this->checkManualReminder(
            $funding->fresh(),
            dueColumn: 'meta_ads_reminder_notified_at',
            isDue: fn (IntegrationFundingSetting $f) => $f->isMetaAdsReminderDue(),
            title: 'Meta ad spend reminder',
            body: "It's been ".$funding->meta_ads_reminder_days.' days (or more) since you last confirmed your Meta ad account is funded — Brain\'s live campaigns pause the moment that account runs dry.',
            settingKey: 'meta_ads',
        );
    }

    protected function checkHeyGenBalance(IntegrationFundingSetting $funding): void
    {
        $heygen = HeyGenSetting::current();

        if (! $heygen->hasApiKey()) {
            return;
        }

        $result = (new HeyGenClient((string) $heygen->credential('api_key')))->checkBalance();

        if (! $result['success']) {
            return;
        }

        $wasLow = $funding->heygen_low_balance_notified_at !== null;
        $isLow = $result['remaining'] < $funding->heygen_low_balance_threshold;

        $funding->update([
            'heygen_balance_value' => $result['remaining'],
            'heygen_balance_checked_at' => now(),
            'heygen_low_balance_notified_at' => $isLow ? ($funding->heygen_low_balance_notified_at ?? now()) : null,
        ]);

        if ($isLow && ! $wasLow) {
            $this->notifyAdmins(
                'HeyGen video credits running low',
                "HeyGen has {$result['remaining']} credits left (threshold: {$funding->heygen_low_balance_threshold}) — top up your HeyGen account before users' \"Generate video\" clicks start failing.",
            );
        }
    }

    protected function checkWalletBalances(IntegrationFundingSetting $funding): void
    {
        $thresholds = (array) $funding->wallet_thresholds_cents;
        $notified = (array) $funding->wallet_currencies_notified;

        foreach ($thresholds as $currency => $thresholdCents) {
            if ((int) $thresholdCents <= 0) {
                continue;
            }

            $balance = $this->wallet->balance($currency);
            $wasNotified = (bool) ($notified[$currency] ?? false);
            $isLow = $balance < (int) $thresholdCents;

            $notified[$currency] = $isLow;

            if ($isLow && ! $wasNotified) {
                $this->notifyAdmins(
                    "Payout wallet ({$currency}) running low",
                    'Your '.$currency.' payout wallet balance is '.number_format($balance / 100, 2).' — below your '.number_format($thresholdCents / 100, 2).' threshold. Top it up before an affiliate payout in this currency can\'t be disbursed automatically.',
                );
            }
        }

        $funding->update(['wallet_currencies_notified' => $notified]);
    }

    /**
     * @param  callable(IntegrationFundingSetting): bool  $isDue
     */
    protected function checkManualReminder(IntegrationFundingSetting $funding, string $dueColumn, callable $isDue, string $title, string $body, string $settingKey): void
    {
        if (! $isDue($funding)) {
            return;
        }

        // Only notify once per overdue stretch — cleared whenever the admin
        // acknowledges it (bumps *_reminder_last_acknowledged_at), so the
        // next time it falls due it notifies again.
        if ($funding->{$dueColumn} !== null && $funding->{$dueColumn}->greaterThan($funding->{$settingKey.'_reminder_last_acknowledged_at'})) {
            return;
        }

        $funding->update([$dueColumn => now()]);

        $this->notifyAdmins($title, $body);
    }

    protected function notifyAdmins(string $title, string $body): void
    {
        $admins = User::role(['admin', 'super-admin'])->get();

        if ($admins->isEmpty()) {
            return;
        }

        Notification::send($admins, new FundingAlert($title, $body, route('filament.admin.pages.funding-health')));
    }

    /**
     * @return array<string, mixed>
     */
    protected function heygenItem(IntegrationFundingSetting $funding): array
    {
        $heygen = HeyGenSetting::current();

        return [
            'key' => 'heygen',
            'label' => 'HeyGen video credits',
            'kind' => 'live',
            'is_configured' => $heygen->hasApiKey(),
            'balance' => $funding->heygen_balance_value,
            'threshold' => $funding->heygen_low_balance_threshold,
            'checked_at' => $funding->heygen_balance_checked_at,
            'is_low' => $funding->isHeyGenBalanceLow(),
            'settings_url' => route('filament.admin.pages.ugc-video-settings'),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function walletItems(IntegrationFundingSetting $funding): array
    {
        $balances = $this->wallet->balances();
        $thresholds = (array) $funding->wallet_thresholds_cents;

        // Every currency that's either ever had a movement or already has
        // a threshold set — so a threshold survives being shown even
        // before the first top-up in that currency.
        $currencies = collect(array_keys($balances))
            ->merge(array_keys($thresholds))
            ->unique()
            ->sort()
            ->values();

        if ($currencies->isEmpty()) {
            return [];
        }

        return $currencies->map(function (string $currency) use ($balances, $funding) {
            $threshold = $funding->walletThresholdCents($currency);
            $balance = $balances[$currency] ?? 0;

            return [
                'key' => "wallet_{$currency}",
                'label' => "Payout wallet ({$currency})",
                'kind' => 'live',
                'is_configured' => $threshold > 0,
                'balance' => $balance,
                'threshold' => $threshold,
                'checked_at' => now(),
                'is_low' => $threshold > 0 && $balance < $threshold,
                'settings_url' => route('filament.admin.resources.wallet-transactions.index'),
                'currency' => $currency,
            ];
        })->all();
    }

    /**
     * @return array<string, mixed>
     */
    protected function anthropicItem(IntegrationFundingSetting $funding): array
    {
        return [
            'key' => 'anthropic',
            'label' => 'Claude / Anthropic API usage',
            'kind' => 'manual',
            'reminder_days' => $funding->anthropic_reminder_days,
            'last_acknowledged_at' => $funding->anthropic_reminder_last_acknowledged_at,
            'is_due' => $funding->isAnthropicReminderDue(),
            'settings_url' => route('filament.admin.pages.brain-agent-settings'),
            'why_manual' => "Anthropic's API key has no balance/usage endpoint to check — billing is only visible in your Anthropic Console.",
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function metaAdsItem(IntegrationFundingSetting $funding): array
    {
        return [
            'key' => 'meta_ads',
            'label' => 'Meta ad spend',
            'kind' => 'manual',
            'reminder_days' => $funding->meta_ads_reminder_days,
            'last_acknowledged_at' => $funding->meta_ads_reminder_last_acknowledged_at,
            'is_due' => $funding->isMetaAdsReminderDue(),
            'settings_url' => route('filament.admin.pages.brain-agent-settings'),
            'why_manual' => 'This app only holds the Meta Ads MCP server\'s own URL/token, never a Meta access token of its own, so ad account balance can\'t be queried directly — check it in Meta Ads Manager.',
        ];
    }
}
