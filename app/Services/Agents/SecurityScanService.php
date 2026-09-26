<?php

namespace App\Services\Agents;

use App\Models\SecurityFinding;
use App\Models\User;
use App\Services\AI\AIGenerationException;
use App\Services\AI\AIProvider;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * Tom, the Security Agent. Runs a fixed set of real checks (never invented
 * ones — no simulated "vulnerabilities") and asks the AI only to translate
 * a check's raw evidence into a plain-English explanation and suggested
 * fix for the super-admin — the AI never decides what the fix *does*;
 * `fix_action` is set deterministically by the check itself, since that's
 * what SecurityFixExecutor eventually runs unattended.
 *
 * Findings are deduped by a fingerprint of (category, title): a still-open
 * finding just gets its detected_at bumped on a later scan instead of
 * spawning a duplicate row and another AI call.
 */
class SecurityScanService
{
    /**
     * Secret-shaped identifier: column names for checkUnencryptedSecretColumns(),
     * and env()/config() key names for checkAiPromptCredentialInterpolation().
     * Deliberately broad (better to over-catch a name and rule it out via a
     * verified allowlist entry than to silently miss a real one).
     */
    protected const SECRET_NAME_PATTERN = '/(api[_-]?key|apikey|secret|passwd|password|credential|private[_-]?key|access[_-]?key|client[_-]?secret|token)/i';

    protected const PROTECTED_CASTS = ['encrypted', 'encrypted:array', 'encrypted:json', 'encrypted:collection', 'hashed'];

    /**
     * Column names safe unencrypted on ANY table, by a framework-wide
     * convention rather than anything specific to this app:
     * Illuminate\Auth\Authenticatable's getRememberToken()/setRememberToken()
     * read and write this exact column name for any Authenticatable model,
     * and no first-party Laravel starter kit has ever encrypted it — it's a
     * rotating "remember me" token invalidated on password change, not a
     * standing credential.
     */
    protected const FRAMEWORK_COLUMN_NAMES = ['remember_token'];

    /**
     * Exact table.column pairs individually verified safe unencrypted: each
     * is a single-purpose capability token meant to leave the system
     * embedded in a URL (a mailed unsubscribe link, an email open-tracking
     * pixel, an analytics beacon), generated with enough randomness
     * (confirmed against each one's own generation code) to be unguessable.
     * Its security property is unguessability at generation time, not
     * confidentiality at rest, unlike an API key or password — encrypting
     * the stored copy would protect nothing.
     */
    protected const KNOWN_SAFE_COLUMNS = [
        'crm_contacts.unsubscribe_token',
        'crm_email_sends.tracking_token',
        'page_views.view_token',
    ];

    /**
     * $this->{property}->generate(Text|Json|Image)(...) — every real
     * AI-provider call site app-wide, regardless of the property name a
     * class injects its AIProvider under ($ai, $imageAi, ...). Matches only
     * real code, never a class name mentioned in a comment or docblock.
     */
    protected const AI_CALL_PATTERN = '/\$this->(\w+)->(generateText|generateJson|generateImage)\s*\(/';

    public function __construct(protected AIProvider $ai) {}

    /**
     * @return Collection<int, SecurityFinding>
     */
    public function scan(): Collection
    {
        $rawFindings = collect([
            ...$this->checkComposerAudit(),
            ...$this->checkDebugModeInProduction(),
            ...$this->checkSecureCookiesInProduction(),
            ...$this->checkAdminsMissingTwoFactor(),
            ...$this->checkUnencryptedSecretColumns(),
            ...$this->checkAiPromptCredentialInterpolation(),
        ]);

        return $rawFindings->map(fn (array $raw) => $this->persist($raw));
    }

    protected function persist(array $raw): SecurityFinding
    {
        $fingerprint = sha1($raw['category'].'|'.$raw['title']);

        $existing = SecurityFinding::where('fingerprint', $fingerprint)
            ->whereIn('status', [SecurityFinding::STATUS_OPEN, SecurityFinding::STATUS_SCHEDULED])
            ->first();

        if ($existing) {
            $existing->update(['detected_at' => now()]);

            return $existing;
        }

        [$summary, $suggestedFix] = $this->explain($raw);

        return SecurityFinding::create([
            'fingerprint' => $fingerprint,
            'category' => $raw['category'],
            'title' => $raw['title'],
            'severity' => $raw['severity'],
            'evidence' => $raw['evidence'] ?? [],
            'ai_summary' => $summary,
            'ai_suggested_fix' => $suggestedFix,
            'fix_action' => $raw['fix_action'] ?? null,
            'status' => SecurityFinding::STATUS_OPEN,
            'detected_at' => now(),
        ]);
    }

    /**
     * @return array{0: string, 1: string}
     */
    protected function explain(array $raw): array
    {
        $system = 'You are Tom, the Security Agent for a Laravel SaaS platform called AffilStack. '
            .'You have just detected a real issue — you are not inventing anything. Explain it in plain, '
            .'non-alarmist English for the platform owner (who is not necessarily a developer), then suggest '
            .'the safest way to fix it without disrupting active users or losing data. Keep both fields to 2-3 sentences.';

        $userPrompt = "Category: {$raw['category']}\nTitle: {$raw['title']}\nSeverity: {$raw['severity']}\n"
            .'Evidence: '.json_encode($raw['evidence'] ?? []);

        try {
            $result = $this->ai->generateJson($system, $userPrompt.
                "\n\nRespond as JSON: {\"summary\": string, \"suggested_fix\": string}");

            return [
                (string) ($result['summary'] ?? $raw['title']),
                (string) ($result['suggested_fix'] ?? 'Review and apply the standard fix for this issue.'),
            ];
        } catch (AIGenerationException $e) {
            Log::warning('Tom: AI explanation failed, falling back to the raw finding.', ['error' => $e->getMessage()]);

            return [$raw['title'], 'Review and apply the standard fix for this issue.'];
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function checkComposerAudit(): array
    {
        $result = Process::path(base_path())->run('composer audit --format=json --no-interaction');

        // composer audit exits non-zero precisely when it finds something
        // (1 = vulnerable, 2 = abandoned, 3 = both), so the exit code can't
        // gate this — only unparseable output means the audit didn't run.
        $data = json_decode($result->output(), true);

        if (! is_array($data)) {
            return [];
        }
        $advisories = $data['advisories'] ?? [];

        $findings = [];

        foreach ($advisories as $package => $issues) {
            foreach ($issues as $issue) {
                $patchedVersion = $this->extractPatchedVersion($issue['affectedVersions'] ?? '');

                $findings[] = [
                    'category' => 'dependency',
                    'title' => "Known vulnerability in {$package}: ".($issue['title'] ?? 'unspecified'),
                    'severity' => $issue['severity'] ?? 'medium',
                    'evidence' => ['package' => $package, 'advisory' => $issue],
                    'fix_action' => $patchedVersion ? [
                        'type' => 'composer_update',
                        'params' => ['package' => $package, 'version' => $patchedVersion],
                    ] : null,
                ];
            }
        }

        return $findings;
    }

    /**
     * composer audit doesn't hand back a clean "upgrade to" version — the
     * closest safe signal is the advisory's own affected-versions range,
     * which is deliberately treated as "needs a human-picked version" (no
     * fix_action) unless a caret constraint gives us something usable.
     */
    protected function extractPatchedVersion(string $affectedVersions): ?string
    {
        return null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function checkDebugModeInProduction(): array
    {
        if (config('app.env') !== 'production' || ! config('app.debug')) {
            return [];
        }

        return [[
            'category' => 'config',
            'title' => 'Debug mode is enabled in production',
            'severity' => 'critical',
            'evidence' => ['APP_ENV' => config('app.env'), 'APP_DEBUG' => true],
            'fix_action' => ['type' => 'env_set', 'params' => ['key' => 'APP_DEBUG', 'value' => 'false']],
        ]];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function checkSecureCookiesInProduction(): array
    {
        if (config('app.env') !== 'production' || config('session.secure_cookie')) {
            return [];
        }

        return [[
            'category' => 'config',
            'title' => 'Session cookies are not marked secure in production',
            'severity' => 'high',
            'evidence' => ['APP_ENV' => config('app.env'), 'SESSION_SECURE_COOKIE' => config('session.secure_cookie')],
            'fix_action' => ['type' => 'env_set', 'params' => ['key' => 'SESSION_SECURE_COOKIE', 'value' => 'true']],
        ]];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function checkAdminsMissingTwoFactor(): array
    {
        $missing = User::role(['admin', 'super-admin'])
            ->whereNull('two_factor_secret')
            ->pluck('email');

        if ($missing->isEmpty()) {
            return [];
        }

        return [[
            'category' => 'auth',
            'title' => 'Admin account(s) without two-factor authentication set up',
            'severity' => 'high',
            'evidence' => ['emails' => $missing->all()],
            // Not code-fixable — the account holder has to set up 2FA
            // themselves, so there's nothing for the executor to run
            // automatically.
            'fix_action' => null,
        ]];
    }

    /**
     * Every first-party model with a credential-shaped column (api_key,
     * secret, password, token, ...) that isn't protected — by an
     * `encrypted`/`hashed` cast, a verified framework mechanism, or a
     * verified one-way hash elsewhere in the app. Deliberately does NOT
     * trust naming conventions alone: every exemption below is backed by
     * either a real Eloquent cast (asked of the model the same way the
     * framework itself resolves it, so it also sees a cast a trait adds via
     * mergeCasts() during construction — e.g. Filament's
     * InteractsWithAppAuthentication on User) or textual evidence
     * (columnIsHashedNotEncrypted()) that the column is actually a digest.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function checkUnencryptedSecretColumns(): array
    {
        $findings = [];

        foreach ($this->firstPartyModelClasses() as $modelClass) {
            $model = new $modelClass;
            $table = $model->getTable();

            if (! Schema::hasTable($table)) {
                continue;
            }

            $casts = $model->getCasts();
            $usesFortifyTwoFactor = in_array(TwoFactorAuthenticatable::class, class_uses_recursive($modelClass), true);

            foreach (Schema::getColumnListing($table) as $column) {
                if (! preg_match(self::SECRET_NAME_PATTERN, $column)) {
                    continue;
                }

                // Laravel's own naming conventions for metadata *about* a
                // secret, not the secret itself — a timestamp (..._at, e.g.
                // set_password_expires_at, two_factor_confirmed_at) or a
                // foreign/public identifier (..._id, e.g. an OAuth client_id,
                // which is meant to be public unlike its paired
                // client_secret) never holds credential material regardless
                // of a "password"/"secret"/etc. substring landing in its name.
                if (str_ends_with($column, '_at') || str_ends_with($column, '_id')) {
                    continue;
                }

                if (in_array($column, self::FRAMEWORK_COLUMN_NAMES, true)) {
                    continue;
                }

                if (in_array("{$table}.{$column}", self::KNOWN_SAFE_COLUMNS, true)) {
                    continue;
                }

                // Fortify's TwoFactorAuthenticatable trait encrypts/decrypts
                // these two columns itself via Fortify::currentEncrypter()
                // inside its own accessor methods (verified in
                // vendor/laravel/fortify/src/TwoFactorAuthenticatable.php),
                // bypassing Eloquent's cast system entirely — no cast here
                // is correct, not a gap.
                if ($usesFortifyTwoFactor && in_array($column, ['two_factor_secret', 'two_factor_recovery_codes'], true)) {
                    continue;
                }

                if (in_array($casts[$column] ?? null, self::PROTECTED_CASTS, true)) {
                    continue;
                }

                if ($this->columnIsHashedNotEncrypted($column)) {
                    continue;
                }

                $findings[] = [
                    'category' => 'credential_storage',
                    'title' => "Column {$table}.{$column} looks like a credential but isn't encrypted at rest",
                    'severity' => 'critical',
                    'evidence' => [
                        'model' => $modelClass,
                        'table' => $table,
                        'column' => $column,
                        'cast' => $casts[$column] ?? 'none',
                    ],
                    // Choosing encrypted vs encrypted:array/json and safely
                    // migrating any existing plaintext rows is a judgment
                    // call for a human, not something to run unattended.
                    'fix_action' => null,
                ];
            }
        }

        return $findings;
    }

    /**
     * @return array<int, class-string<Model>>
     */
    protected function firstPartyModelClasses(): array
    {
        return collect(File::allFiles(app_path('Models')))
            ->map(fn ($file) => 'App\\Models\\'.Str::replace('/', '\\', Str::before($file->getRelativePathname(), '.php')))
            ->filter(fn (string $class) => class_exists($class) && is_subclass_of($class, Model::class))
            ->values()
            ->all();
    }

    /**
     * Real, textual evidence that $column is populated via a one-way digest
     * (hash()/Hash::make()) somewhere in the app, rather than trusting a
     * naming convention alone. Covers both ApiToken::token_hash
     * ('token_hash' => hash('sha256', $plainText), in the model itself) and
     * AffiliateApplication::set_password_token ('set_password_token' =>
     * hash('sha256', $token), in its service class instead) — a hash can't
     * be decrypted back regardless of which class computes it, so there's
     * no plaintext for an encrypted cast to protect.
     */
    protected function columnIsHashedNotEncrypted(string $column): bool
    {
        $quoted = preg_quote($column, '/');

        foreach (File::allFiles(app_path()) as $file) {
            if (preg_match("/['\"]{$quoted}['\"]\\s*=>\\s*(hash\\(|Hash::make\\()/", $file->getContents())) {
                return true;
            }
        }

        return false;
    }

    /**
     * Flags a credential-shaped env()/config() value read directly inside
     * the argument expression of a call to the AI provider — the exact
     * shape of mistake that would hand an API key, password, or other
     * secret to a third-party AI provider inside a prompt, where a prompt
     * log, a provider incident, or a jailbreak that echoes back its own
     * system prompt could leak it publicly.
     *
     * Scoped deliberately tight to keep this precise rather than merely
     * broad: it only looks *inside* the balanced parens of a real
     * generateText()/generateJson()/generateImage() call, not "anywhere in
     * a file that happens to also mention the AI provider" (an earlier,
     * file-wide version of this check false-positived on
     * GoogleMapsLeadService, which reads GOOGLE_PLACES_API_KEY to call
     * Google's own API and doesn't touch the AI provider at all — the class
     * name only appeared in a docblock).
     *
     * Known limitation, stated rather than silently assumed away: this
     * cannot trace a secret smuggled in through an intermediate variable
     * (`$leaked = env('...'); $this->ai->generateText($leaked);`) — that
     * needs real data-flow analysis, which is out of scope for a check that
     * has to run unattended and explain itself in plain evidence. It
     * reliably catches the direct form, which is also the only form found
     * anywhere in this codebase as of when this check was written.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function checkAiPromptCredentialInterpolation(): array
    {
        $findings = [];

        foreach (File::allFiles(app_path()) as $file) {
            $offending = $this->findCredentialLeaksInSource($file->getContents());

            if ($offending === []) {
                continue;
            }

            $findings[] = [
                'category' => 'ai_agent_credential_leak',
                'title' => "{$file->getRelativePathname()} passes a credential-shaped env()/config() value directly into an AI prompt argument",
                'severity' => 'critical',
                'evidence' => ['file' => $file->getRelativePathname(), 'matches' => $offending],
                // Which prompt still needs the value (if any) and how to
                // remove it is a judgment call, not something to run
                // unattended.
                'fix_action' => null,
            ];
        }

        return $findings;
    }

    /**
     * Pure string-in, array-out core of checkAiPromptCredentialInterpolation()
     * — split out so it can be exercised directly against inline PHP-source
     * fixtures in tests, rather than only against whatever happens to be on
     * disk in app/ right now.
     *
     * @return array<int, array{call: string, expression: string}>
     */
    protected function findCredentialLeaksInSource(string $contents): array
    {
        preg_match_all(self::AI_CALL_PATTERN, $contents, $calls, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

        $offending = [];

        foreach ($calls as $call) {
            [$fullMatch, $offset] = $call[0];
            $openParenPos = $offset + strlen($fullMatch) - 1;
            $arguments = $this->extractBalancedParens($contents, $openParenPos);

            if ($arguments === null) {
                continue;
            }

            if (! preg_match_all('/\b(env|config)\s*\(\s*[\'"]([^\'"]*)[\'"]/', $arguments, $envCalls, PREG_SET_ORDER)) {
                continue;
            }

            foreach ($envCalls as $envCall) {
                if (preg_match(self::SECRET_NAME_PATTERN, $envCall[2])) {
                    $offending[] = [
                        'call' => "\${$call[1][0]}->{$call[2][0]}(...)",
                        'expression' => trim($envCall[0]),
                    ];
                }
            }
        }

        return $offending;
    }

    /**
     * Given the position of an opening "(" in $contents, returns everything
     * up to (but not including) its matching ")" — respecting nested parens
     * so a multi-line, multi-argument call is captured whole. Returns null
     * if the parens never balance (malformed/unexpected input — fail closed
     * to "no evidence" rather than guess).
     *
     * Tracks single- and double-quoted string literals (including
     * backslash-escapes) and ignores parens found inside them — a system
     * prompt string containing its own literal "(2-3 sentences)" is a
     * completely ordinary thing to write, and without this a stray ")"
     * inside a string argument would end the scan early and silently
     * truncate the captured arguments.
     */
    protected function extractBalancedParens(string $contents, int $openParenPos): ?string
    {
        $depth = 0;
        $length = strlen($contents);
        $inString = null;

        for ($i = $openParenPos; $i < $length; $i++) {
            $char = $contents[$i];

            if ($inString !== null) {
                if ($char === '\\') {
                    $i++; // skip the escaped character entirely, whatever it is

                    continue;
                }

                if ($char === $inString) {
                    $inString = null;
                }

                continue;
            }

            if ($char === '\'' || $char === '"') {
                $inString = $char;

                continue;
            }

            if ($char === '(') {
                $depth++;
            } elseif ($char === ')') {
                $depth--;

                if ($depth === 0) {
                    return substr($contents, $openParenPos + 1, $i - $openParenPos - 1);
                }
            }
        }

        return null;
    }
}
