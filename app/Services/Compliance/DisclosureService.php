<?php

namespace App\Services\Compliance;

/**
 * Picks and inserts the right affiliate-disclosure wording for a piece of
 * generated content, based on the offer's target-audience country and the
 * generation module (which decides whether the wording is a full paragraph
 * placed above the content or a short hashtag-style line appended after
 * it). Also used standalone (hasDisclosure()) to detect whether content
 * already carries disclosure, so Offer::cloak() never stacks a second one
 * on top and the UI can show whether AffilStack had to add one.
 */
class DisclosureService
{
    /**
     * @return array<string, string> country code => display label, for a <select>.
     */
    public function countries(): array
    {
        return collect(config('disclosure.countries'))
            ->map(fn (array $country) => $country['label'])
            ->all();
    }

    public function defaultCountry(): string
    {
        return config('disclosure.default_country', 'US');
    }

    /**
     * True if $text already contains something that reads as an affiliate
     * disclosure — a simple, deliberately generous keyword match (a false
     * positive just skips an insertion; a false negative just adds one
     * redundant line), never a legal judgment.
     */
    public function hasDisclosure(?string $text): bool
    {
        if ($text === null || trim($text) === '') {
            return false;
        }

        $haystack = mb_strtolower($text);

        foreach (config('disclosure.detection_phrases', []) as $phrase) {
            if (str_contains($haystack, mb_strtolower($phrase))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns $text with the correct disclosure wording inserted, unless
     * $text already has one. Unknown country codes and unmapped modules
     * fall back to safe defaults rather than throwing, since this runs on
     * every page render of generated content.
     */
    public function ensure(?string $text, ?string $countryCode, string $module): string
    {
        if ($text === null || trim($text) === '') {
            return $text ?? '';
        }

        if ($this->hasDisclosure($text)) {
            return $text;
        }

        $wording = $this->wordingFor($countryCode, $module);
        $moduleConfig = $this->moduleConfig($module);

        return $moduleConfig['placement'] === 'prepend'
            ? $wording."\n\n".$text
            : $text."\n\n".$wording;
    }

    public function wordingFor(?string $countryCode, string $module): string
    {
        $country = config('disclosure.countries.'.strtoupper((string) $countryCode))
            ?? config('disclosure.countries.'.$this->defaultCountry());

        $format = $this->moduleConfig($module)['format'];

        return $country[$format] ?? $country['short'];
    }

    /**
     * @return array{format: string, placement: string}
     */
    protected function moduleConfig(string $module): array
    {
        return config('disclosure.modules.'.$module) ?? ['format' => 'short', 'placement' => 'append'];
    }
}
