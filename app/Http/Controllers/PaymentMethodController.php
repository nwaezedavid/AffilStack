<?php

namespace App\Http\Controllers;

use App\Models\PaymentMethod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

/**
 * Manages the saved payment methods listed on the billing page (audit item
 * #2). There's no "add" action here — every row is captured passively by
 * PaymentMethodRecorder from a successful charge; a user can only pick which
 * one is the default or remove one they no longer want kept on file.
 */
class PaymentMethodController extends Controller
{
    public function setDefault(PaymentMethod $paymentMethod): RedirectResponse
    {
        Gate::allowIf(fn ($user) => $paymentMethod->user_id === $user->id);

        $paymentMethod->user->paymentMethods()->update(['is_default' => false]);
        $paymentMethod->update(['is_default' => true]);

        return redirect()->route('billing.index')->with('success', 'Default payment method updated.');
    }

    public function destroy(PaymentMethod $paymentMethod): RedirectResponse
    {
        Gate::allowIf(fn ($user) => $paymentMethod->user_id === $user->id);

        $wasDefault = $paymentMethod->is_default;
        $paymentMethod->delete();

        if ($wasDefault) {
            $paymentMethod->user->paymentMethods()->first()?->update(['is_default' => true]);
        }

        return redirect()->route('billing.index')->with('success', 'Payment method removed.');
    }
}
