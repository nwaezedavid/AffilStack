<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Generation;
use App\Models\Offer;
use App\Services\Credits\InsufficientCreditsException;
use App\Services\Modules\UgcService;
use App\Services\Modules\UgcVideoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use RuntimeException;

class UgcController extends Controller
{
    /**
     * @param  array{angles_generation_id?: int, angle_index?: int}|null  $context
     */
    protected function run(Offer $offer, string $method, UgcService $service, ?array $context = null): RedirectResponse
    {
        abort_unless($offer->isAccessibleBy(auth()->user()), 403);

        if (! auth()->user()->canUseChannel('ugc')) {
            return back()->with('error', 'The UGC module isn\'t included in your current plan — upgrade to unlock it.');
        }

        try {
            $service->queue(auth()->user(), $offer, $method, $context);
        } catch (InsufficientCreditsException $e) {
            return back()->with('error', 'Not enough credits for that UGC generation. Upgrade your plan or buy a credit top-up.');
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('offers.show', $offer)->with('success', "UGC content queued — we'll notify you the moment it's ready.");
    }

    public function angles(Offer $offer, UgcService $service): RedirectResponse
    {
        return $this->run($offer, 'angles', $service);
    }

    public function content(Request $request, Offer $offer, UgcService $service): RedirectResponse
    {
        $request->validate([
            'angles_generation_id' => ['required', 'integer'],
            'angle_index' => ['required', 'integer', 'min:0'],
        ]);

        // Cast explicitly: validate() confirms the input is integer-shaped
        // but leaves it a string, and Generation::input is a raw JSON cast —
        // a stored "1" would silently fail the strict === comparison the
        // offer view uses to detect an already-generated angle.
        $context = [
            'angles_generation_id' => $request->integer('angles_generation_id'),
            'angle_index' => $request->integer('angle_index'),
        ];

        return $this->run($offer, 'content', $service, $context);
    }

    public function video(Request $request, Offer $offer, UgcVideoService $service): RedirectResponse
    {
        abort_unless($offer->isAccessibleBy(auth()->user()), 403);

        if (! auth()->user()->canUseChannel('ugc')) {
            return back()->with('error', 'The UGC module isn\'t included in your current plan — upgrade to unlock it.');
        }

        $request->validate([
            'content_generation_id' => ['required', 'integer'],
            'avatar_id' => ['required', 'string'],
            'voice_id' => ['required', 'string'],
        ]);

        $contentGeneration = Generation::query()->findOrFail($request->integer('content_generation_id'));

        try {
            $service->queue(
                auth()->user(),
                $offer,
                $contentGeneration,
                $request->string('avatar_id')->toString(),
                $request->string('voice_id')->toString(),
            );
        } catch (InsufficientCreditsException $e) {
            return back()->with('error', 'Not enough credits to generate a UGC video. Upgrade your plan or buy a credit top-up.');
        } catch (InvalidArgumentException|RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('offers.show', $offer)->with('success', 'UGC video queued — this can take a few minutes; we\'ll notify you when it\'s ready.');
    }
}
