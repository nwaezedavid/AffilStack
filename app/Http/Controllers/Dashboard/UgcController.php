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
     * ~170 spoken words — the top of the length UgcService's own
     * video_script prompt asks for.
     */
    public const MAX_VIDEO_SCRIPT_CHARACTERS = 1200;

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

        return redirect()->route('offers.feature', [$offer, 'ugc'])->with('success', "UGC content queued — we'll notify you the moment it's ready.");
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
            'avatar_id' => ['nullable', 'string', 'max:100'],
            'voice_id' => ['nullable', 'string', 'max:100'],
            'avatar_name' => ['nullable', 'string', 'max:120'],
            'voice_name' => ['nullable', 'string', 'max:120'],
        ]);

        // Cast explicitly: validate() confirms the input is integer-shaped
        // but leaves it a string, and Generation::input is a raw JSON cast —
        // a stored "1" would silently fail the strict === comparison the
        // offer view uses to detect an already-generated angle.
        $context = [
            'angles_generation_id' => $request->integer('angles_generation_id'),
            'angle_index' => $request->integer('angle_index'),
            'avatar_id' => $request->input('avatar_id'),
            'voice_id' => $request->input('voice_id'),
            'avatar_name' => $request->input('avatar_name'),
            'voice_name' => $request->input('voice_name'),
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
            // The user's own edit of the AI's spoken script. Capped so an
            // edited video stays inside the ~60s length the ugc_video
            // credit price (config/credits.php) was sized against.
            'video_script' => ['nullable', 'string', 'min:20', 'max:'.self::MAX_VIDEO_SCRIPT_CHARACTERS],
        ]);

        $contentGeneration = Generation::query()->findOrFail($request->integer('content_generation_id'));

        try {
            $service->queue(
                auth()->user(),
                $offer,
                $contentGeneration,
                $request->string('avatar_id')->toString(),
                $request->string('voice_id')->toString(),
                $request->filled('video_script') ? $request->string('video_script')->toString() : null,
            );
        } catch (InsufficientCreditsException $e) {
            return back()->with('error', 'Not enough credits to generate a UGC video. Upgrade your plan or buy a credit top-up.');
        } catch (InvalidArgumentException|RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('offers.feature', [$offer, 'ugc'])->with('success', 'UGC video queued — this can take a few minutes; we\'ll notify you when it\'s ready.');
    }
}
