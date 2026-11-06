<?php

namespace App\Services\Mobile\Push;

use App\Models\User;

/**
 * The kinds of phone notification a customer can switch on or off in the
 * app (More > Notifications). Everything is on until they say otherwise;
 * their choices live in users.push_preferences as {category: bool}.
 */
class PushCategories
{
    public const CONTENT = 'content_ready';

    public const AGENTS = 'agents';

    public const INBOX = 'inbox_replies';

    public const WEEKLY = 'weekly_briefing';

    public const SUPPORT = 'support';

    public const ACCOUNT = 'account';

    public const PAYOUTS = 'payouts';

    /**
     * @var array<string, array{label: string, description: string}>
     */
    public const ALL = [
        self::CONTENT => ['label' => 'Content & videos ready', 'description' => 'When an article, post, script, video or product research is finished.'],
        self::AGENTS => ['label' => 'AI agents', 'description' => 'When an agent needs your approval or finished something for you.'],
        self::INBOX => ['label' => 'Buyer replies', 'description' => 'When a contact replies to one of your emails.'],
        self::WEEKLY => ['label' => 'Weekly briefing', 'description' => 'Your Monday summary of what AffilStack did.'],
        self::SUPPORT => ['label' => 'Support replies', 'description' => 'When our team answers one of your tickets.'],
        self::ACCOUNT => ['label' => 'Account alerts', 'description' => 'Broken connections, paused campaigns and add-on payments.'],
        self::PAYOUTS => ['label' => 'Payouts', 'description' => 'When a referral payout is sent to you.'],
    ];

    public static function exists(string $category): bool
    {
        return array_key_exists($category, self::ALL);
    }

    public static function allows(User $user, string $category): bool
    {
        return (bool) data_get($user->push_preferences, $category, true);
    }

    /**
     * @return array<int, array{key: string, label: string, description: string, enabled: bool}>
     */
    public static function forUser(User $user): array
    {
        return collect(self::ALL)->map(fn (array $category, string $key): array => [
            'key' => $key,
            'label' => $category['label'],
            'description' => $category['description'],
            'enabled' => self::allows($user, $key),
        ])->values()->all();
    }

    /**
     * @param  array<string, bool>  $changes
     */
    public static function update(User $user, array $changes): void
    {
        $preferences = (array) ($user->push_preferences ?? []);

        foreach ($changes as $key => $enabled) {
            if (self::exists($key)) {
                $preferences[$key] = (bool) $enabled;
            }
        }

        $user->forceFill(['push_preferences' => $preferences])->save();
    }
}
