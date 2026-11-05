<?php

namespace App\Services\Modules;

use App\Jobs\CheckHiggsfieldVideo;
use App\Jobs\RunQuickVideo;
use App\Models\Generation;
use App\Models\HeyGenSetting;
use App\Models\Offer;
use App\Models\User;
use App\Models\VideoStudioSetting;
use App\Services\AI\AIProvider;
use App\Services\Credits\CreditManager;
use App\Services\Credits\InsufficientCreditsException;
use App\Services\Video\HeyGenCatalog;
use App\Services\Video\HiggsfieldClient;
use App\Services\Video\ProductImageResolver;
use App\Services\Video\VideoStyles;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * One-click videos: the customer picks a style and presses Create.
 * AffilStack writes the script and the posting captions from the product
 * research, picks the presenter and voice (HeyGen styles) or the product
 * picture (Higgsfield styles), renders the video and files it with the
 * product's other content, ready to publish. Typing is optional: a
 * customer may edit the script or choose the presenter themselves.
 *
 * Billing: the style's price (VideoStudioSetting::style()) is charged once,
 * only when the finished video is saved. The script it comes with is
 * included in that price.
 */
class QuickVideoService
{
    /** How long a Higgsfield render may take before AffilStack gives up on it. */
    public const HIGGSFIELD_TIMEOUT_MINUTES = 30;

    public function __construct(
        protected AIProvider $ai,
        protected CreditManager $credits,
        protected UgcVideoService $heygen,
        protected HeyGenCatalog $catalog,
        protected ProductImageResolver $images,
    ) {}

    /**
     * The styles a customer can use right now: switched on in the admin,
     * with their engine connected.
     *
     * @return array<string, array<string, mixed>>
     */
    public function availableStyles(): array
    {
        $settings = VideoStudioSetting::current();
        $heygenReady = HeyGenSetting::current()->isReady();
        $higgsfieldReady = $settings->higgsfieldReady();

        $styles = [];

        foreach (VideoStyles::STYLES as $key => $style) {
            $config = $settings->style($key);
            $ready = $style['provider'] === VideoStyles::HEYGEN ? $heygenReady : $higgsfieldReady;

            $styles[$key] = $style + $config + ['available' => $config['enabled'] && $ready];
        }

        return $styles;
    }

    /**
     * @param  array{script?: ?string, avatar_id?: ?string, voice_id?: ?string, image?: ?UploadedFile}  $options
     *
     * @throws RuntimeException|InsufficientCreditsException with a customer-safe message
     */
    public function queue(User $user, Offer $offer, string $style, array $options = []): Generation
    {
        if (! VideoStyles::exists($style)) {
            throw new RuntimeException('Pick one of the video styles.');
        }

        $available = $this->availableStyles()[$style];

        if (! $available['available']) {
            throw new RuntimeException("{$available['label']} videos aren't available right now. Try another style, or check back soon.");
        }

        $cost = (int) $available['credits'];

        if (! $this->credits->hasEnough($user, $cost)) {
            throw new InsufficientCreditsException("Need {$cost} credits to create this video.");
        }

        $imagePath = null;

        if (VideoStyles::usesHiggsfield($style)) {
            $imagePath = ($options['image'] ?? null) instanceof UploadedFile
                ? $this->images->storeUpload($offer, $options['image'])
                : $this->images->resolve($offer);

            if ($imagePath === null) {
                throw new RuntimeException('We couldn\'t find a picture of this product on its website. Add a product photo below and press Create again.');
            }
        }

        $script = trim((string) ($options['script'] ?? ''));

        return DB::transaction(function () use ($user, $offer, $style, $available, $cost, $imagePath, $script, $options) {
            $content = $offer->generations()->create([
                'user_id' => $user->id,
                'module' => 'ugc_content',
                'input' => ['offer_id' => $offer->id, 'quick_style' => $style],
                'credits_spent' => 0,
                'status' => 'queued',
            ]);

            $video = $offer->generations()->create([
                'user_id' => $user->id,
                'module' => 'ugc_video',
                'input' => array_filter([
                    'content_generation_id' => $content->id,
                    'quick_style' => $style,
                    'provider' => $available['provider'],
                    'credit_cost' => $cost,
                    'duration' => $available['duration'],
                    'user_script' => $script !== '' ? $script : null,
                    'avatar_id' => $options['avatar_id'] ?? null,
                    'voice_id' => $options['voice_id'] ?? null,
                    'product_image_path' => $imagePath,
                ], fn ($value) => $value !== null),
                'credits_spent' => 0,
                'status' => 'queued',
            ]);

            RunQuickVideo::dispatch($video);

            return $video;
        });
    }

    /**
     * Step 1 (in the queue): write the script and captions, then start the
     * render. HeyGen renders finish inside this job; Higgsfield renders are
     * followed up by CheckHiggsfieldVideo.
     */
    public function run(Generation $video): void
    {
        $content = Generation::find($video->input['content_generation_id'] ?? null);
        $style = (string) ($video->input['quick_style'] ?? '');

        if (! $content || ! VideoStyles::exists($style)) {
            $this->fail($video, 'This video request is incomplete — please create it again.', $content);

            return;
        }

        if (! $this->credits->hasEnough($video->user, (int) $video->input['credit_cost'])) {
            $this->fail($video, 'Insufficient credits at processing time.', $content);

            return;
        }

        try {
            $written = $this->writeContent($video, $style);
        } catch (Throwable $e) {
            report($e);
            $this->fail($video, 'The AI writer is unavailable right now — please try again in a minute.', $content);

            return;
        }

        $content->update([
            'output' => $written['script'] ?? '',
            'output_meta' => $written,
            'status' => 'completed',
        ]);

        if (VideoStyles::get($style)['provider'] === VideoStyles::HEYGEN) {
            $this->renderWithHeyGen($video, (string) $written['video_script']);

            return;
        }

        $this->submitToHiggsfield($video, $written);
    }

    /**
     * @return array<string, mixed>
     */
    protected function writeContent(Generation $video, string $style): array
    {
        $offer = $video->offer;
        $duration = (int) ($video->input['duration'] ?? VideoStyles::get($style)['duration']);
        $userScript = $video->input['user_script'] ?? null;
        $words = VideoStyles::get($style)['provider'] === VideoStyles::HEYGEN ? '110-150 words (about 45-55 seconds spoken)' : max(15, (int) round($duration * 2.3)).' words at most (it must fit '.$duration.' seconds)';

        $styleBrief = match ($style) {
            'talking_review' => 'A creator-style talking review: an honest first-person recommendation spoken to camera — a scroll-stopping hook, the problem it solved, two concrete benefits, a natural mention that the link is a partnership/affiliate link, and a call to action pointing to the link in the bio or description.',
            'product_showcase' => 'A short product showcase ad: the product is the hero on screen, shown in attractive, realistic everyday use with dynamic phone-camera moves. A punchy voiceover names the main benefit and ends with "link in bio".',
            default => 'A problem → solution ad: open on a relatable frustration the ideal customer has, then reveal the product solving it, ending on the happy result. The voiceover follows the same arc and ends with "link in bio".',
        };

        $system = <<<PROMPT
            You write short-form vertical video ads for an affiliate marketer. Style: {$styleBrief}

            Return a JSON object with exactly these keys:
            - "video_script": the exact words spoken in the video, plain conversational sentences only, no labels, no stage directions, no emojis, {$words}. Never say a URL out loud.
            - "visual_prompt": for the video model, 50-90 words describing what is seen, shot by shot, in a vertical 9:16 phone-shot style. Keep the product exactly as in its reference photo. No on-screen text or captions.
            - "script": the same video as a beat-by-beat Markdown outline (Hook, Body, Call to action) for the marketer's records.
            - "on_screen_text_ideas": array of 3-5 short caption overlay ideas.
            - "platforms": array of 3-4 objects, best-fit first, each with "platform" ("TikTok", "Instagram Reels", "YouTube Shorts" or "Facebook Reels"), "title" (short hook title), "caption" (the post caption in that platform's voice, with "{{AFFILIATE_LINK}}" placed naturally), "tags" (8-15 hashtags) and "posting_tip" (one platform-native tip).
            PROMPT;

        $userPrompt = "Product: {$offer->product_name}\nOfficial URL: {$offer->product_url}\nIdeal customer: {$offer->ideal_customer_summary}\nBest angle: {$offer->recommended_angle}\n";

        if ($userScript) {
            $userPrompt .= "\nThe marketer wrote the spoken words themselves. Use them as \"video_script\" exactly as written, and write everything else to match:\n{$userScript}\n";
        }

        $result = $this->ai->generateJson($system, $userPrompt);

        if ($userScript) {
            $result['video_script'] = $userScript;
        }

        if (trim((string) ($result['video_script'] ?? '')) === '') {
            throw new RuntimeException('The AI returned no script.');
        }

        $result['quick_style'] = $style;

        return $result;
    }

    protected function renderWithHeyGen(Generation $video, string $script): void
    {
        [$avatarId, $voiceId] = $this->presenter($video);

        if (! $avatarId || ! $voiceId) {
            $this->fail($video, 'No AI presenter is available right now — please try again in a few minutes.');

            return;
        }

        $video->update(['input' => array_merge($video->input, [
            'avatar_id' => $avatarId,
            'voice_id' => $voiceId,
            'video_script' => $script,
            'script_edited' => isset($video->input['user_script']),
        ])]);

        $this->heygen->generate($video->fresh());
    }

    /**
     * The customer's choice, else the admin's default presenter and voice,
     * else the first presenter with a voice of the same gender.
     *
     * @return array{0: ?string, 1: ?string}
     */
    public function presenter(Generation $video): array
    {
        $settings = VideoStudioSetting::current();
        $avatars = $this->catalog->avatars();
        $voices = $this->catalog->voices();

        $avatarId = $video->input['avatar_id'] ?? $settings->heygen_default_avatar_id ?? ($avatars[0]['id'] ?? null);
        $voiceId = $video->input['voice_id'] ?? $settings->heygen_default_voice_id ?? null;

        if ($voiceId === null && $voices !== []) {
            $gender = strtolower((string) (collect($avatars)->firstWhere('id', $avatarId)['gender'] ?? ''));
            $english = collect($voices)->filter(fn (array $voice): bool => str_starts_with(strtolower((string) $voice['language']), 'english'));
            $pool = $english->isNotEmpty() ? $english : collect($voices);

            $voiceId = ($pool->first(fn (array $voice): bool => $gender !== '' && strtolower((string) $voice['gender']) === $gender) ?? $pool->first())['id'] ?? null;
        }

        return [$avatarId, $voiceId];
    }

    /**
     * The request body sent to Higgsfield for this video.
     *
     * @param  array<string, mixed>  $written
     * @return array<string, mixed>
     */
    public function higgsfieldParams(Generation $video, array $written): array
    {
        $settings = VideoStudioSetting::current();
        $offer = $video->offer;
        $voiceover = trim((string) $written['video_script']);

        $prompt = "Vertical 9:16 UGC-style smartphone video ad for {$offer->product_name}. "
            .trim((string) ($written['visual_prompt'] ?? ''))
            ." The product looks exactly like the reference image. A warm, natural voiceover says: \"{$voiceover}\""
            .' Realistic lighting, authentic social-media feel, no on-screen text, no subtitles, no watermarks.';

        return [
            'prompt' => Str::limit($prompt, 2500, ''),
            'image_urls' => [$this->images->publicUrl((string) $video->input['product_image_path'])],
            'duration' => (int) $video->input['duration'],
            'resolution' => $settings->higgsfield_resolution ?: '720p',
            'aspect_ratio' => '9:16',
            'generate_audio' => true,
        ];
    }

    /**
     * @param  array<string, mixed>  $written
     */
    protected function submitToHiggsfield(Generation $video, array $written): void
    {
        $settings = VideoStudioSetting::current();

        if (! $settings->higgsfieldReady()) {
            $this->fail($video, 'Product videos are paused right now — please try another style.');

            return;
        }

        $client = $this->higgsfield($settings);
        $result = $client->submit(
            $settings->higgsfield_model,
            $this->higgsfieldParams($video, $written),
            $settings->higgsfieldWebhookSecret() ? route('webhooks.higgsfield') : null,
        );

        if (! $result['success']) {
            Log::warning('Higgsfield refused a video request.', ['generation_id' => $video->id, 'message' => $result['message']]);
            $this->fail($video, 'The video engine couldn\'t start this video. Please try again in a few minutes.');

            return;
        }

        $video->update([
            'input' => array_merge($video->input, ['video_script' => $written['video_script']]),
            'output_meta' => ['higgsfield_request_id' => $result['request_id'], 'submitted_at' => now()->toIso8601String()],
        ]);

        CheckHiggsfieldVideo::dispatch($video)->delay(now()->addSeconds(45));
    }

    /**
     * Step 2 for Higgsfield (polled by CheckHiggsfieldVideo, or pushed by
     * the webhook). Returns true once the video reached a final state.
     *
     * @param  array{status: string, video_url: ?string, message: ?string}|null  $result  a webhook's result, or null to ask Higgsfield
     */
    public function checkHiggsfield(Generation $video, ?array $result = null): bool
    {
        $video->refresh();

        if ($video->status !== 'queued') {
            return true;
        }

        $requestId = $video->output_meta['higgsfield_request_id'] ?? null;

        if (! $requestId) {
            $this->fail($video, 'This video request was lost — please create it again.');

            return true;
        }

        $result ??= $this->higgsfield(VideoStudioSetting::current())->status($requestId);

        if ($result['status'] === 'completed' && $result['video_url']) {
            $this->complete($video, $result['video_url']);

            return true;
        }

        if (in_array($result['status'], ['failed', 'nsfw', 'canceled'], true)) {
            $this->fail($video, $result['status'] === 'nsfw'
                ? 'The video engine flagged this video as unsafe and stopped it. Try another style or product photo — no credits were taken.'
                : 'The video engine couldn\'t finish this video. Please try again — no credits were taken.');

            return true;
        }

        $submitted = isset($video->output_meta['submitted_at']) ? now()->parse($video->output_meta['submitted_at']) : $video->created_at;

        if ($submitted->lt(now()->subMinutes(self::HIGGSFIELD_TIMEOUT_MINUTES))) {
            $this->fail($video, 'This video took too long to render and was stopped. Please try again — no credits were taken.');

            return true;
        }

        return false;
    }

    protected function complete(Generation $video, string $remoteUrl): void
    {
        $user = $video->user;
        $cost = (int) $video->input['credit_cost'];
        $localPath = $this->download($remoteUrl, $video->id);

        if ($localPath === null) {
            // Higgsfield deletes outputs after seven days, so a remote link isn't good enough.
            $this->fail($video, 'The finished video couldn\'t be saved. Please try again — no credits were taken.');

            return;
        }

        $url = Storage::disk('public')->url($localPath);

        try {
            DB::transaction(function () use ($video, $user, $cost, $url) {
                $this->credits->spend($user, $cost, 'ugc_video', $video);

                $video->update([
                    'status' => 'completed',
                    'output' => $url,
                    'output_meta' => array_merge((array) $video->output_meta, ['video_url' => $url]),
                    'credits_spent' => $cost,
                ]);
            });
        } catch (Throwable $e) {
            Log::error('Credit spend failed after a finished Higgsfield video — not charged, file removed.', ['generation_id' => $video->id, 'error' => $e->getMessage()]);
            Storage::disk('public')->delete($localPath);
            $this->fail($video, 'Insufficient credits at processing time.');
        }
    }

    protected function download(string $url, int $generationId): ?string
    {
        try {
            $response = Http::timeout(180)->get($url);

            if ($response->failed() || strlen($response->body()) < 1024) {
                return null;
            }

            $path = "ugc-videos/{$generationId}-".Str::random(32).'.mp4';
            Storage::disk('public')->put($path, $response->body());

            return $path;
        } catch (Throwable $e) {
            Log::warning('Downloading a finished Higgsfield video failed.', ['generation_id' => $generationId, 'error' => $e->getMessage()]);

            return null;
        }
    }

    protected function fail(Generation $video, string $message, ?Generation $content = null): void
    {
        $video->update(['status' => 'failed', 'error_message' => $message]);

        $content ??= Generation::find($video->input['content_generation_id'] ?? null);

        if ($content && $content->status === 'queued') {
            $content->update(['status' => 'failed', 'error_message' => $message]);
        }
    }

    protected function higgsfield(VideoStudioSetting $settings): HiggsfieldClient
    {
        return new HiggsfieldClient((string) $settings->higgsfieldKeyId(), (string) $settings->higgsfieldKeySecret());
    }
}
