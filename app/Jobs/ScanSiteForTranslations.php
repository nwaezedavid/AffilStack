<?php

namespace App\Jobs;

use App\Support\Translation\TranslationScanner;
use App\Support\Translation\TranslationStore;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * "Scan the website" in Admin → Languages: finds every public page's text
 * in the given languages and starts translating it.
 */
class ScanSiteForTranslations implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 600;

    /**
     * @param  array<int, string>  $locales
     */
    public function __construct(public array $locales) {}

    public function handle(TranslationScanner $scanner): void
    {
        $scanner->scan($this->locales);

        foreach ($this->locales as $locale) {
            TranslationStore::kick($locale);
        }
    }
}
