<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\ApiToken;
use App\Models\Offer;
use App\Models\ResearchClip;
use App\Support\ResearchPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

/**
 * Feature 11 (Phase 3 backlog, item 11): the browser capture extension's
 * dashboard side — issuing/revoking the bearer tokens it authenticates
 * with (App\Models\ApiToken), reviewing what it's captured (ResearchClip),
 * and downloading the extension itself. Owner-only: excluded from
 * config('agency.seat_allowed_routes') — see RestrictAgencySeats — since a
 * seat generating its own token would let it capture pages unrelated to
 * its one assigned offer, the exact scope leak item 10 was built to close.
 */
class ExtensionController extends Controller
{
    /**
     * The API address baked into resources/browser-extension/config.js —
     * the Chrome Web Store build uses it as-is.
     */
    public const STORE_BUILD_API_BASE_URL = 'https://affilstack.com/api';

    /**
     * This site's API address, from APP_URL rather than the request host,
     * so a visit through www. or a proxy can't change what gets baked in.
     */
    public static function apiBaseUrl(): string
    {
        return rtrim((string) config('app.url'), '/').'/api';
    }

    public function index(): View
    {
        $user = auth()->user();

        $tokens = $user->apiTokens()->latest()->get();
        $clips = $user->researchClips()->with('offer')->latest()->get();
        $offers = $user->offers()->orderBy('product_name')->get();

        // "Connected" = the extension has called the API with one of this
        // account's tokens at least once (ApiTokenAuth stamps last_used_at).
        $lastUsedToken = $tokens->whereNotNull('last_used_at')->sortByDesc('last_used_at')->first();

        $stats = [
            'contacts' => $user->crmContacts()->where('raw_data->captured_via', 'chrome_extension')->count(),
            'replies' => $user->linkedinReplyDrafts()->count(),
            'clips' => $clips->count(),
        ];

        // The products' own Maps/LinkedIn keywords (agent 10's research), so
        // the page shows exactly what to search next.
        $keywordOffers = $user->offers()
            ->where('is_sandbox', false)
            ->where('status', 'ready')
            ->latest()
            ->limit(3)
            ->get()
            ->map(fn (Offer $offer) => [
                'offer' => $offer,
                'research' => new ResearchPresenter($offer, $user),
                'list' => $user->crmLists()->where('offer_id', $offer->id)->first(),
            ])
            ->filter(fn (array $item) => $item['research']->mapsKeywords() !== [] || array_merge(...array_values($item['research']->linkedinKeywords())) !== [])
            ->values();

        return view('dashboard.extension.index', compact('tokens', 'clips', 'offers', 'lastUsedToken', 'stats', 'keywordOffers'));
    }

    public function createToken(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $result = ApiToken::generate(auth()->user(), $validated['name']);

        return back()->with(
            'success',
            "Token created — paste this into the extension's options now, it won't be shown again:\n{$result['plainText']}"
        );
    }

    public function revokeToken(ApiToken $token): RedirectResponse
    {
        abort_unless($token->user_id === auth()->id(), 403);

        $token->delete();

        return back()->with('success', 'Token revoked — the extension using it will need a new one.');
    }

    /**
     * Attaches a previously-unattached clip to one of the user's offers, or
     * detaches it (offer_id => null) — the extension can attach at capture
     * time too, but a clip captured before an offer existed needs this.
     */
    public function attachClip(Request $request, ResearchClip $clip): RedirectResponse
    {
        abort_unless($clip->user_id === auth()->id(), 403);

        $validated = $request->validate([
            'offer_id' => ['nullable', 'integer'],
        ]);

        if (! empty($validated['offer_id'])) {
            abort_unless(auth()->user()->offers()->whereKey($validated['offer_id'])->exists(), 403);
        }

        $clip->update(['offer_id' => $validated['offer_id'] ?? null]);

        return back()->with('success', 'Clip updated.');
    }

    public function destroyClip(ResearchClip $clip): RedirectResponse
    {
        abort_unless($clip->user_id === auth()->id(), 403);

        $clip->delete();

        return back()->with('success', 'Clip removed.');
    }

    /**
     * Zips resources/browser-extension/ on the fly rather than shipping a
     * committed binary — the download is always exactly the source in this
     * repo, nothing to keep in sync.
     */
    public function download(): BinaryFileResponse
    {
        $sourceDir = resource_path('browser-extension');
        // A fresh file per download, so two simultaneous downloads can't
        // overwrite or delete each other's zip mid-send.
        $zipPath = tempnam(sys_get_temp_dir(), 'affilstack-extension-');

        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        /** @var \SplFileInfo $file */
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($sourceDir)) as $file) {
            if ($file->isDir()) {
                continue;
            }

            $sourceRelative = str_replace('\\', '/', substr($file->getPathname(), strlen($sourceDir) + 1));

            // store/ holds the Chrome Web Store listing texts — for the
            // owner, not part of the extension customers install.
            if (str_starts_with($sourceRelative, 'store/')) {
                continue;
            }

            $relativePath = 'affilstack-extension/'.$sourceRelative;

            // Point the downloaded copy at this site, so customers only
            // paste a token — no API URL to type in.
            if ($file->getFilename() === 'config.js') {
                $zip->addFromString($relativePath, str_replace(
                    self::STORE_BUILD_API_BASE_URL,
                    self::apiBaseUrl(),
                    (string) file_get_contents($file->getPathname()),
                ));

                continue;
            }

            $zip->addFile($file->getPathname(), $relativePath);
        }

        $zip->close();

        return response()->download($zipPath, 'affilstack-extension.zip')->deleteFileAfterSend();
    }
}
