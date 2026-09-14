<?php

namespace App\Services\Payments;

use App\Services\AI\AIGenerationException;
use App\Services\AI\AIProvider;

/**
 * Turns a failed PaymentGateway::verifyCredentials() result into a plain-
 * English explanation an admin (who may not be technical) can act on.
 * Only the gateway name and the sanitized failure message/HTTP status are
 * sent to the AI provider — never the credentials themselves, entered or
 * stored.
 */
class PaymentCredentialAdvisor
{
    public function __construct(protected AIProvider $ai) {}

    public function explain(string $gateway, string $failureMessage): string
    {
        $system = <<<'PROMPT'
            You help a (possibly non-technical) admin of a SaaS platform fix a
            failed payment gateway credential check. They just clicked "Verify
            credentials" for either Stripe or Flutterwave and it failed.

            Given the gateway name and the raw error, explain in plain English
            (2-4 short sentences, no markdown, no headers) what most likely went
            wrong and the specific next step to fix it — e.g. copying the wrong
            key type (publishable vs. secret, test vs. live), a key with leading
            or trailing whitespace, using a webhook signing secret in the wrong
            field, or the account not being fully activated with that provider.
            Never suggest anything that requires code changes — this admin only
            has the dashboard's own credential fields to work with.
            PROMPT;

        $userPrompt = "Gateway: {$gateway}\nError: {$failureMessage}";

        try {
            return trim($this->ai->generateText($system, $userPrompt, ['temperature' => 0.3]));
        } catch (AIGenerationException) {
            return "We couldn't reach the AI assistant right now. The raw error was: {$failureMessage}";
        }
    }
}
