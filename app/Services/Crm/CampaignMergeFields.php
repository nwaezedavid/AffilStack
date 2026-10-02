<?php

namespace App\Services\Crm;

use App\Models\CrmContact;
use App\Models\Offer;

/**
 * The merge fields a campaign subject/body can use, and how each one is
 * filled for a contact. {affiliate_link} becomes the offer's
 * "{{AFFILIATE_LINK}}" placeholder so Offer::cloak() swaps in the cloaked,
 * click-tracked /go/ link (and the affiliate disclosure) at send time.
 */
class CampaignMergeFields
{
    /**
     * @var array<string, string> field => what it inserts (shown in the editor)
     */
    public const FIELDS = [
        'first_name' => 'Contact\'s first name (or "there")',
        'company' => 'Company / business name',
        'city' => 'City from the contact\'s location',
        'website' => 'Contact\'s website',
        'product_name' => 'The product you\'re promoting',
        'affiliate_link' => 'Your cloaked, tracked affiliate link',
        'opening_line' => 'AI-written personal first line (optional)',
    ];

    /**
     * @return array<string, string>
     */
    public function valuesFor(CrmContact $contact, ?Offer $offer, ?string $openingLine = null, ?string $link = null): array
    {
        return [
            'first_name' => $contact->firstName(),
            'company' => $contact->company ?: ($contact->name ?: 'your business'),
            'city' => $contact->city() ?: 'your area',
            'website' => $contact->website ? preg_replace('#^https?://(www\.)?#i', '', rtrim($contact->website, '/')) : 'your website',
            'product_name' => $offer?->product_name ?? 'our recommendation',
            'affiliate_link' => $link ?? ($offer ? Offer::LINK_PLACEHOLDER : ''),
            'opening_line' => (string) $openingLine,
        ];
    }

    /**
     * Fills every {field} in $template. An {opening_line} that ends up empty
     * takes its line with it, so no blank gap is left behind; when the
     * campaign personalises openings but the template never placed the
     * field, the line goes right after the greeting.
     */
    public function render(string $template, CrmContact $contact, ?Offer $offer, ?string $openingLine = null, ?string $link = null): string
    {
        $openingLine = trim((string) $openingLine);

        if ($openingLine !== '' && ! str_contains($template, '{opening_line}')) {
            $lines = explode("\n", $template, 2);
            $template = count($lines) === 2 ? $lines[0]."\n\n{opening_line}\n".$lines[1] : "{opening_line}\n\n".$template;
        }

        if ($openingLine === '') {
            $template = preg_replace('/^[ \t]*\{opening_line\}[ \t]*\r?\n(\r?\n)?/m', '', $template) ?? $template;
        }

        $values = $this->valuesFor($contact, $offer, $openingLine, $link);

        $text = preg_replace_callback('/\{([a-z_]+)\}/', fn (array $m) => array_key_exists($m[1], $values) ? $values[$m[1]] : $m[0], $template) ?? $template;

        return trim(preg_replace("/\n{3,}/", "\n\n", $text) ?? $text);
    }

    /**
     * Merge fields used in $template that this class doesn't know — the
     * editor warns about typos like {firstname}.
     *
     * @return array<int, string>
     */
    public function unknownFields(string $template): array
    {
        preg_match_all('/\{([a-z_]+)\}/i', $template, $m);

        return array_values(array_unique(array_diff($m[1], array_keys(self::FIELDS))));
    }
}
