<?php

namespace App\Console\Commands\Generations;

use App\Models\Generation;
use App\Models\Offer;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Every generation job leaves its row in a terminal state itself — unless
 * the worker process dies underneath it (a deploy restart, a server reboot,
 * a hard timeout kill). Nothing ever revisited those rows, so the customer
 * watched a spinner forever. Credits are only ever spent on success, so
 * marking them failed costs the customer nothing and lets them retry.
 */
#[Signature('generations:fail-stuck {--minutes=30 : How long a generation may sit unfinished}')]
#[Description('Mark generations and offer research that never finished (worker died) as failed')]
class FailStuckGenerations extends Command
{
    public function handle(): int
    {
        $cutoff = now()->subMinutes(max(15, (int) $this->option('minutes')));
        $message = 'This took too long and was stopped — nothing was charged. Please try again.';

        $generations = Generation::query()
            ->whereIn('status', ['queued', 'pending', 'processing'])
            ->where('updated_at', '<', $cutoff)
            ->update(['status' => 'failed', 'error_message' => $message, 'updated_at' => now()]);

        $offers = Offer::query()
            ->whereIn('status', ['queued', 'researching'])
            ->where('updated_at', '<', $cutoff)
            ->update(['status' => 'failed', 'updated_at' => now()]);

        $this->info("Marked {$generations} generation(s) and {$offers} offer(s) as failed.");

        return self::SUCCESS;
    }
}
