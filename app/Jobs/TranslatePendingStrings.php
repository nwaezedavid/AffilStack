<?php

namespace App\Jobs;

use App\Support\Translation\MachineTranslator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

/**
 * Translates a language's waiting texts with Claude for a few minutes,
 * then queues itself again if more are waiting.
 */
class TranslatePendingStrings implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 300;

    public function __construct(public string $locale) {}

    public function handle(MachineTranslator $translator): void
    {
        $stats = $translator->run($this->locale, 600, microtime(true) + 200);

        Cache::forget('translations:kick:'.$this->locale);

        // Keep going while there's work and the last round made progress.
        if ($stats['remaining'] > 0 && $stats['translated'] > 0 && ! MachineTranslator::paused()) {
            Cache::add('translations:kick:'.$this->locale, true, 120);
            static::dispatch($this->locale)->delay(now()->addSeconds(5));
        }
    }
}
