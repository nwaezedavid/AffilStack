<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Generation;
use App\Services\Credits\InsufficientCreditsException;
use App\Services\Modules\LocalizationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class LocalizationController extends Controller
{
    public function store(Request $request, Generation $generation, LocalizationService $service): RedirectResponse
    {
        abort_unless($generation->user_id === auth()->id(), 403);

        $validated = $request->validate([
            'target_market' => ['required', Rule::in(array_keys(config('localization.markets')))],
        ]);

        try {
            $service->queue(auth()->user(), $generation, $validated['target_market']);
        } catch (InsufficientCreditsException|InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        $marketLabel = config('localization.markets.'.$validated['target_market'].'.label');

        return back()->with('success', "Localizing for {$marketLabel} — running in the background.");
    }
}
