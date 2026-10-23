<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\ScopedToDepartment;
use App\Support\Features\FeatureMedia;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Html;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;

/**
 * Website → Feature demos: for every built-in feature screenshot, choose
 * whether visitors see the still screenshot or its animated demo (the
 * bundled recording, or a video uploaded here). See FeatureMedia.
 */
class FeatureDemoSettings extends Page
{
    use ScopedToDepartment;

    protected static string $department = 'content';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFilm;

    protected static string|\UnitEnum|null $navigationGroup = 'Website';

    protected static ?string $navigationLabel = 'Feature demos';

    protected static ?string $title = 'Feature demos: screenshots or animations';

    protected static ?int $navigationSort = 4;

    protected string $view = 'filament.pages.feature-demo-settings';

    /**
     * @var array<string, mixed>
     */
    public ?array $data = [];

    /**
     * @var array<string, string>|null
     */
    protected ?array $uploadedVideos = null;

    public function mount(): void
    {
        $this->fillForm();
    }

    public function form(Schema $schema): Schema
    {
        $installed = FeatureMedia::installedCount();
        $total = count(FeatureMedia::names());

        return $schema->statePath('data')->components([
            Callout::make($installed === $total
                ? "All {$total} animated demos are installed."
                : "{$installed} of {$total} animated demos are installed.")
                ->description($installed === $total
                    ? 'Pick "Animation" for any screenshot below to show its animated demo instead. The screenshot stays as the first frame while the video loads, and visitors who turn off animations on their device keep seeing the screenshot.'
                    : 'Upload the "feature animations" zip into storage/app/public of the site (it creates a feature-animations folder), or upload your own video for a screenshot below. Until a video exists, "Animation" keeps showing the screenshot.')
                ->color($installed === $total ? 'success' : 'warning'),
            Grid::make(['default' => 1, 'lg' => 2])->components(
                collect(FeatureMedia::names())->map(fn (string $name): Section => $this->card($name))->all()
            ),
        ]);
    }

    protected function card(string $name): Section
    {
        $key = self::key($name);

        return Section::make(FeatureMedia::label($name))
            ->description('Used on the homepage and feature pages wherever this screenshot appears.')
            ->compact()
            ->components([
                Html::make(fn (): HtmlString => $this->preview($name)),
                ToggleButtons::make("choice_{$key}")
                    ->label('Show visitors')
                    ->options([FeatureMedia::SCREENSHOT => 'Screenshot', FeatureMedia::ANIMATION => 'Animation'])
                    ->icons([FeatureMedia::SCREENSHOT => Heroicon::OutlinedPhoto, FeatureMedia::ANIMATION => Heroicon::OutlinedFilm])
                    ->default(FeatureMedia::SCREENSHOT)
                    ->inline()
                    ->required(),
                // The upload box lives in a modal, not on the page: 37 inline
                // upload fields each fired their own request as the page
                // opened, which ran past the host's database connection limit.
                Actions::make([
                    Action::make("upload_{$key}")
                        ->label(fn (): string => $this->uploadedVideo($name) ? 'Replace my video' : 'Upload my own video')
                        ->icon(Heroicon::OutlinedArrowUpTray)
                        ->color('gray')
                        ->size('sm')
                        ->modalHeading('Your own video: '.FeatureMedia::label($name))
                        ->modalDescription('Replaces the built-in animation for this screenshot.')
                        ->modalSubmitActionLabel('Upload')
                        ->schema([
                            FileUpload::make('video')
                                ->label('Video')
                                ->helperText('MP4 or WebM, up to 12 MB, silent.')
                                ->disk('public')
                                ->directory(FeatureMedia::DIRECTORY.'/uploads')
                                ->acceptedFileTypes(['video/mp4', 'video/webm'])
                                ->maxSize(12 * 1024)
                                ->required(),
                        ])
                        ->action(fn (array $data) => $this->storeVideo($name, $data['video'] ?? null)),
                    Action::make("remove_{$key}")
                        ->label('Remove my video')
                        ->icon(Heroicon::OutlinedTrash)
                        ->color('danger')
                        ->link()
                        ->size('sm')
                        ->visible(fn (): bool => $this->uploadedVideo($name) !== null)
                        ->requiresConfirmation()
                        ->modalDescription('This screenshot goes back to its built-in animation.')
                        ->action(fn () => $this->storeVideo($name, null)),
                ])->key("actions_{$key}"),
            ]);
    }

    protected function preview(string $name): HtmlString
    {
        $screenshot = e(asset('images/features/'.$name.'.webp'));
        $videoPath = $this->uploadedVideo($name) ?? FeatureMedia::bundledVideoPath($name);

        $video = $videoPath
            ? '<video src="'.e(Storage::disk('public')->url($videoPath)).'" poster="'.$screenshot.'" muted loop playsinline controls preload="none" style="width:100%;border-radius:8px;border:1px solid rgba(0,0,0,.1)"></video>'
            : '<div style="display:flex;align-items:center;justify-content:center;aspect-ratio:16/10;border-radius:8px;border:1px dashed rgba(0,0,0,.2);font-size:12px;color:#6b7280;text-align:center;padding:8px">No animation installed yet</div>';

        return new HtmlString('<div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">'
            .'<figure style="margin:0"><img src="'.$screenshot.'" alt="" loading="lazy" style="width:100%;border-radius:8px;border:1px solid rgba(0,0,0,.1)"><figcaption style="font-size:11px;color:#6b7280;margin-top:4px">Screenshot</figcaption></figure>'
            .'<figure style="margin:0">'.$video.'<figcaption style="font-size:11px;color:#6b7280;margin-top:4px">Animation</figcaption></figure>'
            .'</div>');
    }

    /**
     * The admin's own video for a screenshot, when one is saved and still on disk.
     */
    protected function uploadedVideo(string $name): ?string
    {
        $this->uploadedVideos ??= FeatureMedia::uploadedVideos();
        $path = $this->uploadedVideos[$name] ?? null;

        return FeatureMedia::isUploadPath($path) && Storage::disk('public')->exists($path) ? $path : null;
    }

    /**
     * Saves (or, with null, removes) the admin's own video for one
     * screenshot straight away, deleting the file it replaces.
     */
    protected function storeVideo(string $name, mixed $path): void
    {
        $path = is_array($path) ? reset($path) : $path;
        $videos = FeatureMedia::uploadedVideos();
        $previous = $videos[$name] ?? null;

        if (is_string($path) && FeatureMedia::isUploadPath($path)) {
            $videos[$name] = $path;
        } else {
            unset($videos[$name]);
        }

        FeatureMedia::save(FeatureMedia::choices(), $videos);
        $this->uploadedVideos = null;

        if (FeatureMedia::isUploadPath($previous) && $previous !== ($videos[$name] ?? null)) {
            Storage::disk('public')->delete($previous);
        }

        Notification::make()
            ->title(isset($videos[$name]) ? 'Video uploaded' : 'Video removed')
            ->body(isset($videos[$name])
                ? 'Visitors see it wherever this screenshot is set to "Animation".'
                : 'This screenshot uses its built-in animation again.')
            ->success()
            ->send();
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $choices = [];

        foreach (FeatureMedia::names() as $name) {
            $choices[$name] = (string) ($data['choice_'.self::key($name)] ?? FeatureMedia::SCREENSHOT);
        }

        FeatureMedia::save($choices, FeatureMedia::uploadedVideos());

        $missing = collect($choices)
            ->filter(fn (string $choice, string $name): bool => $choice === FeatureMedia::ANIMATION && FeatureMedia::videoPaths($name) === [])
            ->keys();

        Notification::make()
            ->title('Saved')
            ->body($missing->isEmpty()
                ? 'Your changes are live.'
                : 'Saved. These keep showing the screenshot until their animation is installed: '.$missing->map(fn (string $name): string => FeatureMedia::label($name))->implode(', ').'.')
            ->{$missing->isEmpty() ? 'success' : 'warning'}()
            ->send();
    }

    public function publicUrl(): string
    {
        return route('features.index');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('allAnimations')
                ->label('Animations everywhere')
                ->icon(Heroicon::OutlinedFilm)
                ->requiresConfirmation()
                ->modalDescription('Every screenshot that has an animation will show it. Screenshots without one keep showing the still image.')
                ->action(fn () => $this->setEverywhere(FeatureMedia::ANIMATION)),
            Action::make('allScreenshots')
                ->label('Screenshots everywhere')
                ->icon(Heroicon::OutlinedPhoto)
                ->color('gray')
                ->requiresConfirmation()
                ->action(fn () => $this->setEverywhere(FeatureMedia::SCREENSHOT)),
            Action::make('viewPage')
                ->label('View the page')
                ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                ->color('gray')
                ->url(fn (): string => $this->publicUrl())
                ->openUrlInNewTab(),
        ];
    }

    protected function setEverywhere(string $choice): void
    {
        FeatureMedia::save(
            array_fill_keys(FeatureMedia::names(), $choice),
            FeatureMedia::uploadedVideos(),
        );

        $this->fillForm();

        Notification::make()
            ->title($choice === FeatureMedia::ANIMATION ? 'Animations are on everywhere' : 'Screenshots are back everywhere')
            ->success()
            ->send();
    }

    protected function fillForm(): void
    {
        $choices = FeatureMedia::choices();
        $state = [];

        foreach (FeatureMedia::names() as $name) {
            $state['choice_'.self::key($name)] = ($choices[$name] ?? null) === FeatureMedia::ANIMATION ? FeatureMedia::ANIMATION : FeatureMedia::SCREENSHOT;
        }

        $this->form->fill($state);
    }

    protected static function key(string $name): string
    {
        return str_replace('-', '_', $name);
    }
}
