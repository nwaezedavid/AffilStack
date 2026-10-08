<?php

namespace Tests\Feature;

use App\Filament\Pages\AiEngineSettings;
use App\Models\AiEngineSetting;
use App\Models\User;
use App\Services\AI\AIGenerationException;
use App\Services\AI\AIProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AiEngineRoutingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'ai.openai.api_key' => 'sk-openai-test',
            'ai.openai.base_url' => 'https://api.openai.com/v1',
            'ai.anthropic.api_key' => null,
        ]);
    }

    protected function useClaude(bool $fallback = true): void
    {
        AiEngineSetting::current()->update([
            'text_engine' => AiEngineSetting::ENGINE_ANTHROPIC,
            'fallback_enabled' => $fallback,
            'credentials' => ['anthropic_api_key' => 'sk-ant-test', 'anthropic_model' => 'claude-sonnet-5-5'],
        ]);
    }

    public function test_openai_writes_text_by_default(): void
    {
        Http::fake(['api.openai.com/*' => Http::response(['choices' => [['message' => ['content' => 'from openai']]]])]);

        $this->assertSame('from openai', app(AIProvider::class)->generateText('system', 'user'));
        Http::assertSentCount(1);
    }

    public function test_claude_writes_text_and_json_when_selected(): void
    {
        $this->useClaude();
        Http::fake([
            'api.anthropic.com/v1/messages' => Http::sequence()
                ->push(['content' => [['type' => 'text', 'text' => 'from claude']]])
                ->push(['content' => [['type' => 'text', 'text' => "Here you go:\n```json\n{\"angle\": \"time saver\"}\n```"]]]),
        ]);

        $ai = app(AIProvider::class);

        $this->assertSame('from claude', $ai->generateText('system', 'user'));
        $this->assertSame(['angle' => 'time saver'], $ai->generateJson('system', 'user'));

        Http::assertSent(fn (Request $request) => $request->url() === 'https://api.anthropic.com/v1/messages'
            && $request->hasHeader('x-api-key', 'sk-ant-test')
            && $request['model'] === 'claude-sonnet-5-5'
            && $request['system'] === 'system');
    }

    public function test_an_openai_model_override_is_not_sent_to_claude(): void
    {
        $this->useClaude();
        Http::fake(['api.anthropic.com/*' => Http::response(['content' => [['type' => 'text', 'text' => 'ok']]])]);

        app(AIProvider::class)->generateText('s', 'u', ['model' => 'gpt-4o']);

        Http::assertSent(fn (Request $request) => $request['model'] === 'claude-sonnet-5-5');
    }

    public function test_it_falls_back_to_openai_when_claude_fails(): void
    {
        $this->useClaude();
        Http::fake([
            'api.anthropic.com/*' => Http::response(['error' => ['message' => 'overloaded']], 529),
            'api.openai.com/*' => Http::response(['choices' => [['message' => ['content' => 'rescued by openai']]]]),
        ]);

        $this->assertSame('rescued by openai', app(AIProvider::class)->generateText('s', 'u'));
    }

    public function test_without_fallback_the_failure_is_reported(): void
    {
        $this->useClaude(fallback: false);
        Http::fake(['api.anthropic.com/*' => Http::response(['error' => ['message' => 'overloaded']], 529)]);

        $this->expectException(AIGenerationException::class);

        app(AIProvider::class)->generateText('s', 'u');
    }

    public function test_images_always_use_openai(): void
    {
        $this->useClaude();
        Http::fake(['api.openai.com/v1/images/generations' => Http::response(['data' => [['url' => 'https://img.test/a.png']]])]);

        $this->assertSame('https://img.test/a.png', app(AIProvider::class)->generateImage('a banner'));
    }

    public function test_choosing_claude_without_a_key_keeps_openai_working(): void
    {
        AiEngineSetting::current()->update(['text_engine' => AiEngineSetting::ENGINE_ANTHROPIC]);
        Http::fake(['api.openai.com/*' => Http::response(['choices' => [['message' => ['content' => 'still openai']]]])]);

        $this->assertSame('still openai', app(AIProvider::class)->generateText('s', 'u'));
    }

    public function test_admin_can_switch_to_claude_and_test_the_key(): void
    {
        Role::findOrCreate('super-admin', 'web');
        $admin = User::factory()->create();
        $admin->assignRole('super-admin');
        Http::fake(['api.anthropic.com/v1/models*' => Http::response(['data' => [['id' => 'claude-sonnet-5-5']]])]);

        Livewire::actingAs($admin)
            ->test(AiEngineSettings::class)
            ->set('data.text_engine', AiEngineSetting::ENGINE_ANTHROPIC)
            ->set('data.anthropic_api_key', 'sk-ant-new-key')
            ->set('data.anthropic_model', 'claude-opus-5-5')
            ->call('testAnthropic')
            ->assertHasNoErrors();

        $settings = AiEngineSetting::current();
        $this->assertSame(AiEngineSetting::ENGINE_ANTHROPIC, $settings->text_engine);
        $this->assertSame('sk-ant-new-key', $settings->anthropicApiKey());
        $this->assertSame('claude-opus-5-5', $settings->anthropicModel());
        $this->assertSame('success', $settings->anthropic_verification_status);
    }
}
