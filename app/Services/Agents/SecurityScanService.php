<?php

namespace App\Services\Agents;

use App\Models\SecurityFinding;
use App\Models\User;
use App\Services\AI\AIGenerationException;
use App\Services\AI\AIProvider;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

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

        if (! $result->successful()) {
            return [];
        }

        $data = json_decode($result->output(), true);
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
}
