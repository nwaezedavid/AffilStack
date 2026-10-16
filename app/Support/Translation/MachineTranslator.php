<?php

namespace App\Support\Translation;

use App\Models\SiteSetting;
use App\Models\Translation;
use App\Services\AI\AIProvider;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Translates waiting texts with Claude, in batches, keeping every
 * placeholder. A batch whose answer drops or invents a placeholder is
 * retried a couple of times, then marked failed (shown in English, listed
 * in admin for a person to translate).
 */
class MachineTranslator
{
    public const GLOSSARY_SETTING = 'translation_glossary';

    public const PAUSED_SETTING = 'translation_paused';

    protected const MAX_ITEMS = 40;

    protected const MAX_CHARS = 6000;

    protected const MAX_ATTEMPTS = 3;

    public function __construct(protected AIProvider $ai) {}

    public static function paused(): bool
    {
        return SiteSetting::flag(self::PAUSED_SETTING, false);
    }

    /**
     * Translates up to $limit waiting texts for a language.
     *
     * @return array{translated: int, failed: int, remaining: int}
     */
    public function run(string $locale, int $limit = 400, ?float $deadline = null): array
    {
        $stats = ['translated' => 0, 'failed' => 0, 'remaining' => 0];

        if (! Languages::exists($locale) || static::paused()) {
            return $stats;
        }

        $pending = Translation::query()
            ->where('locale', $locale)
            ->where('status', 'pending')
            ->orderBy('id')
            ->limit($limit)
            ->get();

        foreach ($this->batches($pending) as $batch) {
            if ($deadline !== null && microtime(true) > $deadline) {
                break;
            }

            [$translated, $failed] = $this->translateBatch($locale, $batch);
            $stats['translated'] += $translated;
            $stats['failed'] += $failed;
        }

        $stats['remaining'] = Translation::query()->where('locale', $locale)->where('status', 'pending')->count();

        return $stats;
    }

    /**
     * @param  Collection<int, Translation>  $rows
     * @return array<int, Collection<int, Translation>>
     */
    protected function batches(Collection $rows): array
    {
        $batches = [];
        $current = collect();
        $chars = 0;

        foreach ($rows as $row) {
            $length = mb_strlen($row->source);

            if ($current->isNotEmpty() && ($current->count() >= self::MAX_ITEMS || $chars + $length > self::MAX_CHARS)) {
                $batches[] = $current;
                $current = collect();
                $chars = 0;
            }

            $current->push($row);
            $chars += $length;
        }

        if ($current->isNotEmpty()) {
            $batches[] = $current;
        }

        return $batches;
    }

    /**
     * @param  Collection<int, Translation>  $batch
     * @return array{0: int, 1: int}
     */
    protected function translateBatch(string $locale, Collection $batch): array
    {
        $items = $batch->values()->mapWithKeys(fn (Translation $row, int $index) => ['t'.$index => $row->source])->all();

        try {
            $result = $this->ai->generateJson($this->systemPrompt($locale), json_encode(['items' => $items], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), [
                'max_tokens' => 8000,
                'temperature' => 0.2,
            ]);
        } catch (Throwable $e) {
            report($e);

            // Nothing came back: leave them waiting for the next run.
            $batch->each(fn (Translation $row) => $this->bump($row));

            return [0, $batch->filter(fn (Translation $row) => $row->status === 'failed')->count()];
        }

        $answers = (array) ($result['translations'] ?? $result);
        $translated = 0;
        $failed = 0;

        foreach ($batch->values() as $index => $row) {
            $answer = $answers['t'.$index] ?? null;

            if (is_string($answer) && trim($answer) !== '' && HtmlTranslator::placeholdersMatch($row->source, $answer)) {
                $row->update(['translation' => HtmlTranslator::normalize($answer), 'status' => 'machine', 'attempts' => $row->attempts + 1]);
                $translated++;
            } else {
                $this->bump($row);
                $failed += $row->status === 'failed' ? 1 : 0;
            }
        }

        return [$translated, $failed];
    }

    protected function bump(Translation $row): void
    {
        $attempts = $row->attempts + 1;
        $row->update(['attempts' => $attempts, 'status' => $attempts >= self::MAX_ATTEMPTS ? 'failed' : 'pending']);
    }

    protected function systemPrompt(string $locale): string
    {
        $language = Languages::name($locale);
        $glossary = trim((string) SiteSetting::get(self::GLOSSARY_SETTING, ''));
        $style = match ($locale) {
            'fr' => 'Address the reader as "vous". Use natural, professional French as written by French SaaS companies.',
            'es' => 'Address the reader as "tú". Use neutral Spanish understood across Latin America and Spain.',
            default => 'Use the tone a modern software company would use for this audience.',
        };

        return <<<PROMPT
You translate the interface and website text of AffilStack, a software platform for affiliate marketers, from English into {$language}.

Rules:
- Translate every item's text completely and naturally. Keep it about as short as the English: many items are buttons, menu labels and headings.
- Items contain placeholders: <g1>…</g1> wraps text (a link or bold words), <x1/> is an icon or line break, <v1/> is a value such as a name, number or date. Keep every placeholder exactly, with the same numbers, each one exactly once. You may move them where the grammar of {$language} needs them. Translate the text inside <g…> tags. Never add placeholders.
- Never translate: AffilStack, product and company names, brand names of networks and tools (Impact, PartnerStack, Awin, CJ, ClickBank, Digistore24, WordPress, Paystack, PayPal, Google, LinkedIn, YouTube, TikTok, Pinterest, X, Instagram, Facebook, Claude), URLs, email addresses, code, currency symbols and numbers.
- Keep HTML entities such as &amp; as they are.
- {$style}
PROMPT
            .($glossary !== '' ? "\n- Follow this glossary from the site owner:\n".$glossary : '')
            .<<<'PROMPT'

Return JSON only: {"translations": {"t0": "…", "t1": "…"}} with one entry per item id.
PROMPT;
    }
}
