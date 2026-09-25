<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\WebhookEndpoint;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * API roadmap item #9 — backs the Zapier platform app's REST Hook triggers
 * (see integrations/zapier/triggers/*.js). A Zap subscribing to "New offer
 * ready" or "New CRM contact" calls store() with its own catch-hook URL
 * and the single event it wants; Zapier calls destroy() when the Zap is
 * turned off or deleted. Both reuse WebhookEndpoint exactly like a
 * hand-added dashboard webhook — see ApiAccessController::storeWebhook(),
 * which this mirrors — so a user sees, and can revoke, their own Zapier
 * connection right on the API Access page like any other endpoint. There
 * is nothing Zapier-specific persisted anywhere.
 *
 * Owner-only, matching every other webhook-management action in this app:
 * a team seat's own token gets the same explicit rejection here it would
 * get from the dashboard.
 */
class ZapierSubscriptionsController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        abort_if($request->user()->isSeat(), 403);

        $validated = $request->validate([
            'target_url' => ['required', 'url', 'max:2048'],
            'event' => ['required', 'string', Rule::in(array_keys(config('webhooks.events')))],
        ]);

        $webhook = $request->user()->webhookEndpoints()->create([
            'url' => $validated['target_url'],
            'events' => [$validated['event']],
            'secret' => WebhookEndpoint::generateSecret(),
            'is_active' => true,
        ]);

        // Zapier's REST Hook contract expects an opaque "id" it can hand
        // back unchanged to DELETE /subscriptions/{id} on unsubscribe.
        return response()->json(['id' => $webhook->id], 201);
    }

    public function destroy(Request $request, WebhookEndpoint $webhook): JsonResponse
    {
        abort_unless($webhook->user_id === $request->user()->id, 404);

        $webhook->delete();

        return response()->json(['id' => $webhook->id]);
    }
}
