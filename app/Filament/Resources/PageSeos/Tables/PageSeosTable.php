<?php

namespace App\Filament\Resources\PageSeos\Tables;

use App\Filament\Resources\PageSeos\PageSeoResource;
use App\Filament\Resources\SitePages\SitePageResource;
use App\Models\PageSeo;
use App\Models\SitePage;
use App\Models\SiteSetting;
use App\Services\Seo\SeoHeadBuilder;
use App\Services\Seo\SeoScore;
use App\Support\Seo\PublicPages;
use App\Support\Seo\RenderedPageSnapshot;
use App\Support\Seo\SeoImage;
use App\Support\Seo\SeoInput;
use Filament\Actions\Action;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Custom-data table: the route-based pages (PageSeo) and every Site Page in
 * one list, each with its live SEO score, so nothing public is ever missing
 * from the SEO overview.
 */
class PageSeosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->records(fn (array $filters): array => self::rows($filters))
            ->paginated(false)
            ->columns([
                TextColumn::make('label')
                    ->label('Page')
                    ->weight('semibold')
                    ->description(fn (array $record): string => $record['path']),
                TextColumn::make('kind')
                    ->label('Type')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'Built-in page' ? 'info' : 'gray'),
                TextColumn::make('focus_keyword')
                    ->label('Focus keyword')
                    ->placeholder('Not set')
                    ->wrap(),
                TextColumn::make('score')
                    ->label('SEO score')
                    ->badge()
                    ->formatStateUsing(fn (int $state): string => "{$state} / 100")
                    ->color(fn (int $state): string => SeoScore::colorFor($state)),
                IconColumn::make('indexable')
                    ->label('In search')
                    ->boolean()
                    ->tooltip(fn (array $record): string => $record['indexable'] ? 'Indexable and listed in the sitemap' : 'noindex — hidden from search and the sitemap'),
                TextColumn::make('tags')
                    ->label('Tags')
                    ->badge()
                    ->separator(',')
                    ->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('tag')
                    ->label('Tag')
                    ->options(fn (): array => collect(self::rows([]))->pluck('tags')->flatten()->filter()->unique()->sort()->mapWithKeys(fn ($tag) => [$tag => $tag])->all()),
                SelectFilter::make('kind')
                    ->label('Type')
                    ->options(['Built-in page' => 'Built-in pages', 'Site page' => 'Site pages']),
            ])
            ->recordActions([
                Action::make('edit_seo')
                    ->label('Edit SEO')
                    ->icon('heroicon-o-pencil-square')
                    ->url(fn (array $record): string => $record['edit_url']),
                Action::make('view')
                    ->label('View')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->color('gray')
                    ->url(fn (array $record): ?string => $record['url'], shouldOpenInNewTab: true)
                    ->visible(fn (array $record): bool => $record['url'] !== null),
            ]);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, array<string, mixed>>
     */
    public static function rows(array $filters): array
    {
        $rows = [];
        $siteName = (string) SiteSetting::get('site_name', 'AffilStack');
        $builder = app(SeoHeadBuilder::class);
        $score = app(SeoScore::class);
        $defaultImage = SiteSetting::get('seo_og_image_path');

        $pages = PageSeo::query()->get()->keyBy('route_name');

        foreach (array_keys(PublicPages::all()) as $routeName) {
            $page = $pages->get($routeName);

            if (! $page) {
                continue;
            }

            $input = $page->seoInput();
            $snapshot = RenderedPageSnapshot::get($routeName);
            $label = PublicPages::label($routeName);
            $title = $input->seoTitle
                ? $builder->renderTitle($input->seoTitle, $label, $siteName)
                : ($snapshot['title'] ?? '');
            $url = PublicPages::url($routeName);

            $rows["route:{$routeName}"] = self::row(
                $label.(PublicPages::isLive($routeName) ? '' : ' (switched off)'),
                'Built-in page',
                $url,
                $input,
                $score->evaluate($input, [
                    'title' => $title,
                    'description' => $input->metaDescription ?: ($snapshot['description'] ?? ''),
                    'content' => $snapshot['text'] ?? null,
                    'url' => $url,
                    'image' => SeoImage::dimensions($input->ogImagePath ?? $defaultImage) ?? (($input->ogImagePath ?? $defaultImage) ? ['width' => null, 'height' => null] : null),
                    'image_is_default' => ! $input->ogImagePath && $defaultImage,
                ])['score'],
                PageSeoResource::getUrl('edit', ['record' => $page]),
            );
        }

        foreach (SitePage::query()->where('slug', '!=', 'about')->orderBy('title')->get() as $sitePage) {
            $input = $sitePage->seoInput();
            $url = url('/'.$sitePage->slug);
            $title = $input->seoTitle
                ? $builder->renderTitle($input->seoTitle, $sitePage->title, $siteName)
                : $builder->renderTitle($builder->titleTemplate(), $sitePage->title, $siteName);

            $rows["page:{$sitePage->id}"] = self::row(
                $sitePage->title.($sitePage->is_published ? '' : ' (draft)'),
                'Site page',
                $sitePage->is_published ? $url : null,
                $input,
                $score->evaluate($input, [
                    'title' => $title,
                    'description' => $input->metaDescription ?: (string) SiteSetting::get('seo_meta_description', ''),
                    'content' => RenderedPageSnapshot::plainText((string) $sitePage->content),
                    'url' => $url,
                    'image' => SeoImage::dimensions($input->ogImagePath ?? $defaultImage) ?? (($input->ogImagePath ?? $defaultImage) ? ['width' => null, 'height' => null] : null),
                    'image_is_default' => ! $input->ogImagePath && $defaultImage,
                ])['score'],
                SitePageResource::getUrl('edit', ['record' => $sitePage]),
                '/'.$sitePage->slug,
            );
        }

        if ($tag = $filters['tag']['value'] ?? null) {
            $rows = array_filter($rows, fn (array $row) => in_array($tag, $row['tags'], true));
        }

        if ($kind = $filters['kind']['value'] ?? null) {
            $rows = array_filter($rows, fn (array $row) => $row['kind'] === $kind);
        }

        return $rows;
    }

    /**
     * @return array<string, mixed>
     */
    protected static function row(string $label, string $kind, ?string $url, SeoInput $input, int $score, string $editUrl, ?string $path = null): array
    {
        return [
            'label' => $label,
            'kind' => $kind,
            'path' => $path ?? ($url ? ((string) parse_url($url, PHP_URL_PATH) ?: '/') : '—'),
            'url' => $url,
            'focus_keyword' => $input->focusKeyword,
            'score' => $score,
            'indexable' => $input->index,
            'tags' => $input->tags,
            'edit_url' => $editUrl,
        ];
    }
}
