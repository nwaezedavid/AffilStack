<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Earning;
use App\Models\Generation;
use App\Models\TrackedLink;
use App\Models\User;
use App\Services\Earnings\EarningsImportService;
use App\Support\ExchangeRates;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EarningsController extends Controller
{
    public function index(): View
    {
        $user = auth()->user();
        $currency = static::displayCurrency($user);
        $convert = fn (int $usdCents): int => ExchangeRates::fromUsdCents($usdCents, $currency);

        $entries = $user->earnings()->with(['offer', 'trackedLink', 'generation'])->latest('converted_at')->paginate(25);

        // Declined/reversed commissions stay listed but never count.
        $counted = fn () => $user->earnings()->where('status', '!=', 'declined');

        $totals = [
            'total_cents' => $convert((int) $counted()->sum('usd_cents')),
            'pending_cents' => $convert((int) $counted()->where('status', 'pending')->sum('usd_cents')),
            'approved_cents' => $convert((int) $counted()->where('status', 'approved')->sum('usd_cents')),
            'paid_cents' => $convert((int) $counted()->where('status', 'paid')->sum('usd_cents')),
        ];

        $totalClicks = (int) $user->trackedLinks()->sum('clicks_count');
        $epcCents = $totalClicks > 0 ? (int) round($totals['total_cents'] / $totalClicks) : 0;

        $byOffer = $user->offers()
            ->withSum(['earnings as earnings_usd' => fn ($query) => $query->where('earnings.user_id', $user->id)->where('earnings.status', '!=', 'declined')], 'usd_cents')
            ->withSum('trackedLinks as clicks', 'clicks_count')
            ->get()
            ->each(fn ($offer) => $offer->earnings_cents = $convert((int) $offer->earnings_usd))
            ->filter(fn ($offer) => $offer->earnings_cents > 0 || $offer->clicks > 0)
            ->sortByDesc('earnings_cents')
            ->values();

        $earningsByChannel = Earning::query()
            ->join('tracked_links', 'tracked_links.id', '=', 'earnings.tracked_link_id')
            ->where('earnings.user_id', $user->id)
            ->where('earnings.status', '!=', 'declined')
            ->selectRaw('tracked_links.module as module, sum(earnings.usd_cents) as earnings_usd')
            ->groupBy('tracked_links.module')
            ->pluck('earnings_usd', 'module');

        $clicksByChannel = TrackedLink::where('user_id', $user->id)
            ->selectRaw('module, sum(clicks_count) as clicks')
            ->groupBy('module')
            ->pluck('clicks', 'module');

        $byChannel = $clicksByChannel->keys()->merge($earningsByChannel->keys())->unique()
            ->map(fn ($module) => [
                'module' => $module,
                'earnings_cents' => $convert((int) ($earningsByChannel[$module] ?? 0)),
                'clicks' => (int) ($clicksByChannel[$module] ?? 0),
            ])
            ->sortByDesc('earnings_cents')
            ->values();

        // Content attribution: sales traced to the exact piece of content
        // through its own tracked link (network sub-IDs).
        $earningsByContent = Earning::query()
            ->where('user_id', $user->id)
            ->whereNotNull('generation_id')
            ->where('status', '!=', 'declined')
            ->selectRaw('generation_id, sum(usd_cents) as earnings_usd, count(*) as sales')
            ->groupBy('generation_id')
            ->orderByDesc('earnings_usd')
            ->limit(15)
            ->get()
            ->keyBy('generation_id');

        $byContent = Generation::query()
            ->with('offer:id,product_name')
            ->whereIn('id', $earningsByContent->keys())
            ->get()
            ->map(fn (Generation $generation) => [
                'generation' => $generation,
                'earnings_cents' => $convert((int) $earningsByContent[$generation->id]->earnings_usd),
                'sales' => (int) $earningsByContent[$generation->id]->sales,
                'clicks' => (int) TrackedLink::where('generation_id', $generation->id)->sum('clicks_count'),
            ])
            ->sortByDesc('earnings_cents')
            ->values();

        $unmatched = $user->earnings()->whereNull('offer_id')->where('status', '!=', 'declined')->latest('converted_at')->limit(20)->get();
        $unmatchedCount = $user->earnings()->whereNull('offer_id')->where('status', '!=', 'declined')->count();

        $offers = $user->offers()->orderBy('product_name')->get(['id', 'product_name']);
        $trackedLinks = $user->trackedLinks()->whereNull('generation_id')->with('offer')->get(['id', 'offer_id', 'module', 'code']);
        $networks = config('earnings.networks');
        $connections = $user->networkConnections()->get();
        $currencies = $user->earnings()->where('status', '!=', 'declined')->distinct()->pluck('currency');

        return view('dashboard.earnings.index', compact(
            'entries', 'totals', 'totalClicks', 'epcCents', 'byOffer', 'byChannel', 'byContent',
            'unmatched', 'unmatchedCount', 'offers', 'trackedLinks', 'networks', 'currency', 'connections', 'currencies',
        ));
    }

    /**
     * The currency totals are shown in: the customer's choice, or naira
     * for Nigerian accounts and US dollars for everyone else.
     */
    public static function displayCurrency(User $user): string
    {
        if (in_array($user->earnings_currency, ['USD', 'NGN'], true)) {
            return $user->earnings_currency;
        }

        return in_array(strtoupper((string) $user->country), ['NG', 'NIGERIA'], true) ? 'NGN' : 'USD';
    }

    public function currency(Request $request): RedirectResponse
    {
        $data = $request->validate(['currency' => ['required', 'in:USD,NGN']]);

        $request->user()->update(['earnings_currency' => $data['currency']]);

        return back()->with('success', 'Totals are now shown in '.($data['currency'] === 'NGN' ? 'naira' : 'US dollars').'.');
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
