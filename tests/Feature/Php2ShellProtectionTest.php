<?php

namespace Tests\Feature;

use App\Http\Middleware\RejectExecutableUploads;
use App\Models\SecurityFinding;
use App\Models\User;
use App\Notifications\SecurityAlert;
use App\Services\Agents\SecurityScanService;
use App\Services\AI\AIProvider;
use App\Support\Security\PhpRuntime;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * PHP2Shell (CVE-2026-109618 / CVE-2026-109619) and attacks like it: the
 * server's PHP is checked against known fixes and php.net, admins are
 * alerted, uploads can't carry code, PHP can't run outside index.php, and
 * web shells are found.
 */
class Php2ShellProtectionTest extends TestCase
{
    use RefreshDatabase;

    protected string $record;

    protected string $shellDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->record = storage_path('framework/php-runtime.json');
        $this->shellDirectory = storage_path('app/public/php2shell-test');
        File::delete($this->record);

        Route::middleware('web')->post('/_test/upload', fn () => 'stored');
        Route::middleware('api')->post('/_test/api-upload', fn () => response()->json(['stored' => true]));
    }

    protected function tearDown(): void
    {
        File::delete($this->record);
        File::deleteDirectory($this->shellDirectory);

        parent::tearDown();
    }

    // --- Uploads ---------------------------------------------------------------

    public function test_php_files_and_server_config_files_cannot_be_uploaded(): void
    {
        foreach (['shell.php', 'shell.PHTML', 'photo.php.jpg', 'archive.phar', '.htaccess', '.user.ini', 'script.php7'] as $name) {
            $this->post('/_test/upload', ['file' => UploadedFile::fake()->createWithContent($name, 'hello')])
                ->assertRedirect()
                ->assertSessionHasErrors('file');
        }

        $this->postJson('/_test/api-upload', ['file' => UploadedFile::fake()->createWithContent('x.php', 'hello')])->assertStatus(422);
    }

    public function test_php_code_hidden_in_an_image_is_refused(): void
    {
        $polyglot = "\x89PNG\r\n\x1a\n".str_repeat("\0", 64)."<?php system(\$_GET['c']); ?>";
        $this->post('/_test/upload', ['logo' => UploadedFile::fake()->createWithContent('logo.png', $polyglot)])->assertSessionHasErrors('file');

        $nested = ['items' => [['image' => UploadedFile::fake()->createWithContent('a.gif', 'GIF89a<?= `id` ?>')]]];
        $this->post('/_test/upload', $nested)->assertSessionHasErrors('file');
    }

    public function test_ordinary_files_upload_normally(): void
    {
        $this->post('/_test/upload', ['file' => UploadedFile::fake()->image('photo.jpg', 40, 40)])->assertOk()->assertSee('stored');
        $this->post('/_test/upload', ['file' => UploadedFile::fake()->createWithContent('logo.svg', '<?xml version="1.0"?><svg xmlns="http://www.w3.org/2000/svg"></svg>')])->assertOk();
        $this->post('/_test/upload', ['file' => UploadedFile::fake()->createWithContent('contacts.csv', "email,name\nada@example.com,Ada")])->assertOk();
        $this->post('/_test/upload', ['file' => UploadedFile::fake()->createWithContent('zap.json', '{"steps":[]}')])->assertOk();

        // "<?=" in binary data is chance, not code.
        $this->post('/_test/upload', ['file' => UploadedFile::fake()->createWithContent('pic.png', "\x89PNG\0\0<?=\0\0")])->assertOk();
    }

    public function test_the_upload_guard_reads_large_files_in_chunks(): void
    {
        $content = str_repeat('a', 1048576 - 3).'<?php echo 1;';
        $file = UploadedFile::fake()->createWithContent('notes.txt', $content);

        $this->assertTrue(app(RejectExecutableUploads::class)->isExecutable($file));
    }

    // --- The PHP version ---------------------------------------------------------

    public function test_php_versions_before_the_php2shell_fix_are_critical(): void
    {
        foreach (['8.4.26', '8.4.0', '8.5.11'] as $version) {
            $problems = PhpRuntime::problemsFor($version, false);
            $this->assertSame('critical', $problems[0]['severity'], $version);
            $this->assertStringContainsString('PHP2Shell', $problems[0]['title']);
        }

        $this->assertSame([], PhpRuntime::problemsFor('8.4.27', false));
        $this->assertSame([], PhpRuntime::problemsFor('8.5.12', false));
        $this->assertStringContainsString('not a supported version', PhpRuntime::problemsFor('8.3.40', false)[0]['title']);
    }

    public function test_a_host_backported_fix_is_accepted_from_the_build_date(): void
    {
        // Hostinger: PHP 8.5.11 rebuilt with the fix on the release day.
        $problems = PhpRuntime::problemsFor('8.5.11', false, '2026-10-21');
        $this->assertCount(1, $problems);
        $this->assertSame('low', $problems[0]['severity']);
        $this->assertStringContainsString('backport', $problems[0]['title']);

        // Built before the fix: still critical.
        $this->assertSame('critical', PhpRuntime::problemsFor('8.5.11', false, '2026-10-20')[0]['severity']);

        $this->writeWebRuntime('8.5.11', [], '2026-10-21');
        $this->assertFalse(PhpRuntime::webIsCriticallyVulnerable());
        $this->artisan('security:check-php')->assertSuccessful();

        $this->writeWebRuntime('8.5.11', [], '2026-09-30');
        $this->assertTrue(PhpRuntime::webIsCriticallyVulnerable());
    }

    public function test_the_php_build_date_is_read_from_php_itself(): void
    {
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', (string) PhpRuntime::buildDate());

        PhpRuntime::recordCurrent();
        $this->assertSame(PhpRuntime::buildDate(), PhpRuntime::webRuntime()['build_date']);
    }

    public function test_a_later_php_net_security_release_is_judged_by_build_date_on_backporting_hosts(): void
    {
        config(['security.php_release_check' => true]);
        Http::fake(['www.php.net/*' => Http::response(['version' => '8.5.13', 'tags' => ['security'], 'date' => '01 Dec 2026'])]);

        $this->assertSame('high', collect(PhpRuntime::problemsFor('8.5.11', true, '2026-10-21'))->firstWhere('severity', 'high')['severity']);
        $this->assertNull(collect(PhpRuntime::problemsFor('8.5.11', true, '2026-12-02'))->firstWhere('severity', 'high'));
    }

    public function test_a_newer_security_release_on_php_net_is_reported_even_without_a_code_update(): void
    {
        config(['security.php_release_check' => true]);
        Http::fake(['www.php.net/*' => Http::sequence()
            ->push(['version' => '8.4.31', 'tags' => ['security'], 'supported_versions' => ['8.4', '8.5']])
            ->push('down', 500)]);

        $problems = PhpRuntime::problemsFor('8.4.27');

        $this->assertCount(1, $problems);
        $this->assertSame('high', $problems[0]['severity']);
        $this->assertStringContainsString('8.4.31 is a security release', $problems[0]['title']);

        // php.net unreachable: only the known fixes are checked.
        Cache::flush();
        $this->assertSame([], PhpRuntime::problemsFor('8.4.27'));
    }

    public function test_the_website_php_version_is_recorded_and_risky_settings_are_reported(): void
    {
        PhpRuntime::recordCurrent();
        $web = PhpRuntime::webRuntime();

        $this->assertSame(PHP_VERSION, $web['version']);
        $this->assertArrayHasKey('session.upload_progress.enabled', $web['ini']);

        $this->assertCount(1, PhpRuntime::riskyIniProblems(['session.upload_progress.enabled' => '1']));
        $this->assertSame([], PhpRuntime::riskyIniProblems(['session.upload_progress.enabled' => 'Off', 'post_max_size' => '40M']));
        $this->assertStringContainsString('2048M', PhpRuntime::riskyIniProblems(['post_max_size' => '2048M'])[0]['title']);
        $this->assertCount(1, PhpRuntime::riskyIniProblems(['post_max_size' => '0']));

        // Hostinger's website settings with proc_open switched back on.
        $open = PhpRuntime::riskyIniProblems(['disable_functions' => 'system, exec, shell_exec, passthru, popen, symlink']);
        $this->assertStringContainsString('proc_open', $open[0]['title']);
        $this->assertSame([], PhpRuntime::riskyIniProblems(['disable_functions' => 'system,exec,shell_exec,passthru,popen,proc_open']));
    }

    public function test_the_shipped_user_ini_only_uses_settings_php_accepts_there(): void
    {
        $settings = parse_ini_file(public_path('.user.ini'));
        $all = ini_get_all(null, true);

        $this->assertSame('0', (string) $settings['session.upload_progress.enabled']);

        foreach (array_keys($settings) as $key) {
            $this->assertArrayHasKey($key, $all, "{$key} is not a PHP setting");
            $this->assertTrue(($all[$key]['access'] & INI_PERDIR) === INI_PERDIR, "{$key} can't be set in .user.ini");
        }
    }

    public function test_only_index_php_may_run_as_php_on_the_web_server(): void
    {
        foreach ([public_path('.htaccess'), storage_path('app/public/.htaccess')] as $htaccess) {
            $this->assertStringContainsString('phtml|pht|phar|phps', (string) file_get_contents($htaccess), $htaccess);
        }

        $this->assertStringContainsString('RewriteRule \.(php[0-9]?|', (string) file_get_contents(public_path('.htaccess')));
    }

    public function test_the_deploy_check_fails_on_an_unpatched_website_php(): void
    {
        $this->writeWebRuntime('8.4.26');

        $this->artisan('security:check-php')->assertFailed();

        $this->writeWebRuntime('8.4.27');
        $this->artisan('security:check-php')->assertSuccessful();
    }

    // --- Tom, the Security Agent ----------------------------------------------

    public function test_tom_reports_an_unpatched_website_php_alerts_the_admins_and_closes_it_once_updated(): void
    {
        $this->prepareScan();
        Notification::fake();
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->writeWebRuntime('8.4.26', ['session.upload_progress.enabled' => '1']);
        app(SecurityScanService::class)->scan();

        $finding = SecurityFinding::where('category', 'php_runtime')->where('severity', 'critical')->sole();
        $this->assertStringContainsString('PHP2Shell', $finding->title);
        $this->assertSame(['website'], $finding->evidence['where']);
        $this->assertTrue(SecurityFinding::where('category', 'php_runtime')->where('title', 'like', '%upload_progress%')->exists());
        Notification::assertSentTo($admin, SecurityAlert::class, fn (SecurityAlert $alert) => collect($alert->findings)->contains('id', $finding->id));

        $this->assertTrue(PhpRuntime::webIsCriticallyVulnerable());
        $this->assertStringContainsString('PHP2Shell', view('filament.security.php-runtime-banner', ['web' => PhpRuntime::webRuntime()])->render());

        // The host updates PHP: the next scan closes the finding.
        $this->writeWebRuntime('8.4.27', ['session.upload_progress.enabled' => '0']);
        app(SecurityScanService::class)->scan();

        $this->assertSame(SecurityFinding::STATUS_FIXED, $finding->fresh()->status);
        $this->assertSame(0, SecurityFinding::where('category', 'php_runtime')->where('status', 'open')->count());
        $this->assertFalse(PhpRuntime::webIsCriticallyVulnerable());
    }

    public function test_tom_finds_web_shells_in_upload_folders(): void
    {
        $this->prepareScan();
        File::ensureDirectoryExists($this->shellDirectory);
        file_put_contents($this->shellDirectory.'/cache.php', '<?php eval($_POST["x"]);');
        file_put_contents($this->shellDirectory.'/avatar.jpg', "\xFF\xD8\xFF<?php system(\$_GET['c']);");
        file_put_contents($this->shellDirectory.'/photo.webp', 'RIFF....WEBP');

        app(SecurityScanService::class)->scan();

        $finding = SecurityFinding::where('category', 'web_shell')->sole();
        $this->assertSame('critical', $finding->severity);
        $this->assertContains('storage/app/public/php2shell-test/cache.php', $finding->evidence['files']);
        $this->assertContains('storage/app/public/php2shell-test/avatar.jpg (PHP code inside)', $finding->evidence['files']);
        $this->assertNotContains('storage/app/public/php2shell-test/photo.webp', $finding->evidence['files']);
        $this->assertNotContains('public/index.php', $finding->evidence['files']);

        File::deleteDirectory($this->shellDirectory);
        app(SecurityScanService::class)->scan();
        $this->assertSame(SecurityFinding::STATUS_FIXED, $finding->fresh()->status);
    }

    /**
     * @param  array<string, string>  $ini
     */
    protected function writeWebRuntime(string $version, array $ini = [], ?string $buildDate = null): void
    {
        file_put_contents($this->record, json_encode(['version' => $version, 'sapi' => 'litespeed', 'build_date' => $buildDate, 'ini' => $ini, 'recorded_at' => time()]));
    }

    protected function prepareScan(): void
    {
        $this->seed(RolesSeeder::class);
        Process::fake(['composer audit*' => Process::result(output: json_encode(['advisories' => [], 'abandoned' => []]))]);

        $this->app->instance(AIProvider::class, new class implements AIProvider
        {
            public function generateText(string $systemPrompt, string $userPrompt, array $options = []): string
            {
                return '';
            }

            public function generateJson(string $systemPrompt, string $userPrompt, array $options = []): array
            {
                return ['summary' => 'Summary.', 'suggested_fix' => 'Fix.'];
            }

            public function generateImage(string $prompt, array $options = []): string
            {
                return '';
            }
        });
    }
}
