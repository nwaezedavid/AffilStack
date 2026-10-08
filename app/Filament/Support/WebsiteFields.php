<?php

namespace App\Filament\Support;

use App\Support\YouTubeUrl;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View;
use Filament\Support\Icons\Heroicon;

/**
 * Building blocks shared by the Website → … editors (homepage, feature
 * pages, page copy, navigation): a clearly labelled image spot that shows
 * what's live right now, a "View this section on the site" link, safe link
 * inputs and a YouTube field with a live preview.
 */
class WebsiteFields
{
    /**
     * The "where / what's showing / size / what to show" card for an image
     * field. $default may be a file name in public/images/features or a
     * closure resolving one from the form (e.g. a repeater item's default).
     */
    public static function imageSlot(string $field, string|Closure $where, string $size, string $shows, string|Closure|null $default = null): View
    {
        return View::make('filament.website.image-slot')
            ->viewData(fn (Get $get): array => [
                'current' => static::persistedPath($get($field)),
                'default' => $default instanceof Closure ? $default($get) : $default,
                'where' => $where instanceof Closure ? $where($get) : $where,
                'size' => $size,
                'shows' => $shows,
            ]);
    }

    /**
     * An image upload (auto-WebP) with the slot's details as helper text.
     */
    public static function imageUpload(string $field, string $label, string $directory, string $size): FileUpload
    {
        return WebpFileUpload::make($field)
            ->label($label)
            ->image()
            ->maxSize(4096)
            ->disk('public')
            ->directory($directory)
            ->imagePreviewHeight('140')
            ->helperText('Optional — replaces the image shown above. '.$size.', PNG, JPG or WebP; converted to WebP automatically.');
    }

    public static function viewOnSite(string $name, string|Closure $url, string $label = 'View this section on the site'): Actions
    {
        return Actions::make([
            Action::make($name)
                ->label($label)
                ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                ->color('gray')
                ->size('sm')
                ->url($url)
                ->openUrlInNewTab(),
        ]);
    }

    /**
     * A site path, in-page #anchor or http(s) link — never javascript:/data:.
     */
    public static function linkInput(string $name, string $label, ?string $placeholder = null): TextInput
    {
        return TextInput::make($name)
            ->label($label)
            ->placeholder($placeholder)
            ->maxLength(255)
            ->regex('#^(/(?![/\\\\])|https?://|\#[A-Za-z0-9_-])#i')
            ->validationMessages(['regex' => 'Use a site path like /pricing, an in-page link like #how-it-works, or a full https:// link.']);
    }

    public static function youTubeInput(string $name, string $label): TextInput
    {
        return TextInput::make($name)
            ->label($label)
            ->placeholder('https://www.youtube.com/watch?v=...')
            ->prefixIcon(Heroicon::OutlinedPlay)
            ->maxLength(255)
            ->live(onBlur: true)
            ->rules([
                fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                    if (filled($value) && ! YouTubeUrl::idFrom((string) $value)) {
                        $fail('That doesn\'t look like a YouTube video link. Copy the address from the video page or the "Share" button.');
                    }
                },
            ]);
    }

    public static function youTubePreview(string $field): View
    {
        return View::make('filament.homepage.youtube-preview')
            ->viewData(fn (Get $get): array => ['url' => $get($field)]);
    }

    /**
     * A FileUpload's state is an array of uuid => path while editing; the
     * stored value is a plain path string.
     */
    public static function persistedPath(mixed $state): ?string
    {
        if (is_array($state)) {
            $state = collect($state)->first(fn ($value): bool => is_string($value));
        }

        return is_string($state) && $state !== '' ? $state : null;
    }
}
