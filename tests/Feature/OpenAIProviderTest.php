<?php

namespace Tests\Feature;

use App\Services\AI\AIGenerationException;
use App\Services\AI\OpenAIProvider;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The app-wide (OpenAI-backed) AIProvider. generateImage() in particular
 * has to handle two genuinely different response shapes depending on which
 * model OPENAI_IMAGE_MODEL points at: dall-e-2/3 return a hosted url,
 * while the current gpt-image-* family (config/ai.php's default, since
 * dall-e-3 itself was deprecated 2025-11-14 and shut down 2026-05-12)
 * returns base64 only and never a url at all — confirmed against OpenAI's
 * own API reference, not assumed.
 */
class OpenAIProviderTest extends TestCase
{
    protected function provider(): OpenAIProvider
    {
        return new OpenAIProvider(
            apiKey: 'sk-test',
            baseUrl: 'https://api.openai.com/v1',
            textModel: 'gpt-4o-mini',
            imageModel: 'gpt-image-2',
            timeout: 30,
        );
    }

    public function test_generate_text_returns_the_first_choices_message_content(): void
    {
        Http::fake(['api.openai.com/v1/chat/completions' => Http::response([
            'choices' => [['message' => ['content' => 'Generated text.']]],
        ])]);

        $result = $this->provider()->generateText('system', 'user');

        $this->assertSame('Generated text.', $result);
    }

    public function test_generate_text_throws_when_the_request_fails(): void
    {
        Http::fake(['api.openai.com/v1/chat/completions' => Http::response(['error' => ['message' => 'bad key']], 401)]);

        $this->expectException(AIGenerationException::class);

        $this->provider()->generateText('system', 'user');
    }

    public function test_generate_json_decodes_the_response_content_as_json(): void
    {
        Http::fake(['api.openai.com/v1/chat/completions' => Http::response([
            'choices' => [['message' => ['content' => '{"headline": "Great Deal"}']]],
        ])]);

        $result = $this->provider()->generateJson('system', 'user');

        $this->assertSame(['headline' => 'Great Deal'], $result);
    }

    public function test_generate_json_throws_when_the_response_is_not_valid_json(): void
    {
        Http::fake(['api.openai.com/v1/chat/completions' => Http::response([
            'choices' => [['message' => ['content' => 'not json']]],
        ])]);

        $this->expectException(AIGenerationException::class);

        $this->provider()->generateJson('system', 'user');
    }

    public function test_generate_image_returns_a_hosted_url_when_the_model_provides_one(): void
    {
        // dall-e-2/3 response shape.
        Http::fake(['api.openai.com/v1/images/generations' => Http::response([
            'data' => [['url' => 'https://cdn.openai.com/generated-image.png']],
        ])]);

        $result = $this->provider()->generateImage('a picture of a cat');

        $this->assertSame('https://cdn.openai.com/generated-image.png', $result);
    }

    public function test_generate_image_converts_a_base64_response_into_a_data_uri(): void
    {
        // gpt-image-* response shape — this is the real, current default
        // (config/ai.php: OPENAI_IMAGE_MODEL=gpt-image-2) and the exact
        // case that silently produced an empty string before this fix,
        // since these models never return a "url" field at all.
        Http::fake(['api.openai.com/v1/images/generations' => Http::response([
            'data' => [['b64_json' => 'ZmFrZS1pbWFnZS1ieXRlcw==']],
        ])]);

        $result = $this->provider()->generateImage('a picture of a cat');

        $this->assertSame('data:image/png;base64,ZmFrZS1pbWFnZS1ieXRlcw==', $result);
    }

    public function test_generate_image_returns_an_empty_string_when_neither_field_is_present(): void
    {
        Http::fake(['api.openai.com/v1/images/generations' => Http::response(['data' => [[]]])]);

        $result = $this->provider()->generateImage('a picture of a cat');

        $this->assertSame('', $result);
    }

    public function test_generate_image_throws_when_the_request_fails(): void
    {
        Http::fake(['api.openai.com/v1/images/generations' => Http::response(['error' => ['message' => 'model not found']], 404)]);

        $this->expectException(AIGenerationException::class);

        $this->provider()->generateImage('a picture of a cat');
    }
}
