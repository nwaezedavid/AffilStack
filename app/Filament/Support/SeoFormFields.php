<?php

namespace App\Filament\Support;

use App\Models\PageSeo;
use App\Models\SiteSetting;
use App\Services\AI\AIGenerationException;
use App\Services\Seo\FaviconGenerator;
use App\Services\Seo\SeoHeadBuilder;
use App\Services\Seo\SeoMetaAssistant;
use App\Services\Seo\SeoScore;
use App\Support\Seo\PublicPages;
use App\Support\Seo\RenderedPageSnapshot;
use App\Support\Seo\SeoImage;
use App\Support\Seo\SeoInput;
use App\Support\SiteDomains;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\CodeEditor;
use Filament\Forms\Components\CodeEditor\Enums\Language;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * The shared RankMath-style SEO editor used by both SEO > Page SEO
 * (route-based pages, PageSeo) and Content > Site Pages (SitePage): search
 * fields with live character counters and the "Generate with AI" helper,
 * social (Open Graph / X) overrides, advanced controls (canonical, robots,
 * schema type, custom JSON-LD), a live Google + social card preview and the
 * on-page checklist with its score.
 *
 * $context is 'page_seo' or 'site_page' — the two differ only in where the
 * page's name, URL, built-in defaults and body text come from, and in how
 * robots is stored (robots_index/robots_follow vs no_index/no_follow).
 */
class SeoFormFields
{
    /**
     * @return array<int, Component>
     */
    public static function make(string $context): array
    {
        return [
            Grid::make(['default' => 1, 'xl' => 5])
                ->columnSpanFull()
                ->schema([
                    Tabs::make('SEO')
                        ->columnSpan(['default' => 1, 'xl' => 3])
                        ->tabs([
                            Tab::make('Search')->icon('heroicon-o-magnifying-glass')->schema(self::searchFields($context)),
                            Tab::make('Social')->icon('heroicon-o-share')->schema(self::socialFields()),
                            Tab::make('Advanced')->icon('heroicon-o-adjustments-horizontal')->schema(self::advancedFields($context)),
                        ]),
                    Grid::make(1)
                        ->columnSpan(['default' => 1, 'xl' => 2])
                        ->schema([
                            Section::make('Search appearance')
                                ->description('Live preview — updates as you type.')
                                ->icon('heroicon-o-eye')
                                ->compact()
                                ->schema([
                                    Placeholder::make('search_appearance_preview')
                                        ->hiddenLabel()
                                        ->content(fn ($get, ?Model $record) => new HtmlString(
                                            view('filament.seo.search-appearance', self::resolve($context, $get, $record))->render()
                                        )),
                                ]),
                            Section::make('SEO checklist')
                                ->icon('heroicon-o-clipboard-document-check')
                                ->compact()
                                ->schema([
                                    Placeholder::make('seo_checklist')
                                        ->hiddenLabel()
                                        ->content(function ($get, ?Model $record) use ($context) {
                                            $resolved = self::resolve($context, $get, $record);

                                            return new HtmlString(view('filament.seo.checklist', $resolved['score'])->render());
                                        }),
                                ]),
                        ]),
                ]),
        ];
    }

    /**
     * @return array<int, Component>
     */
    protected static function searchFields(string $context): array
    {
        return [
            TextInput::make('focus_keyword')
                ->label('Focus keyword')
                ->live(onBlur: true)
                ->maxLength(120)
                ->placeholder('e.g. affiliate marketing software')
                ->helperText('The one phrase this page should rank for. Drives the checklist and goes first in the keywords meta tag.'),
            TagsInput::make('secondary_keywords')
                ->label('Secondary keywords')
                ->live()
                ->placeholder('Add a keyword and press Enter')
                ->splitKeys(['Tab', ','])
                ->helperText('Related phrases and synonyms (3–5 is plenty). Checked against the title, description and copy.'),
            TextInput::make('seo_title')
                ->label('SEO title')
                ->maxLength(120)
                ->live(debounce: 500)
                ->placeholder(fn ($get, ?Model $record) => self::resolve($context, $get, $record)['default_title'])
                ->hint(fn ($get, ?Model $record) => self::counter(mb_strlen(self::resolve($context, $get, $record)['title']), SeoScore::TITLE_MIN, SeoScore::TITLE_MAX))
                ->hintColor(fn ($get, ?Model $record) => self::counterColor(mb_strlen(self::resolve($context, $get, $record)['title']), SeoScore::TITLE_MIN, SeoScore::TITLE_MAX))
                ->helperText('Blank = the built-in title shown as the placeholder. Variables: %page% (page name) and %site% (site name), e.g. "Plans & pricing · %site%". Aim for 50–60 characters.')
                ->hintAction(self::aiAction($context)),
            Textarea::make('meta_description')
                ->label('Meta description')
                ->rows(3)
                ->maxLength(320)
                ->live(debounce: 500)
                ->placeholder(fn ($get, ?Model $record) => self::resolve($context, $get, $record)['default_description'])
                ->hint(fn ($get, ?Model $record) => self::counter(mb_strlen(self::resolve($context, $get, $record)['description']), SeoScore::DESCRIPTION_MIN, SeoScore::DESCRIPTION_MAX))
                ->hintColor(fn ($get, ?Model $record) => self::counterColor(mb_strlen(self::resolve($context, $get, $record)['description']), SeoScore::DESCRIPTION_MIN, SeoScore::DESCRIPTION_MAX))
                ->helperText('The snippet under the title in Google. Aim for 150–160 characters and include the focus keyword.'),
            TagsInput::make('tags')
                ->label('Tags')
                ->placeholder('Add a tag and press Enter')
                ->splitKeys(['Tab', ','])
                ->helperText('Topic labels. Added to the keywords meta tag and, on content pages, as article:tag for social networks; also used to filter the Page SEO list.'),
        ];
    }

    /**
     * @return array<int, Component>
     */
    protected static function socialFields(): array
    {
        return [
            Section::make('Facebook, LinkedIn, WhatsApp (Open Graph)')
                ->compact()
                ->schema([
                    TextInput::make('og_title')->label('Social title')->maxLength(120)->live(onBlur: true)
                        ->helperText('Blank = same as the SEO title. Can be punchier than the search title.'),
                    Textarea::make('og_description')->label('Social description')->rows(2)->maxLength(320)->live(onBlur: true)
                        ->helperText('Blank = same as the meta description.'),
                    OriginalImageUpload::make('og_image_path')
                        ->label('Social share image')
                        ->directory('seo')
                        ->live()
                        ->helperText('1200×630 PNG or JPG (1.91:1), under 5 MB, with key text kept away from the edges. Blank = the sitewide default image from SEO settings.'),
                ]),
            Section::make('X (Twitter) card')
                ->compact()
                ->collapsed()
                ->schema([
                    TextInput::make('twitter_title')->label('X title')->maxLength(120)->live(onBlur: true)
                        ->helperText('Blank = same as the social title.'),
                    Textarea::make('twitter_description')->label('X description')->rows(2)->maxLength(320)->live(onBlur: true)
                        ->helperText('Blank = same as the social description.'),
                    OriginalImageUpload::make('twitter_image_path')
                        ->label('X image')
                        ->directory('seo')
                        ->live()
                        ->helperText('Blank = same as the social share image. 1200×628 works for the large-image card.'),
                ]),
        ];
    }

    /**
     * @return array<int, Component>
     */
    protected static function advancedFields(string $context): array
    {
        $robots = $context === 'page_seo'
            ? [
                Toggle::make('robots_index')->label('Allow search engines to index this page')->default(true)->live()
                    ->helperText('Off = noindex: the page stays live but is removed from Google/Bing and from the sitemap.'),
                Toggle::make('robots_follow')->label('Let search engines follow links on this page')->default(true)->live()
                    ->helperText('Off = nofollow. Leave on unless you have a specific reason.'),
            ]
            : [
                Toggle::make('no_index')->label('Hide from search engines (noindex)')->live()
                    ->helperText('For a page that should stay live but not show up in Google/Bing — a thank-you page, a duplicate, etc. Also removes it from the sitemap.'),
                Toggle::make('no_follow')->label('Don\'t follow links on this page (nofollow)')->live(),
            ];

        return [
            ...$robots,
            TextInput::make('canonical_url')
                ->label('Canonical URL')
                ->url()
                ->maxLength(255)
                ->placeholder(fn ($get, ?Model $record) => self::resolve($context, $get, $record)['url'])
                ->helperText('Only set this when the same content lives at another URL that should rank instead. Blank = this page\'s own URL (correct for almost every page). A page canonicalised elsewhere is left out of the sitemap.'),
            Select::make('schema_type')
                ->label('Page schema type')
                ->options(PublicPages::SCHEMA_TYPES)
                ->placeholder('Automatic')
                ->helperText('Structured data that tells Google what kind of page this is. Software application / Product publish your real plan prices as Offers. FAQ page is only output where the page actually lists questions (Help, Affiliate); elsewhere it falls back to Web page.'),
            CodeEditor::make('custom_schema')
                ->label('Custom JSON-LD (optional)')
                ->language(Language::Json)
                ->rule(fn (): Closure => self::jsonRule())
                ->helperText('Advanced: any extra schema.org JSON-LD for this page (an object or array — no <script> tag). Validated as JSON; test it at search.google.com/test/rich-results.'),
        ];
    }

    /**
     * Valid JSON whose top level is an object or array.
     */
    public static function jsonRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if ($value === null || trim((string) $value) === '') {
                return;
            }

            $decoded = json_decode((string) $value, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                $fail('Custom JSON-LD is not valid JSON: '.json_last_error_msg().'.');

                return;
            }

            if (! is_array($decoded)) {
                $fail('Custom JSON-LD must be a JSON object or array.');
            }
        };
    }

    protected static function aiAction(string $context): Action
    {
        return Action::make('generate_seo_meta')
            ->label('Generate with AI')
            ->icon('heroicon-o-sparkles')
            ->action(function ($set, $get, ?Model $record) use ($context) {
                $resolved = self::resolve($context, $get, $record);

                try {
                    $suggestion = app(SeoMetaAssistant::class)->suggest(
                        $resolved['page_name'],
                        $resolved['content'] ?? '',
                        $get('focus_keyword') ?: null,
                        SeoInput::fromArray(['secondary_keywords' => $get('secondary_keywords')])->secondaryKeywords,
                    );
                } catch (AIGenerationException $e) {
                    Notification::make()->title('Could not generate suggestions')->body($e->getMessage())->danger()->send();

                    return;
                }

                $set('seo_title', $suggestion['seo_title']);
                $set('meta_description', $suggestion['meta_description']);
                Notification::make()->title('Suggestions added — review before saving')->success()->send();
            });
    }

    /**
     * Everything the preview, counters and checklist need, from the form's
     * live state plus the page's built-in defaults.
     *
     * @return array<string, mixed>
     */
    public static function resolve(string $context, $get, ?Model $record): array
    {
        $memoKey = md5((string) json_encode([
            $context,
            $record?->getKey(),
            ...array_map(
                fn (string $field) => self::fingerprint($get($field)),
                ['seo_title', 'meta_description', 'focus_keyword', 'secondary_keywords', 'tags', 'canonical_url', 'og_title', 'og_description', 'twitter_title', 'twitter_description', 'schema_type', 'robots_index', 'robots_follow', 'no_index', 'no_follow', 'title', 'slug', 'content', 'og_image_path', 'twitter_image_path'],
            ),
        ]));

        // Per-request memo (stored on the request, so it never outlives it)
        // — every counter, placeholder and the preview call resolve()
        // during one render.
        $memo = request()->attributes->get('seo_form_memo', []);

        if (! isset($memo[$memoKey])) {
            $memo[$memoKey] = self::compute($context, $get, $record);
            request()->attributes->set('seo_form_memo', $memo);
        }

        return $memo[$memoKey];
    }

    protected static function fingerprint(mixed $value): mixed
    {
        if ($value instanceof TemporaryUploadedFile) {
            return $value->getFilename();
        }

        if (is_array($value)) {
            return array_map(fn ($item) => self::fingerprint($item), $value);
        }

        return is_scalar($value) || $value === null ? $value : get_debug_type($value);
    }

    /**
     * @return array<string, mixed>
     */
    protected static function compute(string $context, $get, ?Model $record): array
    {
        $siteName = (string) SiteSetting::get('site_name', 'AffilStack');
        $builder = app(SeoHeadBuilder::class);
        $state = [];

        foreach (['seo_title', 'meta_description', 'focus_keyword', 'secondary_keywords', 'tags', 'canonical_url', 'og_title', 'og_description', 'twitter_title', 'twitter_description', 'schema_type'] as $field) {
            $state[$field] = $get($field);
        }

        $state['index'] = $context === 'page_seo' ? ($get('robots_index') ?? true) : ! $get('no_index');
        $state['follow'] = $context === 'page_seo' ? ($get('robots_follow') ?? true) : ! $get('no_follow');
        $input = SeoInput::fromArray($state);

        if ($context === 'page_seo') {
            $routeName = $record instanceof PageSeo ? $record->route_name : 'home';
            $snapshot = RenderedPageSnapshot::get($routeName);
            $pageName = PublicPages::label($routeName);
            $url = PublicPages::url($routeName) ?? SiteDomains::publicUrl();
            $defaultTitle = $snapshot['title'] ?? $builder->renderTitle($builder->titleTemplate(), $pageName, $siteName);
            $defaultDescription = ($snapshot['description'] ?? '') ?: (string) SiteSetting::get('seo_meta_description', '');
            $content = $snapshot['text'] ?? null;
        } else {
            $pageName = (string) ($get('title') ?: 'Untitled page');
            $url = url('/'.ltrim((string) $get('slug'), '/'));
            $defaultTitle = $builder->renderTitle($builder->titleTemplate(), $pageName, $siteName);
            $defaultDescription = (string) SiteSetting::get('seo_meta_description', '');
            $content = RenderedPageSnapshot::plainText((string) $get('content'));
        }

        $title = $input->seoTitle ? $builder->renderTitle($input->seoTitle, $pageName, $siteName) : $defaultTitle;
        $description = $input->metaDescription ?: $defaultDescription;

        $ogImage = self::liveImage($get('og_image_path'));
        $imageIsDefault = false;

        if ($ogImage === null && ($default = SiteSetting::get('seo_og_image_path'))) {
            $ogImage = self::liveImage($default);
            $imageIsDefault = $ogImage !== null;
        }

        $twitterImage = self::liveImage($get('twitter_image_path')) ?? $ogImage;
        $ogTitle = $input->ogTitle ?: $title;
        $ogDescription = $input->ogDescription ?: $description;
        $favicons = app(FaviconGenerator::class);

        return [
            'site_name' => $siteName,
            'page_name' => $pageName,
            'url' => $input->canonicalUrl ?: $url,
            'host' => (string) parse_url($url, PHP_URL_HOST),
            'title' => $title,
            'default_title' => $defaultTitle,
            'description' => $description,
            'default_description' => $defaultDescription,
            'content' => $content,
            'favicon' => $favicons->url('favicon-96x96.png'),
            'robots' => $input->robotsDirective(),
            'og' => ['title' => $ogTitle, 'description' => $ogDescription, 'image' => $ogImage],
            'twitter' => [
                'title' => $input->twitterTitle ?: $ogTitle,
                'description' => $input->twitterDescription ?: $ogDescription,
                'image' => $twitterImage,
            ],
            'score' => app(SeoScore::class)->evaluate($input, [
                'title' => $title,
                'description' => $description,
                'content' => $content,
                'url' => $url,
                'image' => $ogImage,
                'image_is_default' => $imageIsDefault,
            ]),
        ];
    }

    /**
     * @return array{url: string, width: ?int, height: ?int}|null
     */
    protected static function liveImage(mixed $state): ?array
    {
        if (is_array($state)) {
            $state = collect($state)->first();
        }

        if ($state instanceof TemporaryUploadedFile) {
            $size = @getimagesize($state->getRealPath());

            try {
                $url = $state->temporaryUrl();
            } catch (\Throwable) {
                return null;
            }

            return ['url' => $url, 'width' => $size[0] ?? null, 'height' => $size[1] ?? null];
        }

        if (! is_string($state) || $state === '') {
            return null;
        }

        $size = SeoImage::dimensions($state);

        return ['url' => (string) SeoImage::url($state), 'width' => $size['width'] ?? null, 'height' => $size['height'] ?? null];
    }

    protected static function counter(int $length, int $min, int $max): string
    {
        return "{$length} / {$max} characters";
    }

    protected static function counterColor(int $length, int $min, int $max): string
    {
        return match (true) {
            $length >= $min && $length <= $max => 'success',
            $length > 0 && $length <= $max + 10 => 'warning',
            default => 'danger',
        };
    }
}
