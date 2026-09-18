<?php

namespace App\Services\Settings;

use App\Services\AI\AIGenerationException;
use App\Services\AI\AIProvider;

/**
 * The general-purpose version of Payments\PaymentCredentialAdvisor (audit
 * item #6 — "every admin setting should have an active AI Agent assisting
 * and verifying every connection") — turns any settings area's failed or
 * incomplete connection into a plain-English explanation a non-technical
 * admin can act on. Powers the "Explain with AI" action on the Connections
 * Health page, and can be reused directly from any individual settings
 * page the same way PaymentGatewaySettings already uses its own advisor.
 *
 * Only the connection's name and its already-sanitized status message are
 * sent — never a credential, entered or stored.
 */
class ConnectionAdvisor
{
    public function __construct(protected AIProvider $ai) {}

    public function explain(string $connectionName, string $whatItDoes, string $statusMessage): string
    {
        $system = <<<'PROMPT'
            You help a (possibly non-technical) admin of a SaaS platform get an
            integration working from its settings page in the admin dashboard.
            You'll be given the integration's name, what it's for, and its
            current status (a failed API check, or "not configured yet").

            Explain in plain English (2-4 short sentences, no markdown, no
            headers) what's most likely wrong or missing and the specific next
            step to fix it from the dashboard fields available — e.g. which
            credential to paste from which page of the provider's own
            dashboard, a key copied with extra whitespace, using the wrong key
            type, or an OAuth app that still needs a redirect URI added on the
            provider's side. If the integration can only really be confirmed
            by testing it as a real user would (many OAuth "Connect" buttons
            can't be verified with just a stored key), say exactly what
            frontend action to click through to confirm it. Never suggest
            anything that requires code changes — this admin only has the
            dashboard's own fields and buttons to work with.
            PROMPT;

        $userPrompt = "Integration: {$connectionName}\nWhat it's for: {$whatItDoes}\nCurrent status: {$statusMessage}";

        try {
            return trim($this->ai->generateText($system, $userPrompt, ['temperature' => 0.3]));
        } catch (AIGenerationException) {
            return "We couldn't reach the AI assistant right now. Current status: {$statusMessage}";
        }
    }
}
