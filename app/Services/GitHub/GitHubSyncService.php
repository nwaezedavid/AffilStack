<?php

namespace App\Services\GitHub;

use App\Models\GitHubSyncRun;
use App\Models\GitHubSyncSetting;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Process\ProcessResult;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Throwable;

/**
 * The owner's ask, in their own words: "Create a built-in feature... that
 * can automatically connect to GitHub. If there's any future updates, it
 * should be made automatically within any manual uploads" — interpreted as:
 * a super-admin connects this platform's own GitHub repo with a Personal
 * Access Token (there's no GitHub App infra here), and the platform pushes
 * its own code changes to that repo, both on a schedule and on demand. See
 * App\Filament\Pages\GitHubSyncSettings and the daily `github:sync` command.
 *
 * Two responsibilities, kept in one class because the owner described them
 * as one feature and they share nothing but the settings row:
 *
 *  - verifyConnection(): a lightweight authenticated GitHub API call (GET
 *    the repo) — never touches git or this app's working copy at all.
 *  - sync(): the real thing, run via Laravel's Process facade against
 *    this application's OWN working copy (workingCopyPath(), base_path()
 *    in production). The personal access token is NEVER written to disk —
 *    not to .git/config, not via `git remote set-url` with an embedded
 *    token. It's passed as a one-off `-c http.<url>.extraHeader=...` flag
 *    scoped to the single `git push` invocation that needs it (the same
 *    mechanism GitHub's own actions/checkout uses) — everything else (add,
 *    commit, status, diff, rev-parse) needs no credential at all. Proven in
 *    GitHubSyncServiceTest that .git/config never contains the raw token
 *    after a sync.
 *
 * Before every sync, a defense-in-depth guard re-parses `git status
 * --porcelain` (pre-stage) and `git diff --cached --stat` (post-stage, the
 * exact set about to be committed) for a `.env`/`.env.*` path, refusing to
 * commit or push at all if one shows up — deliberately not just trusting
 * .gitignore, which in this repo only lists the three exact names
 * `.env`, `.env.backup`, `.env.production` rather than a `.env*` wildcard,
 * and would say nothing at all about a file that was ever force-added and
 * is now tracked (a gitignore entry never hides changes to an already-
 * tracked file).
 */
class GitHubSyncService
{
    protected int $timeout = 60;

    /**
     * @return array{success: bool, message: string}
     */
    public function verifyConnection(GitHubSyncSetting $settings): array
    {
        $token = (string) $settings->credential('personal_access_token');
        $owner = trim((string) $settings->repo_owner);
        $repo = trim((string) $settings->repo_name);

        if ($token === '' || $owner === '' || $repo === '') {
            return ['success' => false, 'message' => 'A personal access token and the repository owner/name are all required.'];
        }

        try {
            $response = $this->apiClient($token)->get("https://api.github.com/repos/{$owner}/{$repo}");
        } catch (Throwable $e) {
            return ['success' => false, 'message' => 'Could not reach GitHub: '.$this->redact($e->getMessage(), $token)];
        }

        if ($response->status() === 401) {
            return ['success' => false, 'message' => 'GitHub rejected this token (401 Unauthorized) — it may be invalid, expired, or revoked.'];
        }

        if ($response->status() === 404) {
            return ['success' => false, 'message' => "GitHub returned 404 Not Found for {$owner}/{$repo} — check the owner/repository name, or this token may not have access to it."];
        }

        if (! $response->successful()) {
            return ['success' => false, 'message' => 'GitHub returned an unexpected response: HTTP '.$response->status()];
        }

        if (! (bool) data_get($response->json(), 'permissions.push', false)) {
            return ['success' => false, 'message' => "Connected to {$owner}/{$repo}, but this token does not have push access to it — it needs the \"repo\" scope (classic token) or \"Contents: Read and write\" (fine-grained token)."];
        }

        $defaultBranch = (string) data_get($response->json(), 'default_branch', 'main');

        return ['success' => true, 'message' => "Connected to {$owner}/{$repo} (default branch: {$defaultBranch}) with push access."];
    }

    /**
     * Runs one real sync attempt against workingCopyPath() and records it
     * (both on $settings' own last_sync_* columns and as its own
     * GitHubSyncRun history row) no matter how it ends — refused,
     * committed nothing, or pushed a new commit.
     */
    public function sync(GitHubSyncSetting $settings, string $trigger = GitHubSyncRun::TRIGGER_MANUAL): GitHubSyncRun
    {
        $token = (string) $settings->credential('personal_access_token');
        $branch = $settings->branch ?: 'main';

        if (! $settings->hasCredentials()) {
            return $this->recordRun($settings, GitHubSyncRun::STATUS_FAILED, 'Sync skipped: connect a personal access token and the repository owner/name first.', null, $trigger);
        }

        $repoPath = $this->workingCopyPath();

        if (! is_dir($repoPath.'/.git')) {
            return $this->recordRun($settings, GitHubSyncRun::STATUS_FAILED, "Sync skipped: no git repository found at {$repoPath}.", null, $trigger);
        }

        // Guard #1 — what does the working tree show right now, before
        // anything is staged? Catches an already-tracked secret file's
        // modifications and any untracked secret file sitting there.
        $secretPaths = $this->findSecretPaths($this->extractStatusPaths(
            $this->runGit($repoPath, ['status', '--porcelain'])->output()
        ));

        if ($secretPaths !== []) {
            return $this->blockSync($settings, $secretPaths, $trigger);
        }

        $this->runGit($repoPath, ['add', '-A']);

        // Guard #2 — re-check exactly what just got staged, immediately
        // before committing it. Belt-and-suspenders with guard #1 rather
        // than trusting that nothing changed between the two commands.
        $diffStat = $this->runGit($repoPath, ['diff', '--cached', '--stat']);
        $secretPaths = $this->findSecretPaths($this->extractDiffStatPaths($diffStat->output()));

        if ($secretPaths !== []) {
            $this->runGit($repoPath, ['reset']);

            return $this->blockSync($settings, $secretPaths, $trigger);
        }

        $hasStagedChanges = trim($diffStat->output()) !== '';

        if ($hasStagedChanges) {
            $commit = $this->runGit($repoPath, [
                '-c', 'user.email=github-sync@affilistack.local',
                '-c', 'user.name=AffiliStack GitHub Sync',
                'commit', '-m', "Automated sync from AffiliStack admin dashboard ({$trigger}, ".now()->toDateTimeString().')',
            ]);

            if (! $commit->successful()) {
                return $this->recordRun($settings, GitHubSyncRun::STATUS_FAILED, 'git commit failed: '.$this->redact($commit->errorOutput() ?: $commit->output(), $token), null, $trigger);
            }
        }

        $remoteUrl = $this->remoteUrl($settings);
        $header = 'AUTHORIZATION: basic '.base64_encode('x-access-token:'.$token);

        // The token exists only as this single process's argv for the
        // life of this one `git push` — never as `-c` (persisted nowhere)
        // scoped to that literal remote URL via git's documented
        // `http.<url>.extraHeader` config, and never via `git remote
        // set-url`/an embedded-token URL, which WOULD persist to
        // .git/config. See GitHubSyncServiceTest for the assertion that
        // .git/config never contains the raw token after this call.
        $push = Process::path($repoPath)
            ->timeout($this->timeout)
            ->env(['GIT_TERMINAL_PROMPT' => '0'])
            ->run([
                'git', '-c', "http.{$remoteUrl}.extraHeader={$header}",
                'push', $remoteUrl, "HEAD:refs/heads/{$branch}",
            ]);

        if (! $push->successful()) {
            return $this->recordRun($settings, GitHubSyncRun::STATUS_FAILED, 'git push failed: '.$this->redact($push->errorOutput() ?: $push->output(), $token), null, $trigger);
        }

        $sha = trim($this->runGit($repoPath, ['rev-parse', 'HEAD'])->output()) ?: null;

        $message = $hasStagedChanges ? 'Synced local changes to GitHub.' : 'Nothing new to commit — pushed up to date.';

        return $this->recordRun($settings, GitHubSyncRun::STATUS_SUCCESS, $message, $sha, $trigger);
    }

    /**
     * The application's own working copy to sync — base_path() in
     * production. Overridden in tests (a Mockery partial mock, the same
     * pattern as SecurityFixExecutor::envPath()) to point at a throwaway
     * repo instead, so no test ever touches this app's real .git.
     */
    protected function workingCopyPath(): string
    {
        return base_path();
    }

    /**
     * The URL passed directly to `git push` — never written to any
     * persisted remote. Overridden in tests to point at a throwaway local
     * `file://` bare repo instead of a real github.com URL.
     */
    protected function remoteUrl(GitHubSyncSetting $settings): string
    {
        return "https://github.com/{$settings->repo_owner}/{$settings->repo_name}.git";
    }

    protected function runGit(string $repoPath, array $args): ProcessResult
    {
        return Process::path($repoPath)
            ->timeout($this->timeout)
            ->env(['GIT_TERMINAL_PROMPT' => '0'])
            ->run(array_merge(['git'], $args));
    }

    protected function apiClient(string $token): PendingRequest
    {
        return Http::withToken($token)
            ->withHeaders([
                'Accept' => 'application/vnd.github+json',
                'X-GitHub-Api-Version' => '2022-11-28',
                'User-Agent' => 'AffiliStack-GitHub-Sync',
            ])
            ->timeout(15);
    }

    protected function blockSync(GitHubSyncSetting $settings, array $secretPaths, string $trigger): GitHubSyncRun
    {
        Log::alert('GitHubSyncService: refused to sync — secret-bearing path(s) detected in pending changes.', [
            'repo' => $settings->repoSlug(),
            'paths' => $secretPaths,
        ]);

        return $this->recordRun(
            $settings,
            GitHubSyncRun::STATUS_BLOCKED,
            'Refused to sync: possible secret file(s) in the pending changes: '.implode(', ', $secretPaths).'. Nothing was committed or pushed.',
            null,
            $trigger,
        );
    }

    protected function recordRun(GitHubSyncSetting $settings, string $status, string $message, ?string $commitSha, string $trigger): GitHubSyncRun
    {
        $settings->update([
            'last_sync_status' => $status,
            'last_sync_at' => now(),
            'last_sync_commit_sha' => $commitSha,
            'last_sync_message' => $message,
        ]);

        return GitHubSyncRun::create([
            'status' => $status,
            'trigger' => $trigger,
            'message' => $message,
            'commit_sha' => $commitSha,
        ]);
    }

    /**
     * @param  list<string>  $paths
     * @return list<string>
     */
    protected function findSecretPaths(array $paths): array
    {
        return array_values(array_unique(array_filter(
            $paths,
            fn (string $path) => (bool) preg_match('/^\.env(\..+)?$/i', basename(trim($path, " \t\n\r\0\x0B\"")))
        )));
    }

    /**
     * Parses `git status --porcelain` output ("XY path" or, for a rename/
     * copy, "XY orig -> path") into a flat list of every path mentioned.
     *
     * @return list<string>
     */
    protected function extractStatusPaths(string $output): array
    {
        $paths = [];

        foreach (preg_split('/\R/', trim($output)) as $line) {
            if ($line === '') {
                continue;
            }

            $rest = substr($line, 3);

            if ($rest === false || $rest === '') {
                continue;
            }

            if (str_contains($rest, ' -> ')) {
                [$from, $to] = explode(' -> ', $rest, 2);
                $paths[] = $from;
                $paths[] = $to;
            } else {
                $paths[] = $rest;
            }
        }

        return $paths;
    }

    /**
     * Parses `git diff --stat` output (" path/to/file | 3 +++", plus a
     * trailing "N files changed..." summary line this deliberately
     * ignores) into a flat list of every path about to be committed.
     *
     * @return list<string>
     */
    protected function extractDiffStatPaths(string $output): array
    {
        $paths = [];

        foreach (preg_split('/\R/', trim($output)) as $line) {
            if (! str_contains($line, '|')) {
                continue;
            }

            [$path] = explode('|', $line, 2);
            $paths[] = trim($path);
        }

        return $paths;
    }

    /**
     * Defense-in-depth for verifyConnection()'s exception branch — the PAT
     * should never appear in an HTTP client exception message, but this
     * makes sure of it before the message is ever returned, notified, or
     * logged.
     */
    protected function redact(string $text, string $token): string
    {
        return $token === '' ? $text : str_replace($token, '[REDACTED]', $text);
    }
}
