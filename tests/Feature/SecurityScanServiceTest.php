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
}
