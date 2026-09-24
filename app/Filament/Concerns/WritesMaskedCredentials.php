<?php

namespace App\Filament\Concerns;

/**
 * Every Filament settings Page whose form has a `->password()` field bound
 * to an encrypted credential (API key, client secret, webhook secret, …)
 * must use this trait for that field's mount/persist handling.
 *
 * The problem: a Livewire component's public properties (here, `$data`,
 * via `->statePath('data')`) are serialized into the page's `wire:snapshot`
 * on every render — plain, view-source-visible text in the response HTML,
 * regardless of the widget itself rendering as a masked `type="password"`
 * input. Calling `$this->form->fill(['secret' => $model->credential('secret')])`
 * on mount() puts the real decrypted secret into that snapshot the moment
 * the page loads, before the admin does anything — see
 * tests/Feature/PaymentGatewaySettingsPageTest.php's snapshot assertions
 * for a reproduction.
 *
 * The fix, applied consistently everywhere this trait is used: a secret
 * field's form state is NEVER filled with its real stored value. It mounts
 * blank; maskedPlaceholder() shows a static "already set" hint (a boolean
 * signal, not the secret) when one exists. Saving a blank secret field then
 * means "the admin didn't retype it", not "clear it" — mergeMaskedCredentials()
 * preserves whatever is already stored for exactly those keys, while still
 * clearing (and overwriting) any other, non-secret key the way this app's
 * settings pages always have. This is the same write-only, "blank = keep
 * current" pattern every SaaS dashboard uses for a masked API key field.
 */
trait WritesMaskedCredentials
{
    /**
     * Static placeholder text for a masked secret TextInput. Deliberately
     * never derived from the secret itself (not its length, not a partial
     * value) — only whether one is already stored.
     */
    protected function maskedPlaceholder(bool $isAlreadySet): ?string
    {
        return $isAlreadySet ? 'Already set — leave blank to keep it, or enter a new value to replace it.' : null;
    }

    /**
     * Merges a settings page's submitted credential fields onto the
     * model's EXISTING stored `credentials`, so that:
     *
     * - a blank/empty value for one of $secretKeys is left completely
     *   alone. Every secret field mounts blank (see maskedPlaceholder()),
     *   so a blank submission only ever means "the admin didn't retype
     *   this" — never "clear this credential".
     * - a blank/empty value for any other key clears that key, matching
     *   this app's settings pages' existing (pre-fix) behaviour for
     *   non-secret fields, which mount with their real value and so can
     *   legitimately be cleared by the admin.
     * - any non-blank value, secret or not, overwrites whatever was
     *   stored.
     *
     * @param  array<string, mixed>  $existing  The model's current credentials (e.g. $settings->credentials ?? []).
     * @param  array<string, mixed>  $submitted  The form's submitted fields for this credential set.
     * @param  array<int, string>  $secretKeys  Which of $submitted's keys are masked secret fields.
     * @return array<string, mixed>
     */
    protected function mergeMaskedCredentials(array $existing, array $submitted, array $secretKeys): array
    {
        $merged = $existing;

        foreach ($submitted as $key => $value) {
            $isBlank = $value === null || $value === '';

            if ($isBlank && in_array($key, $secretKeys, true)) {
                continue;
            }

            if ($isBlank) {
                unset($merged[$key]);

                continue;
            }

            $merged[$key] = $value;
        }

        return $merged;
    }
}
