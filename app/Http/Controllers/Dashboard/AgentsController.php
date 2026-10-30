<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\AgentActivity;
use App\Services\Agents\Customer\AgentApprovals;
use App\Support\Agents\AgentSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

/**
 * The AI Agents page: switch each agent on or off, see what they did and
 * approve what they suggest. Owner-only (agents act account-wide).
 */
class AgentsController extends Controller
{
    public function index(): View
    {
        $user = auth()->user();

        return view('dashboard.agents.index', [
            'settings' => AgentSettings::for($user),
            'included' => AgentSettings::planIncludesAgents($user),
            'autopilotAllowed' => AgentSettings::planAllowsAutopilot($user),
            'approvals' => AgentActivity::query()->where('user_id', $user->id)->where('status', AgentActivity::STATUS_NEEDS_APPROVAL)->latest()->get(),
            'activities' => AgentActivity::query()->where('user_id', $user->id)->where('status', '!=', AgentActivity::STATUS_NEEDS_APPROVAL)->latest()->limit(40)->get(),
            'weekCounts' => AgentActivity::query()->where('user_id', $user->id)->where('created_at', '>=', now()->subWeek())
                ->selectRaw('agent, count(*) as total')->groupBy('agent')->pluck('total', 'agent'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'agents' => ['array'],
            'agents.lead_hunter.target' => ['nullable', 'integer', 'min:10', 'max:1000'],
            'agents.content_autopilot.per_week' => ['nullable', 'integer', 'min:1', 'max:7'],
        ]);

        $submitted = (array) $request->input('agents', []);
        $settings = [];

        foreach (AgentSettings::DEFAULTS as $agent => $defaults) {
            foreach ($defaults as $key => $default) {
                // Checkboxes: unticked means off.
                $settings[$agent][$key] = is_bool($default) ? (bool) ($submitted[$agent][$key] ?? false) : ($submitted[$agent][$key] ?? $default);
            }
        }

        if (! AgentSettings::planAllowsAutopilot(auth()->user())) {
            $settings['reply_copilot']['autopilot'] = false;
        }

        AgentSettings::save(auth()->user(), $settings);

        return back()->with('success', 'Your agents are updated.');
    }

    public function approve(AgentActivity $activity, AgentApprovals $approvals): RedirectResponse
    {
        abort_unless($activity->user_id === auth()->id(), 404);

        try {
            return back()->with('success', $approvals->approve(auth()->user(), $activity));
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function dismiss(AgentActivity $activity): RedirectResponse
    {
        abort_unless($activity->user_id === auth()->id(), 404);

        $activity->update(['status' => AgentActivity::STATUS_DISMISSED]);

        return back()->with('success', 'Dismissed.');
    }
}
