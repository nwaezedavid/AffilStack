<?php

namespace Tests\Feature;

use App\Models\SecurityFinding;
use App\Models\User;
use App\Services\Agents\SecurityScanService;
use App\Services\AI\AIProvider;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

/**
 * Tom, the Security Agent (AI agents phase, agent #1): real, deterministic
 * checks, an AI-written explanation on top, deduped across repeated scans.
 */
class SecurityScanServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // checkAdminsMissingTwoFactor() always runs during scan() and looks
        // up the admin/super-admin roles — every test here needs them to exist.
        $this->seed(RolesSeeder::class);

        // Keep the composer-audit check quiet and deterministic in these
        // tests — its own behavior isn't what's under test here.
        Process::fake(['composer audit*' => Process::result(output: json_encode(['advisories' => [], 'abandoned' => []]))]);

        $this->app->instance(AIProvider::class, new class implements AIProvider
        {
            public int $calls = 0;

            public function generateText(string $systemPrompt, string $userPrompt, array $options = []): string
            {
                return '';
            }

            public function generateJson(string $systemPrompt, string $userPrompt, array $options = []): array
            {
                $this->calls++;

                return ['summary' => 'A test summary.', 'suggested_fix' => 'A test suggested fix.'];
            }

            public function generateImage(string $prompt, array $options = []): string
            {
                return '';
            }
        });
    }

    public function test_it_flags_debug_mode_enabled_in_production_with_an_automatic_fix(): void
    {
        config(['app.env' => 'production', 'app.debug' => true]);

        app(SecurityScanService::class)->scan();

        $finding = SecurityFinding::where('category', 'config')
            ->where('title', 'like', 'Debug mode%')
            ->first();

        $this->assertNotNull($finding);
        $this->assertSame('critical', $finding->severity);
        $this->assertSame('A test summary.', $finding->ai_summary);
        $this->assertTrue($finding->isFixable());
        $this->assertSame('env_set', $finding->fix_action['type']);
        $this->assertSame('APP_DEBUG', $finding->fix_action['params']['key']);
    }

    public function test_it_does_not_flag_debug_mode_when_disabled_or_outside_production(): void
    {
        // secure_cookie=true keeps the *other* production-only check quiet
        // so this test isolates the debug-mode check specifically.
        config(['app.env' => 'production', 'app.debug' => false, 'session.secure_cookie' => true]);
        app(SecurityScanService::class)->scan();
        $this->assertDatabaseCount('security_findings', 0);

        config(['app.env' => 'local', 'app.debug' => true]);
        app(SecurityScanService::class)->scan();
        $this->assertDatabaseCount('security_findings', 0);
    }

    public function test_it_flags_admins_missing_two_factor_authentication_with_no_automatic_fix(): void
    {
        $admin = User::factory()->create(['two_factor_secret' => null]);
        $admin->assignRole('admin');

        app(SecurityScanService::class)->scan();

        $finding = SecurityFinding::where('category', 'auth')->first();

        $this->assertNotNull($finding);
        $this->assertContains($admin->email, $finding->evidence['emails']);
        $this->assertFalse($finding->isFixable());
    }

    public function test_a_still_open_finding_is_deduped_across_repeated_scans_without_a_second_ai_call(): void
    {
        config(['app.env' => 'production', 'app.debug' => true, 'session.secure_cookie' => true]);
        $ai = $this->app->make(AIProvider::class);

        app(SecurityScanService::class)->scan();
        app(SecurityScanService::class)->scan();

        $this->assertDatabaseCount('security_findings', 1);
        $this->assertSame(1, $ai->calls);
    }

    public function test_a_dismissed_findings_fingerprint_can_reopen_on_a_later_scan(): void
    {
        config(['app.env' => 'production', 'app.debug' => true, 'session.secure_cookie' => true]);

        app(SecurityScanService::class)->scan();
        SecurityFinding::first()->update(['status' => SecurityFinding::STATUS_DISMISSED]);

        app(SecurityScanService::class)->scan();

        $this->assertDatabaseCount('security_findings', 2);
    }

    public function test_it_finds_no_unencrypted_credential_columns_in_the_real_schema(): void
    {
        // Every credential-shaped column in this app's actual models is
        // already protected — by an encrypted/hashed cast, a verified
        // framework mechanism (Fortify's own encrypt/decrypt, remember_token),
        // or a verified one-way hash elsewhere in the app. This is the
        // "prove it's currently clean" counterpart to the fixture-based
        // tests below, which prove the check *would* fire on a real gap.
        $findings = $this->invokeCheck('checkUnencryptedSecretColumns');

        $this->assertSame([], $findings);
    }

    public function test_column_is_hashed_not_encrypted_recognizes_real_hash_assignments_app_wide(): void
    {
        // ApiToken hashes its own column ('token_hash' => hash('sha256', ...)
        // inside the model); AffiliateApplication's is hashed from its
        // service class instead ('set_password_token' => hash('sha256', ...)
        // in AffiliateApplicationService) — the check has to find both,
        // since it can't assume the hashing always happens in the model.
        $this->assertTrue($this->invokeCheck('columnIsHashedNotEncrypted', ['token_hash']));
        $this->assertTrue($this->invokeCheck('columnIsHashedNotEncrypted', ['set_password_token']));
        $this->assertFalse($this->invokeCheck('columnIsHashedNotEncrypted', ['definitely_not_a_real_column_anywhere']));
    }

    public function test_it_finds_no_ai_prompt_credential_leaks_in_the_real_codebase(): void
    {
        $findings = $this->invokeCheck('checkAiPromptCredentialInterpolation');

        $this->assertSame([], $findings);
    }

    public function test_find_credential_leaks_in_source_catches_a_secret_read_directly_inside_an_ai_call(): void
    {
        $source = <<<'PHP'
            <?php
            class Example
            {
                public function run(): string
                {
                    return $this->ai->generateText(
                        'You are a helpful assistant.',
                        'Use this key: '.env('OPENAI_API_KEY')
                    );
                }
            }
            PHP;

        $offending = $this->invokeCheck('findCredentialLeaksInSource', [$source]);

        $this->assertCount(1, $offending);
        $this->assertSame('$ai->generateText(...)', $offending[0]['call']);
        $this->assertStringContainsString('OPENAI_API_KEY', $offending[0]['expression']);
    }

    public function test_find_credential_leaks_in_source_ignores_non_ai_calls_and_non_secret_keys(): void
    {
        // Regression fixture for GoogleMapsLeadService: reading a
        // credential-shaped config() value to call a *different* third-party
        // API entirely, with zero AI-provider call anywhere in the file,
        // must never be flagged — an earlier, file-wide version of this
        // check false-positived on exactly this shape.
        $unrelatedApiCall = <<<'PHP'
            <?php
            class GoogleMapsLeadServiceFixture
            {
                public function __construct(protected ?string $apiKey = null)
                {
                    $this->apiKey ??= config('services.google_places.api_key');
                }
            }
            PHP;

        $this->assertSame([], $this->invokeCheck('findCredentialLeaksInSource', [$unrelatedApiCall]));

        // A real AI call whose arguments only read non-secret-shaped config
        // (a model name) must also stay quiet.
        $safeAiCall = <<<'PHP'
            <?php
            class Example
            {
                public function run(): string
                {
                    return $this->ai->generateText('system', config('ai.openai.text_model'));
                }
            }
            PHP;

        $this->assertSame([], $this->invokeCheck('findCredentialLeaksInSource', [$safeAiCall]));
    }

    public function test_extract_balanced_parens_handles_nesting_and_multiline_calls(): void
    {
        $source = 'foo(bar(1, 2), "text with ) inside", [multi, line])->baz();';
        $openParenPos = strpos($source, '(');

        $result = $this->invokeCheck('extractBalancedParens', [$source, $openParenPos]);

        $this->assertSame('bar(1, 2), "text with ) inside", [multi, line]', $result);
    }

    public function test_find_credential_leaks_in_source_is_not_confused_by_a_parenthetical_remark_in_the_prompt_text(): void
    {
        // A system prompt like "Keep the answer short (2-3 sentences)." is
        // completely ordinary AI-prompt writing — its own ")" must not be
        // mistaken for the end of the generateText(...) call, which would
        // otherwise truncate the captured arguments before ever reaching the
        // real env() call further along in the same call.
        $source = <<<'PHP'
            <?php
            class Example
            {
                public function run(): string
                {
                    return $this->ai->generateText(
                        'Keep the answer short (2-3 sentences).',
                        'Use this key: '.env('OPENAI_API_KEY')
                    );
                }
            }
            PHP;

        $offending = $this->invokeCheck('findCredentialLeaksInSource', [$source]);

        $this->assertCount(1, $offending);
        $this->assertStringContainsString('OPENAI_API_KEY', $offending[0]['expression']);
    }

    /**
     * Invokes a protected/private method on a real SecurityScanService
     * instance — every method under test here is pure logic with no side
     * effects, so reflection is simpler and safer than making them public
     * just for tests.
     */
    protected function invokeCheck(string $method, array $args = []): mixed
    {
        $service = app(SecurityScanService::class);
        $reflection = new \ReflectionMethod($service, $method);
        $reflection->setAccessible(true);

        return $reflection->invoke($service, ...$args);
    }
}
