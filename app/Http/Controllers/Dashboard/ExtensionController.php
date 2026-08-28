<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\ApiToken;
use App\Models\ResearchClip;
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
    public function index(): View
    {
        $user = auth()->user();

        $tokens = $user->apiTokens()->latest()->get();
        $clips = $user->researchClips()->with('offer')->latest()->get();
        $offers = $user->offers()->orderBy('product_name')->get();

        return view('dashboard.extension.index', compact('tokens', 'clips', 'offers'));
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
        $zipPath = storage_path('app/affilistack-extension.zip');

        if (file_exists($zipPath)) {
            unlink($zipPath);
        }

        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        /** @var \SplFileInfo $file */
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($sourceDir)) as $file) {
            if ($file->isDir()) {
                continue;
            }

            $relativePath = 'affilistack-extension/'.substr($file->getPathname(), strlen($sourceDir) + 1);
            $zip->addFile($file->getPathname(), $relativePath);
        }

        $zip->close();

        return response()->download($zipPath, 'affilistack-extension.zip')->deleteFileAfterSend();
    }
}
