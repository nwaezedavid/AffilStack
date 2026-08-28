<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Earning;
use App\Models\TrackedLink;
use App\Services\Earnings\EarningsImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EarningsController extends Controller
{
    public function index(): View
    {
        $user = auth()->user();

        $entries = $user->earnings()->with(['offer', 'trackedLink'])->latest('converted_at')->paginate(25);

        $totals = [
            'total_cents' => (int) $user->earnings()->sum('amount_cents'),
            'pending_cents' => (int) $user->earnings()->where('status', 'pending')->sum('amount_cents'),
            'approved_cents' => (int) $user->earnings()->where('status', 'approved')->sum('amount_cents'),
            'paid_cents' => (int) $user->earnings()->where('status', 'paid')->sum('amount_cents'),
        ];

        $totalClicks = (int) $user->trackedLinks()->sum('clicks_count');
        $epcCents = $totalClicks > 0 ? (int) round($totals['total_cents'] / $totalClicks) : 0;

        $byOffer = $user->offers()
            ->withSum('earnings as earnings_cents', 'amount_cents')
            ->withSum('trackedLinks as clicks', 'clicks_count')
            ->get()
            ->filter(fn ($offer) => $offer->earnings_cents > 0 || $offer->clicks > 0)
            ->sortByDesc('earnings_cents')
            ->values();

        $earningsByChannel = Earning::query()
            ->join('tracked_links', 'tracked_links.id', '=', 'earnings.tracked_link_id')
            ->where('earnings.user_id', $user->id)
            ->selectRaw('tracked_links.module as module, sum(earnings.amount_cents) as earnings_cents')
            ->groupBy('tracked_links.module')
            ->pluck('earnings_cents', 'module');

        $clicksByChannel = TrackedLink::where('user_id', $user->id)
            ->selectRaw('module, sum(clicks_count) as clicks')
            ->groupBy('module')
            ->pluck('clicks', 'module');

        $byChannel = $clicksByChannel->keys()->merge($earningsByChannel->keys())->unique()
            ->map(fn ($module) => [
                'module' => $module,
                'earnings_cents' => (int) ($earningsByChannel[$module] ?? 0),
                'clicks' => (int) ($clicksByChannel[$module] ?? 0),
            ])
            ->sortByDesc('earnings_cents')
            ->values();

        $unmatched = $user->earnings()->whereNull('offer_id')->latest('converted_at')->limit(20)->get();
        $unmatchedCount = $user->earnings()->whereNull('offer_id')->count();

        $offers = $user->offers()->orderBy('product_name')->get(['id', 'product_name']);
        $trackedLinks = $user->trackedLinks()->with('offer')->get(['id', 'offer_id', 'module', 'code']);
        $networks = config('earnings.networks');

        return view('dashboard.earnings.index', compact(
            'entries', 'totals', 'totalClicks', 'epcCents', 'byOffer', 'byChannel',
            'unmatched', 'unmatchedCount', 'offers', 'trackedLinks', 'networks',
        ));
    }

    public function storeManual(Request $request, EarningsImportService $service): RedirectResponse
    {
        $validated = $request->validate([
            'network' => ['required', 'string', 'in:'.implode(',', array_keys(config('earnings.networks')))],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'currency' => ['nullable', 'string', 'size:3'],
            'status' => ['required', 'in:pending,approved,paid'],
            'converted_at' => ['required', 'date', 'before_or_equal:today'],
            'tracked_link_id' => ['nullable', 'integer', 'exists:tracked_links,id'],
            'offer_id' => ['nullable', 'integer', 'exists:offers,id'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $service->createManual(auth()->user(), $validated);

        return back()->with('success', 'Earning added.');
    }

    public function storeImport(Request $request, EarningsImportService $service): RedirectResponse
    {
        $validated = $request->validate([
            'network' => ['required', 'string', 'in:'.implode(',', array_keys(config('earnings.networks')))],
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
        ]);

        $stats = $service->import(auth()->user(), $validated['file'], $validated['network']);

        $message = "Imported {$stats['imported']} row(s) — {$stats['matched']} matched to a link automatically";

        if ($stats['unmatched'] > 0) {
            $message .= ", {$stats['unmatched']} unmatched (assign them below)";
        }

        if ($stats['declined'] > 0) {
            $message .= ", {$stats['declined']} declined/reversed row(s) skipped";
        }

        if ($stats['skipped'] > 0) {
            $message .= ", {$stats['skipped']} row(s) couldn't be read (missing amount/date, or already imported)";
        }

        return back()->with('success', $message.'.');
    }

    public function assignOffer(Request $request, Earning $earning): RedirectResponse
    {
        abort_unless($earning->user_id === auth()->id(), 403);

        $validated = $request->validate([
            'offer_id' => ['required', 'integer', 'exists:offers,id'],
        ]);

        abort_unless(auth()->user()->offers()->whereKey($validated['offer_id'])->exists(), 403);

        $earning->update(['offer_id' => $validated['offer_id']]);

        return back()->with('success', 'Earning assigned to that offer.');
    }

    public function destroy(Earning $earning): RedirectResponse
    {
        abort_unless($earning->user_id === auth()->id(), 403);

        $earning->delete();

        return back()->with('success', 'Earning removed.');
    }
}
