<?php

namespace App\Console\Commands\Translation;

use App\Support\Translation\Languages;
use App\Support\Translation\MachineTranslator;
use App\Support\Translation\TranslationScanner;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('translations:scan {locale?* : Language codes (default: every language)} {--translate : Translate everything found right away}')]
#[Description('Visit every public page in each language to find text to translate, optionally translating it now')]
class ScanTranslations extends Command
{
    public function handle(TranslationScanner $scanner, MachineTranslator $translator): int
    {
        $locales = $this->argument('locale') ?: array_keys(Languages::all());

        if ($locales === []) {
            $this->warn('No languages yet. Add one under Admin → Content → Languages.');

            return self::SUCCESS;
        }

        $this->info('Visited '.$scanner->scan($locales).' pages.');

        if ($this->option('translate')) {
            foreach ($locales as $locale) {
                do {
                    $stats = $translator->run($locale, 400);
                    $this->line("{$locale}: {$stats['translated']} translated, {$stats['failed']} failed, {$stats['remaining']} waiting.");
                } while ($stats['remaining'] > 0 && $stats['translated'] > 0);
            }
        }

        return self::SUCCESS;
    }
}
