<?php

namespace Tests\Feature;

use App\Jobs\RunUgcGeneration;
use App\Jobs\RunUgcVideoGeneration;
use App\Models\Generation;
use App\Models\HeyGenSetting;
use App\Models\Offer;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Modules\UgcVideoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The UGC page's step-by-step flow: pick an angle, choose a character
 * (HeyGen stock avatar + voice), generate the script, edit it, and render
 * the video from the EDITED script.
 */
class UgcEditedScriptVideoTest extends TestCase
{
    use RefreshDatabase;

    protected function customer(int $credits = 500): User
    {
        $plan = Plan::factory()->create(['channels' => ['research', 'ugc']]);
        $user = User::factory()->create(['credits_balance' => $credits]);
        Subscription::factory()->create(['user_id' => $user->id, 'plan_id' => $plan->id]);

        return $user->fresh();
    }

    protected function offer(User $user): Offer
    {
        return Offer::create([
            'user_id' => $user->id, 'product_name' => 'Acme Widget', 'product_url' => 'https://example.com',
            'affiliate_network' => 'Impact.com', 'status' => 'ready', 'recommended_angle' => 'Save time.',
        ]);
    }

    protected function connectHeyGen(): void
    {
        HeyGenSetting::current()->update(['is_enabled' => true, 'credentials' => ['api_key' => 'k'], 'verification_status' => 'success']);
    }

    protected function fakeHeyGen(): void
    {
        Http::fake(function (Request $request) {
            $url = $request->url();

            return match (true) {
                str_contains($url, '/v3/avatars/looks') => Http::response(['data' => [
                    ['id' => 'av_amara', 'name' => 'Amara', 'gender' => 'female', 'preview_image_url' => 'https://cdn.example/amara.jpg'],
                    ['id' => 'av_tunde', 'name' => 'Tunde', 'gender' => 'male', 'preview_image_url' => null],
                ]]),
                str_contains($url, '/v3/voices') => Http::response(['data' => [
                    ['voice_id' => 'vo_warm', 'name' => 'Warm Lagos', 'language' => 'English', 'gender' => 'female'],
                ]]),
                $request->method() === 'POST' && str_ends_with($url, '/v3/videos') => Http::response(['data' => ['video_id' => 'vid_1']]),
                str_contains($url, '/v3/videos/vid_1') => Http::response(['data' => ['status' => 'completed', 'video_url' => 'https://files.heygen.example/vid_1.mp4']]),
                str_contains($url, 'files.heygen.example') => Http::response('MP4BYTES'),
                default => Http::response([], 404),
            };
        });
    }

    protected function completedScript(User $user, Offer $offer, array $input = []): Generation
    {
        $angles = $offer->generations()->create([
            'user_id' => $user->id, 'module' => 'ugc_angles', 'status' => 'completed',
            'output_meta' => ['angles' => [['name' => 'Before/After', 'format' => 'testimonial', 'hook_idea' => 'I wasted hours…', 'why_it_works' => 'Relatable.']]],
        ]);

        return $offer->generations()->create([
            'user_id' => $user->id, 'module' => 'ugc_content', 'status' => 'completed',
            'input' => array_merge(['angles_generation_id' => $angles->id, 'angle_index' => 0], $input),
            'output_meta' => [
                'script' => '**Hook:** I wasted hours…',
                'video_script' => 'Original AI script that the presenter would read out loud.',
                'platforms' => [['platform' => 'TikTok', 'title' => 'Stop wasting time', 'caption' => 'Link in bio', 'tags' => ['ugc'], 'posting_tip' => 'Post at 7pm']],
            ],
        ]);
    }

    public function test_the_chosen_character_is_saved_with_the_script_request(): void
    {
        Queue::fake();
        $user = $this->customer();
        $offer = $this->offer($user);
        $angles = $offer->generations()->create([
            'user_id' => $user->id, 'module' => 'ugc_angles', 'status' => 'completed',
            'output_meta' => ['angles' => [['name' => 'A', 'format' => 'f', 'hook_idea' => 'h', 'why_it_works' => 'w']]],
        ]);

        $this->actingAs($user)->post(route('offers.ugc.content', $offer), [
            'angles_generation_id' => $angles->id, 'angle_index' => 0,
            'avatar_id' => 'av_amara', 'voice_id' => 'vo_warm', 'avatar_name' => 'Amara', 'voice_name' => 'Warm Lagos',
        ])->assertRedirect(route('offers.feature', [$offer, 'ugc']));

        $content = $offer->generations()->where('module', 'ugc_content')->firstOrFail();
        $this->assertSame('av_amara', $content->input['avatar_id']);
        $this->assertSame('vo_warm', $content->input['voice_id']);
        $this->assertSame('Amara', $content->input['avatar_name']);
        Queue::assertPushed(RunUgcGeneration::class);
    }

    public function test_the_video_is_queued_with_the_users_edited_script(): void
    {
        Queue::fake();
        $this->connectHeyGen();
        $user = $this->customer();
        $offer = $this->offer($user);
        $script = $this->completedScript($user, $offer);
        $edited = 'My own words: this widget gave me back an hour every single day. Link in my bio.';

        $this->actingAs($user)->post(route('offers.ugc.video', $offer), [
            'content_generation_id' => $script->id, 'avatar_id' => 'av_amara', 'voice_id' => 'vo_warm', 'video_script' => $edited,
        ])->assertRedirect(route('offers.feature', [$offer, 'ugc']))->assertSessionHas('success');

        $video = $offer->generations()->where('module', 'ugc_video')->firstOrFail();
        $this->assertSame($edited, $video->input['video_script']);
        $this->assertTrue($video->input['script_edited']);
        $this->assertSame($edited, $script->fresh()->output_meta['edited_video_script']);
        Queue::assertPushed(RunUgcVideoGeneration::class);
    }

    public function test_without_an_edit_the_ai_script_is_used(): void
    {
        Queue::fake();
        $this->connectHeyGen();
        $user = $this->customer();
        $offer = $this->offer($user);
        $script = $this->completedScript($user, $offer);

        $this->actingAs($user)->post(route('offers.ugc.video', $offer), [
            'content_generation_id' => $script->id, 'avatar_id' => 'av_amara', 'voice_id' => 'vo_warm',
        ])->assertSessionHas('success');

        $video = $offer->generations()->where('module', 'ugc_video')->firstOrFail();
        $this->assertSame('Original AI script that the presenter would read out loud.', $video->input['video_script']);
        $this->assertFalse($video->input['script_edited']);
    }

    public function test_an_overlong_edited_script_is_rejected(): void
    {
        Queue::fake();
        $this->connectHeyGen();
        $user = $this->customer();
        $offer = $this->offer($user);
        $script = $this->completedScript($user, $offer);

        $this->actingAs($user)->post(route('offers.ugc.video', $offer), [
            'content_generation_id' => $script->id, 'avatar_id' => 'av_amara', 'voice_id' => 'vo_warm',
            'video_script' => str_repeat('word ', 400),
        ])->assertSessionHasErrors('video_script');

        Queue::assertNothingPushed();
    }

    public function test_heygen_renders_exactly_the_edited_script(): void
    {
        Storage::fake('public');
        $this->connectHeyGen();
        $this->fakeHeyGen();
        $user = $this->customer();
        $offer = $this->offer($user);
        $script = $this->completedScript($user, $offer);
        $edited = 'Edited line one. Edited line two, with the link in my bio.';

        $video = app(UgcVideoService::class)->queue($user, $offer, $script, 'av_amara', 'vo_warm', $edited);
        app(UgcVideoService::class)->generate($video);

        $this->assertSame('completed', $video->fresh()->status);
        Http::assertSent(fn (Request $request) => $request->method() === 'POST'
            && str_ends_with($request->url(), '/v3/videos')
            && $request['script'] === $edited
            && $request['avatar_id'] === 'av_amara'
            && $request['voice_id'] === 'vo_warm');
    }

    public function test_the_ugc_page_offers_heygen_presenters_and_the_editable_script(): void
    {
        Cache::flush();
        $this->connectHeyGen();
        $this->fakeHeyGen();
        $user = $this->customer();
        $offer = $this->offer($user);
        $this->completedScript($user, $offer, ['avatar_id' => 'av_tunde', 'voice_id' => 'vo_warm']);

        $this->actingAs($user)->get(route('offers.feature', [$offer, 'ugc']))
            ->assertOk()
            ->assertSee('Choose your angle')
            ->assertSee('Choose your character')
            ->assertSee('Amara')
            ->assertSee('https://cdn.example/amara.jpg', false)
            ->assertSee('Warm Lagos')
            ->assertSee('Original AI script that the presenter would read out loud.')
            ->assertSee('name="video_script"', false)
            ->assertSee('<option value="av_tunde" selected>', false)
            ->assertSee('Create video');
    }

    public function test_the_ugc_page_still_works_for_scripts_when_heygen_is_not_configured(): void
    {
        $user = $this->customer();
        $offer = $this->offer($user);
        $this->completedScript($user, $offer);

        $this->actingAs($user)->get(route('offers.feature', [$offer, 'ugc']))
            ->assertOk()
            ->assertSee('AI presenters are being set up.')
            ->assertSee('Generate another script')
            ->assertSee('Create video (coming soon)');
    }

    public function test_a_seat_can_edit_scripts_but_is_told_the_owner_renders_videos(): void
    {
        $this->connectHeyGen();
        $this->fakeHeyGen();
        $owner = $this->customer();
        $offer = $this->offer($owner);
        $this->completedScript($owner, $offer);
        $seat = User::factory()->create(['agency_owner_id' => $owner->id, 'seat_offer_id' => $offer->id]);

        $this->actingAs($seat)->get(route('offers.feature', [$offer, 'ugc']))
            ->assertOk()
            ->assertSee('Ask your account owner to render the video');
    }
}
