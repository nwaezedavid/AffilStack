<?php

namespace App\Console\Commands\Translation;

use App\Support\Translation\Catalog;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('translations:catalog')]
#[Description('Rebuild the list of AffilStack\'s own interface strings (what can be translated on dashboard pages)')]
class BuildTranslationCatalog extends Command
{
    public function handle(): int
    {
        Catalog::flush();
        $this->info('Catalog rebuilt: '.number_format(Catalog::build()).' strings.');

        return self::SUCCESS;
    }
}
