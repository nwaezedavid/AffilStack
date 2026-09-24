<?php

namespace Tests\Unit;

use App\Logging\SecretRedactionProcessor;
use DateTimeImmutable;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\TestCase;

/**
 * Pure Monolog-level unit tests — no Laravel app needed, since the
 * processor only ever touches the LogRecord it's handed. The end-to-end
 * "it's actually wired onto every real channel" proof lives in
 * SecretRedactionLoggingTest instead.
 */
class SecretRedactionProcessorTest extends TestCase
{
    protected function record(string $message, array $context = []): LogRecord
    {
        return new LogRecord(new DateTimeImmutable, 'testing', Level::Info, $message, $context);
    }

    public function test_it_strips_a_known_secret_value_wherever_it_appears_in_the_message(): void
    {
        $processor = new SecretRedactionProcessor(['sk-realsecretvalue123']);

        $result = ($processor)($this->record('Calling OpenAI with key sk-realsecretvalue123 failed'));

        $this->assertStringNotContainsString('sk-realsecretvalue123', $result->message);
        $this->assertStringContainsString(SecretRedactionProcessor::REDACTED, $result->message);
    }

    public function test_it_redacts_a_context_value_whose_own_key_looks_like_a_credential(): void
    {
        $processor = new SecretRedactionProcessor;

        $result = ($processor)($this->record('Request failed', [
            'api_key' => 'whatever-this-value-is',
            'user_id' => 42,
        ]));

        $this->assertSame(SecretRedactionProcessor::REDACTED, $result->context['api_key']);
        $this->assertSame(42, $result->context['user_id']);
    }

    public function test_it_redacts_credential_shaped_keys_inside_nested_context_arrays(): void
    {
        $processor = new SecretRedactionProcessor;

        $result = ($processor)($this->record('Gateway call', [
            'request' => [
                'headers' => [
                    'Authorization' => 'Bearer some-real-token-value',
                    'Accept' => 'application/json',
                ],
            ],
        ]));

        $this->assertSame(SecretRedactionProcessor::REDACTED, $result->context['request']['headers']['Authorization']);
        $this->assertSame('application/json', $result->context['request']['headers']['Accept']);
    }

    public function test_it_redacts_a_bearer_token_embedded_inside_a_longer_string(): void
    {
        $processor = new SecretRedactionProcessor;

        $result = ($processor)($this->record('Guzzle exception: sent header Authorization: Bearer abc123.def456-ghi'));

        $this->assertStringNotContainsString('abc123.def456-ghi', $result->message);
        $this->assertStringContainsString('Bearer '.SecretRedactionProcessor::REDACTED, $result->message);
    }

    public function test_it_redacts_well_known_provider_key_prefixes(): void
    {
        $processor = new SecretRedactionProcessor;

        $openAiStyle = ($processor)($this->record('key was sk-abcdefghijklmnopqrst'));
        $stripeStyle = ($processor)($this->record('key was sk_live_abcdefghijklmnop'));
        $awsStyle = ($processor)($this->record('key id AKIAABCDEFGHIJKLMNOP'));

        $this->assertStringNotContainsString('sk-abcdefghijklmnopqrst', $openAiStyle->message);
        $this->assertStringNotContainsString('sk_live_abcdefghijklmnop', $stripeStyle->message);
        $this->assertStringNotContainsString('AKIAABCDEFGHIJKLMNOP', $awsStyle->message);
    }

    public function test_it_redacts_a_key_value_pair_inside_a_query_string(): void
    {
        $processor = new SecretRedactionProcessor;

        $result = ($processor)($this->record('GET https://api.example.com/v1/thing?api_key=abcdef123456&format=json'));

        $this->assertStringNotContainsString('abcdef123456', $result->message);
        $this->assertStringContainsString('format=json', $result->message);
    }

    public function test_it_leaves_ordinary_messages_and_context_untouched(): void
    {
        $processor = new SecretRedactionProcessor(['some-known-secret-value']);

        $result = ($processor)($this->record('User 42 generated a blog article', [
            'user_id' => 42,
            'module' => 'blog_article',
            'duration_ms' => 1234,
        ]));

        $this->assertSame('User 42 generated a blog article', $result->message);
        $this->assertSame(['user_id' => 42, 'module' => 'blog_article', 'duration_ms' => 1234], $result->context);
    }

    public function test_it_does_not_attempt_to_stringify_objects_in_context(): void
    {
        $processor = new SecretRedactionProcessor;
        $exception = new \RuntimeException('boom');

        $result = ($processor)($this->record('Something failed', ['exception' => $exception]));

        $this->assertSame($exception, $result->context['exception']);
    }

    public function test_it_ignores_short_known_secrets_to_avoid_mass_redacting_common_substrings(): void
    {
        // A 3-character "secret" (e.g. an empty/placeholder config value)
        // must never be used for verbatim redaction — it would blow away
        // unrelated text that happens to contain the same short substring.
        $processor = new SecretRedactionProcessor(['abc']);

        $result = ($processor)($this->record('The abc method ran successfully'));

        $this->assertSame('The abc method ran successfully', $result->message);
    }
}
