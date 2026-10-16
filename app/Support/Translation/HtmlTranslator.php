<?php

namespace App\Support\Translation;

use App\Models\Translation;
use Dom\Comment;
use Dom\Element;
use Dom\HTMLDocument;
use Dom\Node;
use Dom\Text;
use RuntimeException;
use Throwable;

/**
 * Translates a rendered page (or a piece of one) into another language.
 *
 * The page is split into pieces of text the way a person would read them:
 * a paragraph, a heading, a button, a table cell — each with its inline
 * markup (links, bold text, icons) replaced by numbered placeholders, so
 * "Read our <a>pricing</a>" is translated as one sentence. Customer data
 * marked by Mark becomes a <vN/> placeholder and is put back untouched.
 * Each piece is looked up in the TranslationStore; pieces not translated
 * yet stay in English and are queued for Claude.
 *
 * Also translates the text in placeholder, title, alt and aria-label
 * attributes, button labels on inputs, and the page's meta description and
 * social titles.
 */
class HtmlTranslator
{
    /** Never translated, nor anything inside. */
    protected const SKIP = ['script', 'style', 'noscript', 'template', 'textarea', 'pre', 'code', 'svg', 'math', 'iframe', 'object', 'canvas', 'video', 'audio', 'kbd', 'samp'];

    /** Kept whole (as an <xN/> placeholder) when inside a piece of text. */
    protected const ATOMIC = ['svg', 'img', 'br', 'wbr', 'input', 'select', 'textarea', 'code', 'kbd', 'samp', 'iframe', 'video', 'audio', 'canvas', 'object', 'math', 'picture', 'script', 'style', 'noscript', 'template', 'meter', 'progress', 'hr'];

    /** Inline elements: part of the sentence around them. */
    protected const INLINE = ['a', 'abbr', 'b', 'bdi', 'bdo', 'cite', 'data', 'dfn', 'em', 'i', 'mark', 'q', 's', 'small', 'span', 'strong', 'sub', 'sup', 'time', 'u', 'var', 'label', 'button', 'font', 'del', 'ins', 'output'];

    protected const ATTRIBUTES = ['placeholder', 'title', 'alt', 'aria-label', 'aria-placeholder', 'aria-roledescription', 'aria-description'];

    protected const META = ['description', 'twitter:title', 'twitter:description', 'twitter:image:alt', 'og:title', 'og:description', 'og:image:alt'];

    protected const RAW_OPEN = 'afs-raw-open';

    protected const RAW_CLOSE = 'afs-raw-close';

    /** @var array<int, array<string, mixed>> */
    protected array $units = [];

    protected int $rawDepth = 0;

    public function __construct(protected TranslationStore $store) {}

    public static function available(): bool
    {
        return class_exists(HTMLDocument::class);
    }

    /**
     * @param  array{context?: ?string, rewriteUrl?: ?callable(string, string): ?string, head?: string, rtl?: bool}  $options
     */
    public function translateDocument(string $html, string $locale, array $options = []): string
    {
        if (! static::available() || strlen($html) > 3_000_000 || ! str_contains(strtolower(substr($html, 0, 2000)), '<html')) {
            return Mark::strip($html);
        }

        try {
            $document = HTMLDocument::createFromString(static::prepare($html), LIBXML_NOERROR);
            $this->process($document, $document->documentElement, $locale, $options);

            if ($root = $document->documentElement) {
                $root->setAttribute('lang', $locale);

                if ($options['rtl'] ?? false) {
                    $root->setAttribute('dir', 'rtl');
                }
            }

            $output = static::finish($document->saveHtml());

            if (! empty($options['head'])) {
                $output = preg_replace('#</head>#i', $options['head'].'</head>', $output, 1) ?? $output;
            }

            return $output;
        } catch (Throwable $e) {
            report($e);

            return Mark::strip($html);
        }
    }

    /**
     * A piece of a page (HTML sent back to update part of a page).
     *
     * @param  array{context?: ?string}  $options
     */
    public function translateFragment(string $html, string $locale, array $options = []): string
    {
        $start = strtolower(substr(ltrim($html), 0, 7));

        // Table rows and options can't be parsed on their own.
        if (! static::available() || preg_match('/^<(tr|td|th|tbody|thead|option)\b/', $start) || strlen($html) > 1_000_000) {
            return Mark::strip($html);
        }

        try {
            $document = HTMLDocument::createFromString('<!DOCTYPE html><html><head></head><body>'.static::prepare($html).'</body></html>', LIBXML_NOERROR);
            $this->process($document, $document->body, $locale, $options);

            return static::finish($document->body?->innerHTML ?? '');
        } catch (Throwable $e) {
            report($e);

            return Mark::strip($html);
        }
    }

    /**
     * @param  array<string, mixed>  $options
     */
    protected function process(HTMLDocument $document, ?Element $root, string $locale, array $options): void
    {
        if (! $root) {
            return;
        }

        $this->units = [];
        $this->rawDepth = 0;

        $this->repairAttributes($root);
        $this->walk($root);

        if (! empty($options['rewriteUrl'])) {
            $this->rewriteUrls($root, $options['rewriteUrl']);
        }

        $sources = [];

        foreach ($this->units as $unit) {
            $sources[$unit['hash']] = $unit['template'];
        }

        $translations = $this->store->lookup($locale, $sources, $options['context'] ?? null);

        foreach ($this->units as $unit) {
            if (isset($translations[$unit['hash']])) {
                try {
                    $this->apply($document, $unit, $translations[$unit['hash']]);
                } catch (Throwable) {
                    // A translation that doesn't fit stays in English.
                }
            }
        }
    }

    // --- Before parsing --------------------------------------------------

    /**
     * Turns {!! !!} regions into something the parser keeps in place:
     * plain text becomes a protected value, HTML is bracketed by comments,
     * and regions inside a tag, a script or a style are just unmarked.
     */
    public static function prepare(string $html): string
    {
        if (! str_contains($html, Mark::RAW_OPEN)) {
            return $html;
        }

        $output = '';
        $depth = 0;
        $regionStart = 0;
        $offset = 0;
        $length = strlen($html);
        $open = Mark::RAW_OPEN;
        $close = Mark::RAW_CLOSE;
        $markerLength = strlen($open);

        while ($offset < $length) {
            $nextOpen = strpos($html, $open, $offset);
            $nextClose = strpos($html, $close, $offset);

            if ($nextOpen === false && $nextClose === false) {
                break;
            }

            $isOpen = $nextOpen !== false && ($nextClose === false || $nextOpen < $nextClose);
            $position = $isOpen ? $nextOpen : $nextClose;

            if ($isOpen) {
                if ($depth === 0) {
                    $output .= substr($html, $offset, $position - $offset);
                    $regionStart = $position + $markerLength;
                }

                $depth++;
            } elseif ($depth > 0) {
                $depth--;

                if ($depth === 0) {
                    $content = str_replace([$open, $close], '', substr($html, $regionStart, $position - $regionStart));
                    $output .= static::wrapRegion($output, $content);
                }
            } else {
                $output .= substr($html, $offset, $position - $offset); // stray close marker
            }

            $offset = $position + $markerLength;
        }

        if ($depth > 0) {
            $output .= str_replace([$open, $close], '', substr($html, $regionStart));
        } else {
            $output .= substr($html, $offset);
        }

        return $output;
    }

    protected static function wrapRegion(string $before, string $content): string
    {
        $lastLt = strrpos($before, '<');
        $lastGt = strrpos($before, '>');
        $insideTag = $lastLt !== false && ($lastGt === false || $lastLt > $lastGt);

        foreach (['script', 'style', 'textarea', 'title'] as $tag) {
            $opened = strripos($before, '<'.$tag);
            $closed = strripos($before, '</'.$tag);

            if ($opened !== false && ($closed === false || $opened > $closed)) {
                return $content;
            }
        }

        if ($insideTag) {
            return $content;
        }

        if (! str_contains($content, '<')) {
            return Mark::VALUE_OPEN.$content.Mark::VALUE_CLOSE;
        }

        return '<!--'.self::RAW_OPEN.'-->'.$content.'<!--'.self::RAW_CLOSE.'-->';
    }

    protected static function finish(string $html): string
    {
        return Mark::strip(str_replace(['<!--'.self::RAW_OPEN.'-->', '<!--'.self::RAW_CLOSE.'-->'], '', $html));
    }

    /**
     * A marked value echoed inside a tag (an attribute list) can end up in
     * an attribute's name; clean those up.
     */
    protected function repairAttributes(Element $root): void
    {
        foreach ($root->getElementsByTagName('*') as $element) {
            foreach (iterator_to_array($element->attributes) as $attribute) {
                if (! Mark::contains($attribute->name)) {
                    continue;
                }

                $name = Mark::strip($attribute->name);
                $value = Mark::strip($attribute->value);
                $element->removeAttribute($attribute->name);

                try {
                    if ($name !== '' && ! $element->hasAttribute($name)) {
                        $element->setAttribute($name, $value);
                    }
                } catch (Throwable) {
                    // Not a valid attribute name once cleaned: dropped.
                }
            }
        }
    }

    // --- Finding the text --------------------------------------------------

    protected function walk(Element $element): void
    {
        if ($this->isSkipped($element)) {
            return;
        }

        $this->collectAttributes($element);

        [$hasBlock, $hasRaw, $hasLetters] = $this->analyze($element);

        if (! $hasBlock && ! $hasRaw) {
            if ($hasLetters) {
                $this->addUnitFor($element);
            }

            $this->collectAttributesInside(iterator_to_array($element->childNodes));

            return;
        }

        $run = [];

        foreach (iterator_to_array($element->childNodes) as $child) {
            if ($child instanceof Comment && in_array($child->data, [self::RAW_OPEN, self::RAW_CLOSE], true)) {
                $this->flushRun($run);
                $this->rawDepth = $child->data === self::RAW_OPEN ? $this->rawDepth + 1 : max(0, $this->rawDepth - 1);

                continue;
            }

            if ($this->rawDepth > 0) {
                if ($child instanceof Element) {
                    $this->trackRawComments($child);
                }

                continue;
            }

            if ($child instanceof Text || $child instanceof Comment) {
                $run[] = $child;

                continue;
            }

            if (! $child instanceof Element) {
                continue;
            }

            $tag = strtolower($child->localName);

            if (in_array($tag, self::ATOMIC, true) || $this->isNoTranslate($child)) {
                $run[] = $child;

                continue;
            }

            if (in_array($tag, self::INLINE, true)) {
                [$childBlock, $childRaw] = $this->analyze($child);

                if (! $childBlock && ! $childRaw) {
                    $run[] = $child;

                    continue;
                }
            }

            $this->flushRun($run);
            $this->walk($child);
        }

        $this->flushRun($run);
    }

    /**
     * @param  array<int, Node>  $run
     */
    protected function flushRun(array &$run): void
    {
        if ($run !== []) {
            $this->collectAttributesInside($run);
            $nodes = $this->trimRun($run);

            // A lone inline element (a button, a link): translate what's
            // inside it, so the same label reads the same everywhere.
            if (count($nodes) === 1 && $nodes[0] instanceof Element) {
                $this->addUnitFor($nodes[0]);
            } elseif ($this->isListOfLabels($nodes)) {
                $this->addUnitsForEach($nodes);
            } elseif ($nodes !== []) {
                $this->addUnit('run', $nodes);
            }
        }

        $run = [];
    }

    /**
     * The text inside an element with no blocks in it, without the icons
     * at either end; inside a lone inline child, that child's text.
     */
    protected function addUnitFor(Element $element): void
    {
        $children = iterator_to_array($element->childNodes);
        $nodes = $this->trimRun($children);

        if ($nodes === []) {
            return;
        }

        if (count($nodes) === 1 && $nodes[0] instanceof Element && in_array(strtolower($nodes[0]->localName), self::INLINE, true)) {
            $this->addUnitFor($nodes[0]);

            return;
        }

        if ($this->isListOfLabels($nodes)) {
            $this->addUnitsForEach($nodes);

            return;
        }

        count($nodes) === count($children)
            ? $this->addUnit('element', $children, $element)
            : $this->addUnit('run', $nodes);
    }

    /**
     * Separate labels side by side with no words between them (a row of
     * links or buttons, a title next to its description): each is its own
     * piece of text, not one sentence.
     *
     * @param  array<int, Node>  $nodes
     */
    protected function isListOfLabels(array $nodes): bool
    {
        $labels = 0;

        foreach ($nodes as $node) {
            if ($node instanceof Text) {
                if (trim(str_replace("\u{A0}", ' ', Mark::strip($node->data))) !== '') {
                    return false;
                }
            } elseif ($node instanceof Element && ! in_array(strtolower($node->localName), self::ATOMIC, true) && ! $this->isNoTranslate($node) && $this->analyze($node)[2]) {
                $labels++;
            }
        }

        return $labels > 1;
    }

    /**
     * @param  array<int, Node>  $nodes
     */
    protected function addUnitsForEach(array $nodes): void
    {
        foreach ($nodes as $node) {
            if ($node instanceof Element && ! in_array(strtolower($node->localName), self::ATOMIC, true) && ! $this->isNoTranslate($node) && $this->analyze($node)[2]) {
                $this->addUnitFor($node);
            }
        }
    }

    /**
     * Drops icons, empty elements and whitespace from both ends of a run:
     * they don't need translating.
     *
     * @param  array<int, Node>  $run
     * @return array<int, Node>
     */
    protected function trimRun(array $run): array
    {
        $isEdge = function (Node $node): bool {
            if ($node instanceof Text) {
                return trim(str_replace("\u{A0}", ' ', static::withoutValues($node->data))) === '' && ! str_contains($node->data, Mark::VALUE_OPEN);
            }

            if ($node instanceof Comment) {
                return true;
            }

            return $node instanceof Element && (in_array(strtolower($node->localName), self::ATOMIC, true) || $this->isNoTranslate($node) || ! $this->analyze($node)[2]);
        };

        while ($run !== [] && $isEdge($run[0])) {
            array_shift($run);
        }

        while ($run !== [] && $isEdge($run[count($run) - 1])) {
            array_pop($run);
        }

        return array_values($run);
    }

    /**
     * Raw (customer) HTML is skipped, but its end marker may be inside it.
     */
    protected function trackRawComments(Element $element): void
    {
        foreach ($element->childNodes as $child) {
            if ($child instanceof Comment && $child->data === self::RAW_OPEN) {
                $this->rawDepth++;
            } elseif ($child instanceof Comment && $child->data === self::RAW_CLOSE) {
                $this->rawDepth = max(0, $this->rawDepth - 1);
            } elseif ($child instanceof Element) {
                $this->trackRawComments($child);
            }
        }
    }

    /**
     * @return array{0: bool, 1: bool, 2: bool} has block children, has raw HTML, has words
     */
    protected function analyze(Element $element): array
    {
        $hasBlock = false;
        $hasRaw = false;
        $hasLetters = false;

        foreach ($element->childNodes as $child) {
            if ($child instanceof Text) {
                $hasLetters = $hasLetters || static::hasLetters(static::withoutValues($child->data));
            } elseif ($child instanceof Comment) {
                $hasRaw = $hasRaw || in_array($child->data, [self::RAW_OPEN, self::RAW_CLOSE], true);
            } elseif ($child instanceof Element) {
                $tag = strtolower($child->localName);

                if (in_array($tag, self::ATOMIC, true) || $this->isNoTranslate($child)) {
                    continue;
                }

                if (! in_array($tag, self::INLINE, true)) {
                    $hasBlock = true;
                }

                [$childBlock, $childRaw, $childLetters] = $this->analyze($child);
                $hasBlock = $hasBlock || $childBlock;
                $hasRaw = $hasRaw || $childRaw;
                $hasLetters = $hasLetters || $childLetters;
            }

            if ($hasBlock && $hasRaw) {
                break;
            }
        }

        return [$hasBlock, $hasRaw, $hasLetters];
    }

    protected function isSkipped(Element $element): bool
    {
        return in_array(strtolower($element->localName), self::SKIP, true) || $this->isNoTranslate($element);
    }

    protected function isNoTranslate(Element $element): bool
    {
        if (strtolower((string) $element->getAttribute('translate')) === 'no' || $element->hasAttribute('data-no-translate')) {
            return true;
        }

        return in_array('notranslate', explode(' ', (string) $element->getAttribute('class')), true);
    }

    // --- Templates ---------------------------------------------------------

    /**
     * @param  array<int, Node>  $nodes
     */
    protected function addUnit(string $kind, array $nodes, ?Element $element = null): void
    {
        $parts = [];
        $counters = ['g' => 0, 'x' => 0, 'v' => 0];
        $plain = '';
        $template = $this->serialize($nodes, $parts, $counters, $plain);

        if (! static::hasLetters($plain)) {
            return;
        }

        preg_match('/^\s*/u', $template, $lead);
        preg_match('/\s*$/u', $template, $trail);
        $normalized = static::normalize($template);

        if ($normalized === '' || mb_strlen($normalized) > 5000) {
            return;
        }

        $this->units[] = [
            'kind' => $kind,
            'nodes' => $nodes,
            'element' => $element,
            'template' => $normalized,
            'hash' => Translation::hashFor($normalized),
            'parts' => $parts,
            'lead' => $lead[0] !== '' ? ' ' : '',
            'trail' => $trail[0] !== '' ? ' ' : '',
        ];
    }

    /**
     * @param  iterable<Node>  $nodes
     * @param  array<string, mixed>  $parts
     * @param  array<string, int>  $counters
     */
    protected function serialize(iterable $nodes, array &$parts, array &$counters, string &$plain): string
    {
        $out = '';

        foreach ($nodes as $node) {
            if ($node instanceof Text) {
                $out .= $this->textToTemplate($node->data, $parts, $counters, $plain);
            } elseif ($node instanceof Comment) {
                $key = 'x'.(++$counters['x']);
                $parts[$key] = '<!--'.$node->data.'-->';
                $out .= '<'.$key.'/>';
            } elseif ($node instanceof Element) {
                $tag = strtolower($node->localName);
                [, , $letters] = $this->analyze($node);

                if (in_array($tag, self::ATOMIC, true) || $this->isNoTranslate($node) || ! $letters) {
                    $key = 'x'.(++$counters['x']);
                    $parts[$key] = Mark::strip((string) $node->ownerDocument->saveHtml($node));
                    $out .= '<'.$key.'/>';

                    continue;
                }

                $key = 'g'.(++$counters['g']);
                $parts[$key] = ['open' => $this->openTag($node), 'close' => '</'.$tag.'>'];
                $out .= '<'.$key.'>'.$this->serialize(iterator_to_array($node->childNodes), $parts, $counters, $plain).'</'.$key.'>';
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $parts
     * @param  array<string, int>  $counters
     */
    protected function textToTemplate(string $text, array &$parts, array &$counters, string &$plain): string
    {
        $out = '';
        $pieces = preg_split('/\x{E000}(.*?)\x{E001}/su', $text, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [$text];

        foreach ($pieces as $index => $piece) {
            if ($index % 2 === 1) {
                $key = 'v'.(++$counters['v']);
                $parts[$key] = htmlspecialchars(Mark::strip($piece), ENT_NOQUOTES, 'UTF-8');
                $out .= '<'.$key.'/>';
            } else {
                $piece = Mark::strip($piece);
                $plain .= $piece;
                $out .= htmlspecialchars($piece, ENT_NOQUOTES, 'UTF-8');
            }
        }

        return $out;
    }

    protected function openTag(Element $element): string
    {
        $tag = '<'.strtolower($element->localName);

        foreach ($element->attributes as $attribute) {
            $tag .= ' '.$attribute->name.'="'.htmlspecialchars(Mark::strip($attribute->value), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'"';
        }

        return $tag.'>';
    }

    // --- Attributes --------------------------------------------------------

    /**
     * @param  array<int, Node>  $nodes
     */
    protected function collectAttributesInside(array $nodes): void
    {
        foreach ($nodes as $node) {
            if (! $node instanceof Element || $this->isSkipped($node)) {
                continue;
            }

            $this->collectAttributes($node);
            $this->collectAttributesInside(iterator_to_array($node->childNodes));
        }
    }

    protected function collectAttributes(Element $element): void
    {
        $tag = strtolower($element->localName);
        $names = self::ATTRIBUTES;

        if ($tag === 'input' && in_array(strtolower((string) $element->getAttribute('type')), ['submit', 'button', 'reset'], true)) {
            $names[] = 'value';
        }

        if ($tag === 'meta') {
            $key = strtolower((string) ($element->getAttribute('name') ?: $element->getAttribute('property')));
            $names = in_array($key, self::META, true) ? ['content'] : [];
        }

        foreach ($names as $name) {
            if (! $element->hasAttribute($name)) {
                continue;
            }

            $parts = [];
            $counters = ['g' => 0, 'x' => 0, 'v' => 0];
            $plain = '';
            $template = static::normalize($this->textToTemplate((string) $element->getAttribute($name), $parts, $counters, $plain));

            if (! static::hasLetters($plain) || mb_strlen($template) > 2000) {
                continue;
            }

            $this->units[] = [
                'kind' => 'attr',
                'element' => $element,
                'attribute' => $name,
                'template' => $template,
                'hash' => Translation::hashFor($template),
                'parts' => $parts,
                'lead' => '',
                'trail' => '',
            ];
        }
    }

    // --- Putting translations in --------------------------------------------

    /**
     * @param  array<string, mixed>  $unit
     */
    protected function apply(HTMLDocument $document, array $unit, string $translation): void
    {
        if (! static::placeholdersMatch($unit['template'], $translation)) {
            return;
        }

        $html = $unit['lead'].static::rebuild($translation, $unit['parts']).$unit['trail'];

        if ($unit['kind'] === 'attr') {
            $unit['element']->setAttribute($unit['attribute'], html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

            return;
        }

        if ($unit['kind'] === 'element') {
            $unit['element']->innerHTML = $html;

            return;
        }

        $nodes = $unit['nodes'];
        $first = $nodes[0];
        $parent = $first->parentNode;

        if (! $parent) {
            return;
        }

        $holder = $document->createElement('span');
        $holder->innerHTML = $html;

        foreach (iterator_to_array($holder->childNodes) as $child) {
            $parent->insertBefore($child, $first);
        }

        foreach ($nodes as $node) {
            $node->parentNode?->removeChild($node);
        }
    }

    /**
     * @param  array<string, mixed>  $parts
     */
    public static function rebuild(string $translation, array $parts): string
    {
        // Any other < or > in a translation is shown as text, never markup.
        return (string) preg_replace_callback('#<(/?)([gxv])(\d+)\s*(/?)>|[<>]#', function (array $match) use ($parts) {
            if (! isset($match[2])) {
                return $match[0] === '<' ? '&lt;' : '&gt;';
            }

            $key = $match[2].$match[3];

            if (! isset($parts[$key])) {
                throw new RuntimeException("Unknown placeholder {$key}.");
            }

            if ($match[2] === 'g') {
                return $match[1] === '/' ? $parts[$key]['close'] : $parts[$key]['open'];
            }

            return $parts[$key];
        }, $translation);
    }

    /**
     * Whether a translation keeps exactly the placeholders of its source.
     */
    public static function placeholdersMatch(string $source, string $translation): bool
    {
        return static::placeholders($source) === static::placeholders($translation);
    }

    /**
     * @return array<int, string>
     */
    public static function placeholders(string $text): array
    {
        preg_match_all('#<(/?)([gxv])(\d+)\s*(/?)>#', $text, $matches, PREG_SET_ORDER);

        $found = array_map(fn ($match) => $match[2].$match[3].($match[1] === '/' ? 'c' : ''), $matches);
        sort($found);

        return $found;
    }

    // --- Addresses -----------------------------------------------------------

    /**
     * @param  callable(string, string): ?string  $rewrite
     */
    protected function rewriteUrls(Element $root, callable $rewrite): void
    {
        foreach ($root->getElementsByTagName('*') as $element) {
            $tag = strtolower($element->localName);

            if ($element->hasAttribute('data-locale-link')) {
                continue;
            }

            [$attribute, $method] = match (true) {
                $tag === 'a' => ['href', 'GET'],
                $tag === 'form' => ['action', strtoupper((string) ($element->getAttribute('method') ?: 'GET'))],
                $tag === 'link' && strtolower((string) $element->getAttribute('rel')) === 'canonical' => ['href', 'GET'],
                $tag === 'meta' && strtolower((string) $element->getAttribute('property')) === 'og:url' => ['content', 'GET'],
                default => [null, null],
            };

            if ($attribute === null || ! $element->hasAttribute($attribute)) {
                continue;
            }

            $rewritten = $rewrite(Mark::strip((string) $element->getAttribute($attribute)), $method);

            if ($rewritten !== null) {
                $element->setAttribute($attribute, $rewritten);
            }
        }
    }

    // --- Helpers ---------------------------------------------------------------

    public static function normalize(string $text): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }

    public static function hasLetters(string $text): bool
    {
        return (bool) preg_match('/\p{L}/u', $text);
    }

    protected static function withoutValues(string $text): string
    {
        return (string) preg_replace('/\x{E000}.*?\x{E001}/su', '', $text);
    }
}
