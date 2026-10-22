<?php

namespace App\Console\Commands\Security;

use App\Support\Security\PhpRuntime;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Run on the server during a deploy: fails (exit code 1) when the PHP
 * running AffilStack is below a critical security fix such as PHP2Shell's,
 * and shows the website's PHP too once a page has been served.
 */
#[Signature('security:check-php')]
#[Description('Check that the PHP running AffilStack has every critical security fix (PHP2Shell and later)')]
class CheckPhpRuntime extends Command
{
    public function handle(): int
    {
        $rows = [['Command line (cron, deploys)', PHP_VERSION]];
        $problems = PhpRuntime::problemsFor(PHP_VERSION);

        if ($web = PhpRuntime::webRuntime()) {
            $rows[] = ['Website', $web['version'].' ('.$web['sapi'].', seen '.date('Y-m-d H:i', (int) $web['recorded_at']).' UTC)'];
            $problems = [...$problems, ...PhpRuntime::problemsFor($web['version']), ...PhpRuntime::riskyIniProblems($web['ini'] ?? [])];
        } else {
            $rows[] = ['Website', 'not seen yet — open any page, then run this again'];
        }

        $this->table(['PHP', 'Version'], $rows);

        $problems = collect($problems)->unique('title')->values();

        if ($problems->isEmpty()) {
            $this->info('PHP is up to date with every known security fix.');

            return self::SUCCESS;
        }

        foreach ($problems as $problem) {
            $line = '['.strtoupper($problem['severity']).'] '.$problem['title'].' — '.$problem['detail'];
            in_array($problem['severity'], ['critical', 'high'], true) ? $this->error($line) : $this->warn($line);
        }

        return $problems->contains('severity', 'critical') ? self::FAILURE : self::SUCCESS;
    }
}
