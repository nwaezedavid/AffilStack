<?php

namespace App\Http\Controllers\Api\V1\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Resources\Mobile\MobileAgentActivityResource;
use App\Models\AgentActivity;
use App\Services\Agents\Customer\AgentApprovals;
use App\Support\Agents\AgentSettings;
use App\Support\Mobile\SeatAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * AI agents in the phone app: suggestions waiting for approval, the
 * activity feed and this week's counts — approve or dismiss with the same
 * AgentApprovals service as the dashboard. Reply Copilot's drafts live in
 * the inbox, as on the website.
 */
class AgentsController extends Controller
{
    /**
     * AffilStack's own revenue agents (upgrade suggestions, "keep your
     * account" check-ins) are about buying, so they stay out of the app:
     * Apple and Google don't allow it to point to purchases made elsewhere.
     *
     * @var array<int, string>
     */
    public const HIDDEN_AGENTS = ['upgrade_nudge', 'churn_saver'];

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        SeatAccess::authorize($user, 'agents.index');

        $activities = fn () => AgentActivity::query()->where('user_id', $user->id)->whereNotIn('agent', self::HIDDEN_AGENTS);

        return response()->json([
            'included' => AgentSettings::planIncludesAgents($user),
            'approvals' => MobileAgentActivityResource::collection(
                $activities()->where('status', AgentActivity::STATUS_NEEDS_APPROVAL)->latest()->get()
            ),
            'activity' => MobileAgentActivityResource::collection(
                $activities()->where('status', '!=', AgentActivity::STATUS_NEEDS_APPROVAL)->latest()->limit(40)->get()
            ),
            'week' => $activities()->where('created_at', '>=', now()->subWeek())
                ->selectRaw('agent, count(*) as total')->groupBy('agent')->pluck('total', 'agent'),
        ]);
    }

    public function approve(Request $request, AgentActivity $activity, AgentApprovals $approvals): JsonResponse
    {
        $user = $request->user();
        SeatAccess::authorize($user, 'agents.activities.approve');
        abort_unless($activity->user_id === $user->id, 404);

        try {
            $message = $approvals->approve($user, $activity);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => $message, 'data' => new MobileAgentActivityResource($activity->fresh())]);
    }

    public function dismiss(Request $request, AgentActivity $activity): JsonResponse
    {
        $user = $request->user();
        SeatAccess::authorize($user, 'agents.activities.dismiss');
        abort_unless($activity->user_id === $user->id, 404);

        $activity->update(['status' => AgentActivity::STATUS_DISMISSED]);

        return response()->json(['message' => 'Dismissed.', 'data' => new MobileAgentActivityResource($activity)]);
    }
}
