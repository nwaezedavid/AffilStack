<?php

namespace Tests\Feature;

use App\Services\Video\HeyGenClient;
use App\Services\Video\VideoGenerationException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The UGC video overhaul's HeyGen client — covers auth verification,
 * listing avatars/voices, submitting a video, and polling its status,
 * including the cost-control detail that generateVideo() always pins the
 * cheaper avatar_iii engine rather than trusting HeyGen's pricier default.
 */
class HeyGenClientTest extends TestCase
{
    public function test_verify_api_key_succeeds_when_heygen_accepts_the_key(): void
    {
        Http::fake(['api.heygen.com/v3/users/me' => Http::response(['data' => ['email' => 'a@b.com']], 200)]);

        $result = (new HeyGenClient('key-good'))->verifyApiKey();

        $this->assertTrue($result['success']);
    }

    public function test_verify_api_key_fails_and_surfaces_heygens_error_message(): void
    {
        Http::fake(['api.heygen.com/v3/users/me' => Http::response(['error' => ['message' => 'Invalid API key']], 401)]);

        $result = (new HeyGenClient('key-bad'))->verifyApiKey();

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Invalid API key', $result['message']);
    }

    public function test_list_avatars_returns_a_simplified_list(): void
    {
        Http::fake([
            'api.heygen.com/v3/avatars/looks*' => Http::response([
                'data' => [
                    ['id' => 'avatar_1', 'name' => 'Alex', 'gender' => 'male', 'preview_image_url' => 'https://cdn/1.png'],
                ],
            ]),
        ]);

        $avatars = (new HeyGenClient('key'))->listAvatars();

        $this->assertSame([['id' => 'avatar_1', 'name' => 'Alex', 'gender' => 'male', 'preview_image_url' => 'https://cdn/1.png']], $avatars);
    }

    public function test_list_avatars_throws_a_video_generation_exception_on_failure(): void
    {
        Http::fake(['api.heygen.com/v3/avatars/looks*' => Http::response(['error' => ['message' => 'down']], 500)]);

        $this->expectException(VideoGenerationException::class);

        (new HeyGenClient('key'))->listAvatars();
    }

    public function test_list_voices_returns_a_simplified_list(): void
    {
        Http::fake([
            'api.heygen.com/v3/voices*' => Http::response([
                'data' => [
                    ['voice_id' => 'voice_1', 'name' => 'Jamie', 'language' => 'English', 'gender' => 'female'],
                ],
            ]),
        ]);

        $voices = (new HeyGenClient('key'))->listVoices();

        $this->assertSame([['id' => 'voice_1', 'name' => 'Jamie', 'language' => 'English', 'gender' => 'female']], $voices);
    }

    public function test_generate_video_pins_the_cheaper_avatar_iii_engine_and_vertical_720p(): void
    {
        Http::fake(['api.heygen.com/v3/videos' => Http::response(['data' => ['video_id' => 'v_123']], 200)]);

        $result = (new HeyGenClient('key'))->generateVideo('avatar_1', 'voice_1', 'Hello world.');

        $this->assertTrue($result['success']);
        $this->assertSame('v_123', $result['video_id']);

        Http::assertSent(fn ($request) => $request['engine']['type'] === 'avatar_iii'
            && $request['resolution'] === '720p'
            && $request['aspect_ratio'] === '9:16'
            && $request['avatar_id'] === 'avatar_1'
            && $request['voice_id'] === 'voice_1'
            && $request['script'] === 'Hello world.');
    }

    public function test_generate_video_reports_failure_when_heygen_rejects_the_request(): void
    {
        Http::fake(['api.heygen.com/v3/videos' => Http::response(['error' => ['message' => 'insufficient_credit']], 402)]);

        $result = (new HeyGenClient('key'))->generateVideo('avatar_1', 'voice_1', 'Hello world.');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('insufficient_credit', $result['message']);
    }

    public function test_check_video_status_reports_completed_with_the_video_url(): void
    {
        Http::fake(['api.heygen.com/v3/videos/v_123' => Http::response(['data' => ['status' => 'completed', 'video_url' => 'https://cdn/v.mp4']])]);

        $status = (new HeyGenClient('key'))->checkVideoStatus('v_123');

        $this->assertSame('completed', $status['status']);
        $this->assertSame('https://cdn/v.mp4', $status['video_url']);
    }

    public function test_check_video_status_reports_failure_reason(): void
    {
        Http::fake(['api.heygen.com/v3/videos/v_123' => Http::response(['data' => ['status' => 'failed', 'failure_message' => 'render error']])]);

        $status = (new HeyGenClient('key'))->checkVideoStatus('v_123');

        $this->assertSame('failed', $status['status']);
        $this->assertSame('render error', $status['message']);
    }
}
