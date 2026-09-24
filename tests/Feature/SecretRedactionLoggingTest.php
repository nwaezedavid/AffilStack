<?php

namespace Tests\Feature;

use App\Logging\RedactsSecrets;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * End-to-end proof that the redaction tap is really wired onto a live
 * Monolog pipeline the way config/logging.php declares it — reading
 * SecretRedactionProcessorTest's unit coverage of the redaction rules
 * themselves isn't enough on its own, since the wiring (LogManager::tap(),
 * and 'stack' merging its leaf channels' processors) is exactly the kind of
 * thing that looks right on paper and silently doesn't fire in practice.
 */
class SecretRedactionLoggingTest extends TestCase
{
    protected string $logPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->logPath = storage_path('logs/redaction_test.log');
        File::ensureDirectoryExists(dirname($this->logPath));
        File::put($this->logPath, '');
    }

    protected function tearDown(): void
    {
        if (File::exists($this->logPath)) {
            File::delete($this->logPath);
        }

        parent::tearDown();
    }

    protected function configureTestChannel(): void
    {
        config(['logging.channels.redaction_test' => [
            'driver' => 'single',
            'path' => $this->logPath,
            'level' => 'debug',
            'tap' => [RedactsSecrets::class],
        ]]);
    }

    public function test_a_real_configured_secret_never_reaches_the_log_file(): void
    {
        // Simulates a real integration's key living in config() the normal
        // way (a services.* array populated from env()) — RedactsSecrets
        // has to find this dynamically, not via any hardcoded key name.
        config(['services.fake_integration' => ['api_key' => 'super-real-secret-abc123']]);
        $this->configureTestChannel();

        Log::channel('redaction_test')->error('Request to fake integration failed with key super-real-secret-abc123');

        $contents = File::get($this->logPath);

        $this->assertStringNotContainsString('super-real-secret-abc123', $contents);
        $this->assertStringContainsString('[REDACTED]', $contents);
    }

    public function test_a_credential_shaped_context_key_never_reaches_the_log_file(): void
    {
        $this->configureTestChannel();

        Log::channel('redaction_test')->info('Integration connected', [
            'integration' => 'stripe',
            'api_key' => 'sk_live_shouldneverbelogged',
        ]);

        $contents = File::get($this->logPath);

        $this->assertStringNotContainsString('sk_live_shouldneverbelogged', $contents);
        $this->assertStringContainsString('stripe', $contents);
    }

    public function test_the_stack_driver_inherits_the_tap_from_its_leaf_channels(): void
    {
        $this->configureTestChannel();
        config(['logging.channels.redaction_test_stack' => [
            'driver' => 'stack',
            'channels' => ['redaction_test'],
            'ignore_exceptions' => false,
        ]]);

        Log::channel('redaction_test_stack')->warning('Leaked value: sk_live_shouldneverbelogged');

        $contents = File::get($this->logPath);

        $this->assertStringNotContainsString('sk_live_shouldneverbelogged', $contents);
    }
}
