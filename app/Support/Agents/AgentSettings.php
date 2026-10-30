<?php

namespace App\Support\Agents;

use App\Models\AgentActivity;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Each customer's AI agent switches (users.agent_settings), merged over
 * the defaults below, plus what their plan allows: agents at all
 * (plans.agents_included) and acting without approval (plans.agent_autopilot).
 */
class AgentSettings
{
    /**
     * @var array<string, array{label: string, description: string, icon: string}>
     */
    public const AGENTS = [
        'reply_copilot' => [
            'label' => 'Reply Copilot',
            'description' => 'Checks your inbox every minute, sorts each reply and drafts the best response with your affiliate link. You approve with one tap, or let autopilot send the safe ones.',
            'icon' => 'chat-bubble-left-right',
        ],
        'lead_hunter' => [
            'label' => 'Lead Hunter',
            'description' => 'Every night, tops up each product\'s lead list to your target and tells you when strong leads arrive. Uses your lead allowance, then credits.',
            'icon' => 'user-plus',
        ],
        'commission_watchdog' => [
            'label' => 'Commission Watchdog',
            'description' => 'Checks your affiliate links every day and spots broken links, paused offers and falling sales. With your approval, it swaps in a working link.',
            'icon' => 'shield-check',
        ],
        'content_autopilot' => [
            'label' => 'Content Autopilot',
            'description' => 'Plans your week, writes the content for your products and queues it in your content calendar, favouring the angles that get clicks.',
            'icon' => 'calendar-days',
        ],
        'offer_scout' => [
            'label' => 'Offer Scout',
            'description' => 'Looks through your connected networks for better-paying offers in your niche and shows how much more you could earn.',
            'icon' => 'magnifying-glass-circle',
        ],
        'weekly_briefing' => [
            'label' => 'Weekly briefing',
            'description' => 'A Monday email with what your agents did, your results and the three best next steps.',
            'icon' => 'envelope-open',
        ],
    ];

    /**
     * @var array<string, mixed>
     */
    public const DEFAULTS = [
        'reply_copilot' => ['enabled' => true, 'autopilot' => false],
        'lead_hunter' => ['enabled' => false, 'target' => 50],
        'commission_watchdog' => ['enabled' => true],
        'content_autopilot' => ['enabled' => false, 'per_week' => 3],
        'offer_scout' => ['enabled' => true],
        'weekly_briefing' => ['enabled' => true],
    ];

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function for(User $user): array
    {
        $owner = $user->billableUser();
        $saved = is_array($owner->agent_settings) ? $owner->agent_settings : [];
        $merged = [];

        foreach (self::DEFAULTS as $agent => $defaults) {
            $merged[$agent] = array_merge($defaults, is_array($saved[$agent] ?? null) ? $saved[$agent] : []);
        }

        return $merged;
    }

    public static function planIncludesAgents(User $user): bool
    {
        return (bool) ($user->billableUser()->activeSubscription?->plan?->agents_included ?? false);
    }

    public static function planAllowsAutopilot(User $user): bool
    {
        return self::planIncludesAgents($user) && (bool) ($user->billableUser()->activeSubscription?->plan?->agent_autopilot ?? false);
    }

    public static function enabled(User $user, string $agent): bool
    {
        return self::planIncludesAgents($user) && (bool) (self::for($user)[$agent]['enabled'] ?? false);
    }

    public static function get(User $user, string $agent, string $key, mixed $default = null): mixed
    {
        return self::for($user)[$agent][$key] ?? $default;
    }

    /**
     * @param  array<string, array<string, mixed>>  $settings
     */
    public static function save(User $user, array $settings): void
    {
        $owner = $user->billableUser();
        $clean = [];

        foreach (self::DEFAULTS as $agent => $defaults) {
            $clean[$agent] = [];

            foreach ($defaults as $key => $default) {
                $value = $settings[$agent][$key] ?? $default;
                $clean[$agent][$key] = is_bool($default) ? (bool) $value : (int) $value;
            }
        }

        $owner->forceFill(['agent_settings' => $clean])->save();
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public static function log(User $user, string $agent, string $action, string $summary, ?Model $subject = null, array $meta = [], string $status = AgentActivity::STATUS_DONE): AgentActivity
    {
        return AgentActivity::create([
            'user_id' => $user->billableUser()->id,
            'agent' => $agent,
            'action' => $action,
            'summary' => mb_substr($summary, 0, 500),
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'meta' => $meta ?: null,
            'status' => $status,
        ]);
    }
}
