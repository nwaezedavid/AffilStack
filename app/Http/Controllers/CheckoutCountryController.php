<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The manual escape hatch for CheckoutCountryResolver's auto-detection —
 * shown as a "Not in Nigeria? / Paying from Nigeria?" link on the pricing,
 * signup, and billing pages (audit item #3). Only "NG" vs. "not NG" is
 * meaningful anywhere this is read, so any other code collapses to a
 * single "US" bucket rather than pretending to support real country
 * selection.
 */
class CheckoutCountryController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate(['country' => ['required', 'string', 'size:2']]);

        $request->session()->put('checkout_country', strtoupper($validated['country']) === 'NG' ? 'NG' : 'US');

        return back();
    }
}
