<?php

namespace Tests\Unit;

use App\Models\Translation;
use App\Support\Translation\HtmlTranslator;
use App\Support\Translation\Mark;
use App\Support\Translation\TranslationStore;
use PHPUnit\Framework\TestCase;

/**
 * How a page is cut into pieces of text, what Claude is sent for each,
 * and how translations are put back — without a database.
 */
class HtmlTranslatorTest extends TestCase
{
    protected function tearDown(): void
    {
        Mark::$protect = false;
        Mark::$trustHtml = false;

        parent::tearDown();
    }

    /**
     * A store that knows the given translations (English template =>
     * translation) and records every template it was asked for.
     *
     * @param  array<string, string>  $known
     */
    protected function store(array $known = []): TranslationStore
    {
        return new class($known) extends TranslationStore
        {
            /** @var array<int, string> */
            public array $asked = [];

            /**
             * @param  array<string, string>  $known
             */
            public function __construct(protected array $known) {}

            public function lookup(string $locale, array $sources, ?string $context = null): array
            {
                $found = [];

                foreach ($sources as $hash => $source) {
                    $this->asked[] = $source;

                    if (isset($this->known[$source])) {
                        $found[$hash] = $this->known[$source];
                    }
                }

                return $found;
            }
        };
    }

    /**
     * @param  array<string, string>  $known
     * @return array{0: string, 1: array<int, string>}
     */
    protected function fragment(string $html, array $known = []): array
    {
        $store = $this->store($known);
        $output = (new HtmlTranslator($store))->translateFragment($html, 'fr');

        return [$output, $store->asked];
    }

    public function test_inline_markup_becomes_numbered_placeholders(): void
    {
        [$output, $asked] = $this->fragment(
            '<p>Read our <a href="/pricing" class="link">pricing</a> <strong>today</strong>.</p>',
            ['Read our <g1>pricing</g1> <g2>today</g2>.' => 'Découvrez nos <g1>tarifs</g1> <g2>aujourd’hui</g2>.'],
        );

        $this->assertSame(['Read our <g1>pricing</g1> <g2>today</g2>.'], $asked);
        $this->assertSame('<p>Découvrez nos <a href="/pricing" class="link">tarifs</a> <strong>aujourd’hui</strong>.</p>', $output);
    }

    public function test_marked_customer_values_are_never_sent_and_stay_as_they_are(): void
    {
        $html = '<p>Hello '.Mark::VALUE_OPEN.'Ada &amp; Co'.Mark::VALUE_CLOSE.', welcome back!</p>';

        [$output, $asked] = $this->fragment($html, ['Hello <v1/>, welcome back!' => 'Bon retour, <v1/> !']);

        $this->assertSame(['Hello <v1/>, welcome back!'], $asked);
        $this->assertSame('<p>Bon retour, Ada &amp; Co !</p>', $output);
    }

    public function test_a_value_on_its_own_is_not_translated_at_all(): void
    {
        [$output, $asked] = $this->fragment('<td>'.Mark::VALUE_OPEN.'Zebra Gizmo'.Mark::VALUE_CLOSE.'</td>');

        $this->assertSame([], $asked);
        $this->assertSame('<td>Zebra Gizmo</td>', $output);
    }

    public function test_customer_html_regions_are_left_untouched(): void
    {
        $html = '<div><h2>Your article</h2>'.Mark::RAW_OPEN.'<p>My own words about boots.</p>'.Mark::RAW_CLOSE.'</div>';

        [$output, $asked] = $this->fragment($html, ['Your article' => 'Votre article']);

        $this->assertSame(['Your article'], $asked);
        $this->assertSame('<div><h2>Votre article</h2><p>My own words about boots.</p></div>', $output);
    }

    public function test_raw_values_inside_an_attribute_or_script_are_just_unmarked(): void
    {
        $prepared = HtmlTranslator::prepare('<a href="'.Mark::RAW_OPEN.'/x?a=1'.Mark::RAW_CLOSE.'">Go</a><script>var a = "'.Mark::RAW_OPEN.'<b>'.Mark::RAW_CLOSE.'";</script>');

        $this->assertSame('<a href="/x?a=1">Go</a><script>var a = "<b>";</script>', $prepared);
    }

    public function test_icons_at_the_edges_are_left_out_and_kept(): void
    {
        [$output, $asked] = $this->fragment(
            '<button><svg viewBox="0 0 1 1"><path d="M0"></path></svg> Save changes</button>',
            ['Save changes' => 'Enregistrer'],
        );

        $this->assertSame(['Save changes'], $asked);
        $this->assertStringContainsString('<svg viewBox="0 0 1 1"><path d="M0"></path></svg>', $output);
        $this->assertStringContainsString('Enregistrer', $output);
        $this->assertStringNotContainsString('Save changes', $output);
    }

    public function test_a_row_of_buttons_is_one_piece_per_button(): void
    {
        [, $asked] = $this->fragment('<div><a href="/login">Login</a> <a href="/start">Get started</a></div>');

        $this->assertSame(['Login', 'Get started'], $asked);
    }

    public function test_placeholders_and_untranslatable_parts_are_skipped(): void
    {
        [, $asked] = $this->fragment(
            '<form><input type="email" placeholder="Your email"><input type="submit" value="Join"></form>'
            .'<pre>code here</pre><p translate="no">AffilStack</p><p class="notranslate">Brand</p><p>12 345</p>'
        );

        $this->assertSame(['Your email', 'Join'], $asked);
    }

    public function test_a_translation_that_loses_a_placeholder_is_not_used(): void
    {
        [$output] = $this->fragment(
            '<p>Read our <a href="/pricing">pricing</a></p>',
            ['Read our <g1>pricing</g1>' => 'Découvrez nos tarifs'],
        );

        $this->assertSame('<p>Read our <a href="/pricing">pricing</a></p>', $output);
    }

    public function test_a_translation_cannot_inject_markup(): void
    {
        [$output] = $this->fragment('<p>Hello</p>', ['Hello' => '<g1>Salut</g1>']);

        $this->assertSame('<p>Hello</p>', $output);
    }

    public function test_markup_in_a_translation_is_shown_as_text(): void
    {
        [$output] = $this->fragment(
            '<p>Read our <a href="/pricing">pricing</a></p>',
            ['Read our <g1>pricing</g1>' => 'Lisez <img src=x onerror=alert(1)> nos <g1>tarifs</g1> <script>x</script>'],
        );

        $this->assertStringNotContainsString('<img', $output);
        $this->assertStringNotContainsString('<script', $output);
        $this->assertStringContainsString('&lt;img src=x onerror=alert(1)&gt;', $output);
        $this->assertStringContainsString('<a href="/pricing">tarifs</a>', $output);
    }

    public function test_whole_documents_get_their_language_and_head_extras(): void
    {
        $store = $this->store(['Welcome' => 'Bienvenue', 'A site for affiliates' => 'Un site pour affiliés']);

        $output = (new HtmlTranslator($store))->translateDocument(
            '<!DOCTYPE html><html lang="en"><head><meta name="description" content="A site for affiliates"><title>Welcome</title></head><body><h1>Welcome</h1><a href="/about">x</a></body></html>',
            'ar',
            ['head' => '<link rel="alternate" hreflang="fr" href="/fr">', 'rtl' => true, 'rewriteUrl' => fn (string $url) => '/ar'.$url],
        );

        $this->assertStringContainsString('<html lang="ar" dir="rtl">', $output);
        $this->assertStringContainsString('<title>Bienvenue</title>', $output);
        $this->assertStringContainsString('<h1>Bienvenue</h1>', $output);
        $this->assertStringContainsString('content="Un site pour affiliés"', $output);
        $this->assertStringContainsString('<a href="/ar/about">', $output);
        $this->assertStringContainsString('<link rel="alternate" hreflang="fr" href="/fr"></head>', $output);
    }

    public function test_markers_never_survive(): void
    {
        $this->assertSame('Ada {"a":"b"}', Mark::strip(Mark::VALUE_OPEN.'Ada'.Mark::VALUE_CLOSE.' {"a":"b"}'));
        $this->assertFalse(Mark::contains(Mark::strip(implode('', Mark::ALL))));

        [$output] = $this->fragment('<p data-x="'.Mark::VALUE_OPEN.'1'.Mark::VALUE_CLOSE.'">'.Mark::RAW_OPEN.'<b>x</b>'.Mark::RAW_CLOSE.'</p>');
        $this->assertDoesNotMatchRegularExpression('/[\x{E000}-\x{E003}]/u', $output);
        $this->assertStringNotContainsString('afs-raw', $output);
    }

    public function test_placeholder_matching_ignores_order_but_not_count(): void
    {
        $this->assertTrue(HtmlTranslator::placeholdersMatch('A <g1>b</g1> <v1/>', '<v1/> a <g1>b</g1>'));
        $this->assertFalse(HtmlTranslator::placeholdersMatch('A <v1/>', 'A <v1/> <v1/>'));
        $this->assertFalse(HtmlTranslator::placeholdersMatch('A <x1/>', 'A <x2/>'));
    }

    public function test_hashes_are_of_the_normalized_template(): void
    {
        $this->assertSame(Translation::hashFor('Read our <g1>pricing</g1>'), sha1('Read our <g1>pricing</g1>'));
        $this->assertSame('Read our <g1>pricing</g1>', HtmlTranslator::normalize("  Read  our\n <g1>pricing</g1> "));
    }
}
