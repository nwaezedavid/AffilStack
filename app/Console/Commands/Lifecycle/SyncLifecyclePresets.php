<?php

namespace App\Console\Commands\Lifecycle;

use App\Support\Lifecycle\LifecycleEmailPresets;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('lifecycle:sync-presets')]
#[Description('Add any missing preset lifecycle emails (birthday, festivals, re-engagement) without touching edited ones')]
class SyncLifecyclePresets extends Command
{
    public function handle(): void
    {
        $created = LifecycleEmailPresets::install();

        $this->info("Added {$created} missing preset lifecycle email(s).");
    }
}
