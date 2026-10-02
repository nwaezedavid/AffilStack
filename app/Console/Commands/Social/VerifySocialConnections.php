<?php

namespace App\Console\Commands\Social;

use App\Models\SocialConnection;
use App\Services\Social\SocialConnectionVerifier;
use App\Services\Social\SocialProviderRegistry;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('social-connections:verify {--provider= : Only check connections for this provider} {--stale-hours=20 : Skip connections checked more recently than this}')]
#[Description('Re-check every connected social account against its provider and notify owners whose connection broke')]
class VerifySocialConnections extends Command
{
    public function handle(SocialConnectionVerifier $verifier, SocialProviderRegistry $registry): int
    {
        // Providers an admin has switched off can't refresh tokens, so their
        // connections are left untouched until they're back on.
        $providers = collect($registry->all())
            ->filter(fn ($provider) => $provider->isAvailable())
            ->keys()
            ->when($this->option('provider'), fn ($keys) => $keys->intersect([$this->option('provider')]));

        $staleBefore = now()->subHours(max(0, (int) $this->option('stale-hours')));
        $checked = 0;
        $broken = 0;

        SocialConnection::query()
            ->whereIn('provider', $providers->all())
            ->where(fn ($query) => $query->whereNull('last_verified_at')->orWhere('last_verified_at', '<=', $staleBefore))
            ->with('user')
            ->chunkById(100, function ($connections) use ($verifier, &$checked, &$broken) {
                foreach ($connections as $connection) {
                    $check = $verifier->verify($connection);
                    $checked++;
                    $broken += $check->isVerified() ? 0 : 1;
                }
            });

        $this->info("Checked {$checked} social connection(s); {$broken} need attention.");

        return self::SUCCESS;
    }
}
