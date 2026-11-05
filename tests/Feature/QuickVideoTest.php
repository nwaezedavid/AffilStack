<?php

namespace Tests\Feature;

use App\Filament\Pages\ApiKeySettings;
use App\Filament\Pages\UgcVideoSettings;
use App\Jobs\CheckHiggsfieldVideo;
use App\Jobs\RunQuickVideo;
use App\Models\ApiKeySetting;
use App\Models\Generation;
use App\Models\HeyGenSetting;
use App\Models\Offer;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Models\VideoStudioSetting;
use App\Services\AI\AIProvider;
use App\Services\Modules\QuickVideoService;
use App\Support\OutboundUrlGuard;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * One-click videos: pick a style, press Create. HeyGen renders the
 * "Talking review"; Higgsfield renders the product styles from the
 * product's own picture. Charged once, only when the video is saved.
 */
class QuickVideoTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Offer $offer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
        Storage::fake('public');
        Cache::flush();
        OutboundUrlGuard::resolveUsing(fn (string $host): array => ['93.184.216.34']);

        $plan = Plan::factory()->create(['channels' => ['research', 'ugc'], 'contact_limit' => 0]);
        $this->user = User::factory()->create(['credits_balance' => 500]);
        $this->user->assignRole('user');
        Subscription::factory()->create(['user_id' => $this->user->id, 'plan_id' => $plan->id]);
        $this->user->refresh();

        $this->offer = Offer::create([
            'user_id' => $this->user->id,
            'product_name' => 'GlowBlend Juicer',
            'product_url' => 'https://glowblend.test/juicer',
            'affiliate_network' => 'Impact',
            'status' => 'ready',
            'ideal_customer_summary' => 'Busy professionals who want healthy breakfasts.',
            'recommended_angle' => 'Fresh juice in 60 seconds',
        ]);

        $this->app->instance(AIProvider::class, new class implements AIProvider
        {
            public function generateText(string $systemPrompt, string $userPrompt, array $options = []): string
            {
                return '';
            }

            public function generateJson(string $systemPrompt, string $userPrompt, array $options = []): array
            {
                return [
                    'video_script' => 'I make fresh juice in sixty seconds every morning now. Link in bio.',
                    'visual_prompt' => 'Close-up of the juicer on a sunny kitchen counter, oranges dropping in.',
                    'script' => "## Hook\nFresh juice in 60 seconds.",
                    'on_screen_text_ideas' => ['60-second juice'],
                    'platforms' => [['platform' => 'TikTok', 'title' => 'My morning hack', 'caption' => 'Get it here {{AFFILIATE_LINK}}', 'tags' => ['#juice'], 'posting_tip' => 'Post at 7am']],
                ];
            }

            public function generateImage(string $prompt, array $options = []): string
            {
                return '';
            }
        });
    }

    protected function tearDown(): void
    {
        OutboundUrlGuard::resolveUsing(null);

        parent::tearDown();
    }

    protected function connectHeyGen(): void
    {
        HeyGenSetting::current()->update(['is_enabled' => true, 'credentials' => ['api_key' => 'hg-key'], 'verification_status' => 'success']);
    }

    protected function connectHiggsfield(array $extra = []): void
    {
        VideoStudioSetting::current()->update($extra + [
            'higgsfield_enabled' => true,
            'credentials' => ['key_id' => 'kid', 'key_secret' => 'ksecret'],
            'higgsfield_verification_status' => 'success',
        ]);
    }

    protected function productPhoto(): string
    {
        $image = imagecreatetruecolor(640, 640);
        imagefilledrectangle($image, 0, 0, 639, 639, imagecolorallocate($image, 240, 120, 30));
        ob_start();
        imagepng($image);

        return (string) ob_get_clean();
    }

    public function test_only_styles_whose_engine_is_connected_are_offered(): void
    {
        $styles = app(QuickVideoService::class)->availableStyles();
        $this->assertFalse($styles['talking_review']['available']);
        $this->assertFalse($styles['product_showcase']['available']);

        $this->connectHeyGen();
        $styles = app(QuickVideoService::class)->availableStyles();
        $this->assertTrue($styles['talking_review']['available']);
        $this->assertFalse($styles['product_showcase']['available'], 'Higgsfield is not verified yet.');

        $this->connectHiggsfield();
        VideoStudioSetting::current()->update(['styles' => ['problem_solution' => ['enabled' => false, 'credits' => 150, 'duration' => 15]]]);
        $styles = app(QuickVideoService::class)->availableStyles();
        $this->assertTrue($styles['product_showcase']['available']);
        $this->assertFalse($styles['problem_solution']['available'], 'Switched off in the admin.');
    }

    public function test_the_ugc_page_offers_the_two_click_panel(): void
    {
        $this->connectHeyGen();
        Http::fake(['api.heygen.com/v3/avatars/looks*' => Http::response(['data' => []]), 'api.heygen.com/v3/voices*' => Http::response(['data' => []])]);

        $this->actingAs($this->user)->get(route('offers.feature', [$this->offer, 'ugc']))
            ->assertOk()
            ->assertSee('Pick a style, press Create')
            ->assertSee('Talking review')
            ->assertSee('Product showcase')
            ->assertSee('Step-by-step studio');
    }

    public function test_a_talking_review_is_written_rendered_by_heygen_and_charged_once(): void
    {
        $this->connectHeyGen();
        Http::fake([
            'api.heygen.com/v3/avatars/looks*' => Http::response(['data' => [['id' => 'av_f', 'name' => 'Ada', 'gender' => 'female']]]),
            'api.heygen.com/v3/voices*' => Http::response(['data' => [
                ['voice_id' => 'v_m', 'name' => 'Tom', 'language' => 'English', 'gender' => 'male'],
                ['voice_id' => 'v_f', 'name' => 'Grace', 'language' => 'English', 'gender' => 'female'],
            ]]),
            'api.heygen.com/v3/videos' => Http::response(['data' => ['video_id' => 'hg_1']]),
            'api.heygen.com/v3/videos/hg_1' => Http::response(['data' => ['status' => 'completed', 'video_url' => 'https://cdn.heygen.test/hg_1.mp4']]),
            'cdn.heygen.test/*' => Http::response(str_repeat('v', 4096)),
        ]);

        $this->actingAs($this->user)->post(route('offers.ugc.quick', $this->offer), ['style' => 'talking_review'])
            ->assertRedirect(route('offers.feature', [$this->offer, 'ugc']))
            ->assertSessionHas('success');

        $video = Generation::where('module', 'ugc_video')->firstOrFail();
        $this->assertSame('completed', $video->status);
        $this->assertSame(110, $video->credits_spent);
        $this->assertSame(500 - 110, $this->user->fresh()->credits_balance);
        $this->assertSame('av_f', $video->input['avatar_id']);
        $this->assertSame('v_f', $video->input['voice_id'], 'A voice matching the presenter is picked automatically.');
        Storage::disk('public')->assertExists(str_replace(Storage::disk('public')->url(''), '', $video->output));

        $content = Generation::findOrFail($video->input['content_generation_id']);
        $this->assertSame('completed', $content->status);
        $this->assertSame(0, (int) $content->credits_spent);
        $this->assertSame('TikTok', $content->output_meta['platforms'][0]['platform']);

        Http::assertSent(fn ($request) => $request->url() === 'https://api.heygen.com/v3/videos' && $request['script'] === 'I make fresh juice in sixty seconds every morning now. Link in bio.');
    }

    public function test_a_product_showcase_uses_the_product_page_picture_and_finishes_through_polling(): void
    {
        $this->connectHiggsfield();
        Bus::fake([CheckHiggsfieldVideo::class]);
        Http::fake([
            'glowblend.test/juicer' => Http::response('<html><head><meta property="og:image" content="/img/juicer.png"></head></html>'),
            'glowblend.test/img/juicer.png' => Http::response($this->productPhoto(), 200, ['Content-Type' => 'image/png']),
            'api.higgsfield.ai/bytedance/seedance-2.5/reference-to-video' => Http::response(['status' => 'queued', 'request_id' => 'req-9']),
            'api.higgsfield.ai/requests/req-9/status' => Http::sequence()
                ->push(['status' => 'in_progress', 'request_id' => 'req-9'])
                ->push(['status' => 'completed', 'request_id' => 'req-9', 'video' => ['url' => 'https://cdn.higgsfield.test/req-9.mp4']]),
            'cdn.higgsfield.test/*' => Http::response(str_repeat('m', 4096)),
        ]);

        $this->actingAs($this->user)->post(route('offers.ugc.quick', $this->offer), ['style' => 'product_showcase'])->assertSessionHas('success');

        $video = Generation::where('module', 'ugc_video')->firstOrFail();
        $this->assertSame('queued', $video->status);
        $this->assertSame('req-9', $video->output_meta['higgsfield_request_id']);
        Bus::assertDispatched(CheckHiggsfieldVideo::class);
        $this->assertNotNull($this->offer->fresh()->research_data['product_image_path'] ?? null, 'The found picture is kept for next time.');

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'seedance-2.5/reference-to-video')
                && $request->hasHeader('Authorization', 'Key kid:ksecret')
                && $request['aspect_ratio'] === '9:16'
                && $request['duration'] === 10
                && $request['generate_audio'] === true
                && str_contains($request['image_urls'][0], 'ugc-product-images/')
                && str_contains($request['prompt'], 'I make fresh juice in sixty seconds');
        });

        $service = app(QuickVideoService::class);
        $this->assertFalse($service->checkHiggsfield($video), 'Still rendering.');
        $this->assertSame(500, $this->user->fresh()->credits_balance);

        $this->assertTrue($service->checkHiggsfield($video));
        $video->refresh();
        $this->assertSame('completed', $video->status);
        $this->assertSame(120, $video->credits_spent);
        $this->assertSame(380, $this->user->fresh()->credits_balance);
        $this->assertStringContainsString('ugc-videos/', $video->output);
    }

    public function test_without_a_product_picture_the_customer_is_asked_to_upload_one(): void
    {
        $this->connectHiggsfield();
        Bus::fake([RunQuickVideo::class]);
        Http::fake(['glowblend.test/*' => Http::response('<html><head><title>No image</title></head></html>')]);

        $this->actingAs($this->user)->post(route('offers.ugc.quick', $this->offer), ['style' => 'product_showcase'])
            ->assertSessionHas('error', fn (string $message) => str_contains($message, 'Add a product photo'));
        $this->assertSame(0, Generation::count());

        $this->actingAs($this->user)->post(route('offers.ugc.quick', $this->offer), [
            'style' => 'product_showcase',
            'product_image' => UploadedFile::fake()->image('juicer.jpg', 800, 800),
        ])->assertSessionHas('success');

        $video = Generation::where('module', 'ugc_video')->firstOrFail();
        Storage::disk('public')->assertExists($video->input['product_image_path']);
    }

    public function test_a_moderated_video_fails_without_charging(): void
    {
        $this->connectHiggsfield();
        Bus::fake([CheckHiggsfieldVideo::class]);
        $this->offer->forceFill(['research_data' => ['product_image_path' => 'ugc-product-images/x.png']])->save();
        Storage::disk('public')->put('ugc-product-images/x.png', $this->productPhoto());
        Http::fake([
            'api.higgsfield.ai/bytedance/*' => Http::response(['request_id' => 'req-n']),
            'api.higgsfield.ai/requests/req-n/status' => Http::response(['status' => 'nsfw', 'request_id' => 'req-n']),
        ]);

        $this->actingAs($this->user)->post(route('offers.ugc.quick', $this->offer), ['style' => 'problem_solution']);
        $video = Generation::where('module', 'ugc_video')->firstOrFail();

        $this->assertTrue(app(QuickVideoService::class)->checkHiggsfield($video));
        $this->assertSame('failed', $video->fresh()->status);
        $this->assertStringContainsString('no credits were taken', $video->fresh()->error_message);
        $this->assertSame(500, $this->user->fresh()->credits_balance);
    }

    public function test_the_webhook_only_accepts_signed_deliveries(): void
    {
        $secret = 'whsec_'.base64_encode('a-very-secret-signing-key');
        $this->connectHiggsfield(['credentials' => ['key_id' => 'kid', 'key_secret' => 'ksecret', 'webhook_secret' => $secret]]);
        $this->offer->forceFill(['research_data' => ['product_image_path' => 'ugc-product-images/x.png']])->save();
        Storage::disk('public')->put('ugc-product-images/x.png', $this->productPhoto());
        Bus::fake([CheckHiggsfieldVideo::class]);
        Http::fake([
            'api.higgsfield.ai/bytedance/*' => Http::response(['request_id' => 'req-w']),
            'cdn.higgsfield.test/*' => Http::response(str_repeat('w', 4096)),
        ]);

        $this->actingAs($this->user)->post(route('offers.ugc.quick', $this->offer), ['style' => 'product_showcase']);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'hf_webhook='.rawurlencode(route('webhooks.higgsfield'))));

        $body = json_encode(['request_id' => 'req-w', 'status' => 'completed', 'payload' => ['video' => ['url' => 'https://cdn.higgsfield.test/req-w.mp4']]]);
        $headers = ['webhook-id' => 'msg_1', 'webhook-timestamp' => (string) time(), 'CONTENT_TYPE' => 'application/json'];

        $this->call('POST', route('webhooks.higgsfield'), [], [], [], $this->server($headers + ['webhook-signature' => 'v1,bad']), $body)->assertStatus(401);
        $this->assertSame('queued', Generation::where('module', 'ugc_video')->value('status'));

        $signature = base64_encode(hash_hmac('sha256', "msg_1.{$headers['webhook-timestamp']}.{$body}", 'a-very-secret-signing-key', true));
        $this->call('POST', route('webhooks.higgsfield'), [], [], [], $this->server($headers + ['webhook-signature' => "v1,{$signature}"]), $body)->assertOk();

        $this->assertSame('completed', Generation::where('module', 'ugc_video')->value('status'));
        $this->assertSame(380, $this->user->fresh()->credits_balance);
    }

    /**
     * @param  array<string, string>  $headers
     * @return array<string, string>
     */
    protected function server(array $headers): array
    {
        $server = [];

        foreach ($headers as $name => $value) {
            $server[$name === 'CONTENT_TYPE' ? $name : 'HTTP_'.strtoupper(str_replace('-', '_', $name))] = $value;
        }

        return $server;
    }

    public function test_seats_and_plans_without_videos_cannot_create_them(): void
    {
        $this->connectHeyGen();
        $this->user->activeSubscription->plan->update(['channels' => ['research']]);

        $this->actingAs($this->user->fresh())->post(route('offers.ugc.quick', $this->offer), ['style' => 'talking_review'])
            ->assertSessionHas('error', fn (string $message) => str_contains($message, 'plan'));
        $this->assertSame(0, Generation::count());
    }

    public function test_admins_connect_higgsfield_and_set_style_prices(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('super-admin');
        Http::fake(['api.higgsfield.ai/estimate/*' => Http::response(['usd' => 2.31])]);

        Livewire::actingAs($admin)->test(UgcVideoSettings::class)
            ->set('data.higgsfield_enabled', true)
            ->set('data.higgsfield_key_id', 'kid-123')
            ->set('data.higgsfield_key_secret', 'secret-456')
            ->set('data.styles.product_showcase.credits', 140)
            ->call('verifyHiggsfield')
            ->assertDontSee('secret-456');

        $studio = VideoStudioSetting::current();
        $this->assertTrue($studio->higgsfieldReady());
        $this->assertSame(140, $studio->style('product_showcase')['credits']);
        $this->assertStringContainsString('2.31', (string) $studio->higgsfield_verification_message);
        $this->assertStringNotContainsString('secret-456', (string) DB::table('video_studio_settings')->value('credentials'));
        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Key kid-123:secret-456'));
    }

    public function test_admins_enter_the_remaining_keys_in_the_admin_instead_of_the_env_file(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('super-admin');

        Livewire::actingAs($admin)->test(ApiKeySettings::class)
            ->set('data.google_places_api_key', 'AIza-places-key')
            ->set('data.chrome_extension_store_url', 'https://chromewebstore.google.com/detail/affilstack/abc')
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertDontSee('AIza-places-key');

        $this->assertSame('AIza-places-key', ApiKeySetting::current()->credential('google_places_api_key'));
        $this->assertStringNotContainsString('AIza-places-key', (string) DB::table('api_key_settings')->value('credentials'));

        config(['services.google_places.api_key' => null, 'extension.store_url' => null]);
        ApiKeySetting::applyToConfig();
        $this->assertSame('AIza-places-key', config('services.google_places.api_key'));
        $this->assertSame('https://chromewebstore.google.com/detail/affilstack/abc', config('extension.store_url'));
        $this->assertFalse(collect(Cache::get('api_key_settings:config'))->contains(fn ($v) => str_contains((string) $v, 'AIza')), 'Cached sealed, not in plain text.');

        Livewire::actingAs($admin)->test(ApiKeySettings::class)
            ->set('data.chrome_extension_store_url', 'https://evil.example/fake')
            ->call('save')
            ->assertHasFormErrors(['chrome_extension_store_url']);
    }
}
