<?php

namespace App\Console\Commands;

use App\Services\Seo\SiteLinkHealthChecker;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * The active half of the 404 monitor (see SiteLinkHealthChecker) — runs on
 * its own schedule (routes/console.php) rather than waiting for a real
 * visitor to hit a dead link.
 */
#[Signature('seo:check-links')]
#[Description('Proactively scan site content and existing redirects for broken internal links, auto-fixing only unambiguous cases')]
class CheckSiteLinkHealth extends Command
{
    public function handle(SiteLinkHealthChecker $checker): void
    {
        $stats = $checker->run();

        $this->info(sprintf(
            'Link health check: %d link(s) checked, %d broken, %d auto-fixed with a redirect, %d flagged for review in the 404 Monitor.',
            $stats['checked'],
            $stats['broken'],
            $stats['auto_fixed'],
            $stats['flagged'],
        ));
    }
}
