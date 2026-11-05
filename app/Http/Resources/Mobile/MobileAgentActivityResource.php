<?php

namespace App\Http\Resources\Mobile;

use App\Models\AgentActivity;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One entry in the AI agents' feed — or a suggestion waiting for approval.
 *
 * @mixin AgentActivity
 */
class MobileAgentActivityResource extends JsonResource
{
    public const AGENT_NAMES = [
        'reply_copilot' => 'Reply Copilot',
        'lead_hunter' => 'Lead Hunter',
        'commission_watchdog' => 'Commission Watchdog',
        'content_autopilot' => 'Content Autopilot',
        'offer_scout' => 'Offer Scout',
        'weekly_briefing' => 'Weekly briefing',
        'churn_saver' => 'Account check-in',
        'upgrade_nudge' => 'Plan tips',
    ];

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $approval = data_get($this->meta, 'approval');

        return [
            'id' => $this->id,
            'agent' => $this->agent,
            'agent_name' => self::AGENT_NAMES[$this->agent] ?? ucwords(str_replace('_', ' ', (string) $this->agent)),
            'action' => $this->action,
            'summary' => $this->summary,
            'status' => $this->status,
            'needs_approval' => $this->status === AgentActivity::STATUS_NEEDS_APPROVAL,
            'approve_label' => $approval === 'research_program' ? 'Research it' : 'Approve',
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
