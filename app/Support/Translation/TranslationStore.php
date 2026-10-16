<?php

namespace App\Support\Translation;

use App\Jobs\TranslatePendingStrings;
use App\Models\Translation;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Throwable;

/**
 * Saved translations: looks up a page's texts in one query, records the
 * ones not translated yet (so Claude translates them in the background)
 * and notes when each was last seen.
 */
class TranslationStore
{
    /**
     * @param  array<string, string>  $sources  hash => English template
     * @return array<string, string> hash => translation (only usable ones)
     */
    public function lookup(string $locale, array $sources, ?string $context = null): array
    {
        if ($sources === []) {
            return [];
        }

        $found = [];
        $stale = [];
        $known = [];
        $today = now()->toDateString();

        foreach (array_chunk(array_keys($sources), 500) as $chunk) {
            foreach (Translation::query()->where('locale', $locale)->whereIn('hash', $chunk)->get(['id', 'hash', 'translation', 'status', 'last_seen_at']) as $row) {
                $known[$row->hash] = true;

                if ($row->isUsable()) {
                    $found[$row->hash] = (string) $row->translation;
                }

                if ($row->last_seen_at === null || $row->last_seen_at->toDateString() !== $today) {
                    $stale[] = $row->id;
                }
            }
        }

        if ($stale !== []) {
            Translation::query()->whereIn('id', $stale)->update(['last_seen_at' => $today]);
        }

        $missing = array_diff_key($sources, $known);

        if ($missing !== []) {
            $this->queue($locale, $missing, $context);
        }

        return $found;
    }

    /**
     * @param  array<string, string>  $sources  hash => English template
     */
    public function queue(string $locale, array $sources, ?string $context = null): void
    {
        $now = now();
        $rows = [];

        foreach ($sources as $hash => $source) {
            $rows[] = [
                'locale' => $locale,
                'hash' => $hash,
                'source' => $source,
                'status' => 'pending',
                'attempts' => 0,
                'context' => $context !== null ? Str::limit($context, 250, '') : null,
                'last_seen_at' => $now->toDateString(),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($rows, 200) as $chunk) {
            Translation::query()->insertOrIgnore($chunk);
        }

        static::kick($locale);
    }

    /**
     * Starts background translation for a language, at most once every
     * couple of minutes.
     */
    public static function kick(string $locale): void
    {
        try {
            if (Cache::add('translations:kick:'.$locale, true, 120)) {
                TranslatePendingStrings::dispatch($locale);
            }
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * One plain line in another language (the language suggestion bar,
     * email subjects); English until it has been translated.
     */
    public function line(string $text, string $locale, ?string $context = null): string
    {
        if ($locale === Languages::SOURCE) {
            return $text;
        }

        $source = HtmlTranslator::normalize(htmlspecialchars($text, ENT_NOQUOTES, 'UTF-8', false));
        $hash = Translation::hashFor($source);
        $found = $this->lookup($locale, [$hash => $source], $context);

        return isset($found[$hash]) ? html_entity_decode(strip_tags($found[$hash]), ENT_QUOTES | ENT_HTML5, 'UTF-8') : $text;
    }
}
