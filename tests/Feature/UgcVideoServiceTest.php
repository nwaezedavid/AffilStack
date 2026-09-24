<?php

namespace Tests\Feature;

use App\Models\Generation;
use App\Models\HeyGenSetting;
use App\Models\Offer;
use App\Models\User;
use App\Services\Modules\UgcVideoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;

/**
 * The UGC video overhaul's core flow: queue() only ever accepts a
 * completed ugc_content script with a spoken video_script, generate()
 * submits it to HeyGen and polls to a terminal state, and the finished
 * video is downloaded to permanent storage rather than trusting HeyGen's
 * own (presumably temporary) URL to stay valid forever.
 */
class UgcVideoServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function connectedHeyGen(): void
    {
        HeyGenSetting::current()->update([
            'is_enabled' => true,
            'credentials' => ['api_key' => 'key-test'],
            'verification_status' => 'success',
        ]);
    }

    protected function offer(User $user): Offer
    {
        return Offer::create([
            'user_id' => $user->id,
            'product_name' => 'Acme Widget',
            'product_url' => 'https://example.com',
            'affiliate_network' => 'ShareASale',
            'status' => 'ready',
        ]);
    }

    protected function completedContentGeneration(Offer $offer, User $user, string $videoScript = 'Hey, this widget changed my routine.'): Generation
    {
        return $offer->generations()->create([
            'user_id' => $user->id,
            'module' => 'ugc_content',
            'input' => [],
            'output' => 'script text',
            'output_meta' => ['script' => 'script text', 'video_script' => $videoScript],
            'credits_spent' => 12,
            'status' => 'completed',
        ]);
    }

    public function test_queue_rejects_a_content_generation_that_is_not_completed_ugc_content(): void
    {
        $this->connectedHeyGen();
        $user = User::factory()->create(['credits_balance' => 100]);
        $offer = $this->offer($user);
        $notUgc = $offer->generations()->create(['user_id' => $user->id, 'module' => 'blog_article', 'status' => 'completed', 'output_meta' => []]);

        $this->expectException(InvalidArgumentException::class);

        app(UgcVideoService::class)->queue($user, $offer, $notUgc, 'avatar_1', 'voice_1');
    }

    public function test_queue_rejects_a_script_with_no_video_script(): void
    {
        $this->connectedHeyGen();
        $user = User::factory()->create(['credits_balance' => 100]);
        $offer = $this->offer($user);
        $content = $this->completedContentGeneration($offer, $user, videoScript: '');

        $this->expectException(RuntimeException::class);

        app(UgcVideoService::class)->queue($user, $offer, $content, 'avatar_1', 'voice_1');
    }

    public function test_queue_refuses_when_heygen_is_not_connected(): void
    {
        $user = User::factory()->create(['credits_balance' => 100]);
        $offer = $this->offer($user);
        $content = $this->completedContentGeneration($offer, $user);

        $this->expectException(RuntimeException::class);

        app(UgcVideoService::class)->queue($user, $offer, $content, 'avatar_1', 'voice_1');
    }

    public function test_queue_creates_a_pending_generation_snapshotting_the_script(): void
    {
        Http::fake(); // prevent the queued job from actually firing HTTP in this test
        $this->connectedHeyGen();
        $user = User::factory()->create(['credits_balance' => 100]);
        $offer = $this->offer($user);
        $content = $this->completedContentGeneration($offer, $user);

        Queue::fake();

        $video = app(UgcVideoService::class)->queue($user, $offer, $content, 'avatar_1', 'voice_1');

        $this->assertSame('ugc_video', $video->module);
        $this->assertSame('queued', $video->status);
        $this->assertSame('Hey, this widget changed my routine.', $video->input['video_script']);
        $this->assertSame($content->id, $video->input['content_generation_id']);
    }

    public function test_generate_marks_the_generation_completed_and_downloads_the_video_permanently(): void
    {
        Storage::fake('public');
        $this->connectedHeyGen();
        $user = User::factory()->create(['credits_balance' => 100]);
        $offer = $this->offer($user);
        $content = $this->completedContentGeneration($offer, $user);

        Http::fake([
            'api.heygen.com/v3/videos' => Http::response(['data' => ['video_id' => 'v_1']]),
            'api.heygen.com/v3/videos/v_1' => Http::response(['data' => ['status' => 'completed', 'video_url' => 'https://cdn.heygen.com/v_1.mp4']]),
            'cdn.heygen.com/*' => Http::response('fake-mp4-bytes', 200),
        ]);

        $video = $offer->generations()->create([
            'user_id' => $user->id,
            'module' => 'ugc_video',
            'input' => ['content_generation_id' => $content->id, 'avatar_id' => 'avatar_1', 'voice_id' => 'voice_1', 'video_script' => 'Hey there.'],
            'status' => 'queued',
        ]);

        app(UgcVideoService::class)->generate($video);

        $video->refresh();
        $this->assertSame('completed', $video->status);
        $this->assertSame(40, $video->credits_spent);
        Storage::disk('public')->assertExists('ugc-videos/'.$video->id.'.mp4');
        $this->assertSame(100 - 40, $user->fresh()->credits_balance);
    }

    public function test_generate_marks_the_generation_failed_when_heygen_rejects_the_submission(): void
    {
        $this->connectedHeyGen();
        $user = User::factory()->create(['credits_balance' => 100]);
        $offer = $this->offer($user);
        $content = $this->completedContentGeneration($offer, $user);

        Http::fake(['api.heygen.com/v3/videos' => Http::response(['error' => ['message' => 'insufficient_credit']], 402)]);

        $video = $offer->generations()->create([
            'user_id' => $user->id,
            'module' => 'ugc_video',
            'input' => ['content_generation_id' => $content->id, 'avatar_id' => 'avatar_1', 'voice_id' => 'voice_1', 'video_script' => 'Hey there.'],
            'status' => 'queued',
        ]);

        app(UgcVideoService::class)->generate($video);

        $video->refresh();
        $this->assertSame('failed', $video->status);
        $this->assertStringContainsString('insufficient_credit', $video->error_message);
        $this->assertSame(100, $user->fresh()->credits_balance); // never charged
    }

    public function test_generate_marks_the_generation_failed_when_heygen_reports_a_render_failure(): void
    {
        $this->connectedHeyGen();
        $user = User::factory()->create(['credits_balance' => 100]);
        $offer = $this->offer($user);
        $content = $this->completedContentGeneration($offer, $user);

        Http::fake([
            'api.heygen.com/v3/videos' => Http::response(['data' => ['video_id' => 'v_1']]),
            'api.heygen.com/v3/videos/v_1' => Http::response(['data' => ['status' => 'failed', 'failure_message' => 'render error']]),
        ]);

        $video = $offer->generations()->create([
            'user_id' => $user->id,
            'module' => 'ugc_video',
            'input' => ['content_generation_id' => $content->id, 'avatar_id' => 'avatar_1', 'voice_id' => 'voice_1', 'video_script' => 'Hey there.'],
            'status' => 'queued',
        ]);

        app(UgcVideoService::class)->generate($video);

        $this->assertSame('failed', $video->fresh()->status);
        $this->assertStringContainsString('render error', $video->fresh()->error_message);
    }

    public function test_a_credit_spend_failure_leaves_the_generation_failed_not_completed(): void
    {
        Storage::fake('public');
        $this->connectedHeyGen();
        $user = User::factory()->create(['credits_balance' => 40]);
        $offer = $this->offer($user);
        $content = $this->completedContentGeneration($offer, $user);

        Http::fake([
            'api.heygen.com/v3/videos' => Http::response(['data' => ['video_id' => 'v_1']]),
            'api.heygen.com/v3/videos/v_1' => function () use ($user) {
                // Simulates a second, concurrent generation spending this
                // user's last 40 credits while this one is still rendering:
                // hasEnough() passed when this generation started (balance
                // was 40), but by the time credits->spend() runs after the
                // render finishes, the balance has already been taken.
                $user->update(['credits_balance' => 0]);

                return Http::response(['data' => ['status' => 'completed', 'video_url' => 'https://cdn.heygen.com/v_1.mp4']]);
            },
            'cdn.heygen.com/*' => Http::response('fake-mp4-bytes', 200),
        ]);

        $video = $offer->generations()->create([
            'user_id' => $user->id,
            'module' => 'ugc_video',
            'input' => ['content_generation_id' => $content->id, 'avatar_id' => 'avatar_1', 'voice_id' => 'voice_1', 'video_script' => 'Hey there.'],
            'status' => 'queued',
        ]);

        app(UgcVideoService::class)->generate($video);

        $video->refresh();
        // Before the fix: status ended up "completed" with credits_spent
        // recorded as 40 even though spend() threw InsufficientCreditsException
        // and the ledger/balance were never actually touched by this
        // generation — the rendered video was handed out for free.
        $this->assertSame('failed', $video->status);
        $this->assertSame(0, $video->credits_spent);
        $this->assertNull($video->output);
        $this->assertSame(0, $user->fresh()->credits_balance);
    }

    public function test_generate_falls_back_to_heygens_own_url_when_the_download_fails(): void
    {
        Storage::fake('public');
        $this->connectedHeyGen();
        $user = User::factory()->create(['credits_balance' => 100]);
        $offer = $this->offer($user);
        $content = $this->completedContentGeneration($offer, $user);

        Http::fake([
            'api.heygen.com/v3/videos' => Http::response(['data' => ['video_id' => 'v_1']]),
            'api.heygen.com/v3/videos/v_1' => Http::response(['data' => ['status' => 'completed', 'video_url' => 'https://cdn.heygen.com/v_1.mp4']]),
            'cdn.heygen.com/*' => Http::response('', 500),
        ]);

        $video = $offer->generations()->create([
            'user_id' => $user->id,
            'module' => 'ugc_video',
            'input' => ['content_generation_id' => $content->id, 'avatar_id' => 'avatar_1', 'voice_id' => 'voice_1', 'video_script' => 'Hey there.'],
            'status' => 'queued',
        ]);

        app(UgcVideoService::class)->generate($video);

        $video->refresh();
        $this->assertSame('completed', $video->status);
        $this->assertSame('https://cdn.heygen.com/v_1.mp4', $video->output);
    }
}
