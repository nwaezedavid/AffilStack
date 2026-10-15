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
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Html;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
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
                Html::make(function (Get $get) use ($name, $key): HtmlString {
                    $screenshot = e(asset('images/features/'.$name.'.webp'));
                    $uploaded = $get("video_{$key}");
                    $uploaded = is_array($uploaded) ? reset($uploaded) : $uploaded;
                    $videoPath = FeatureMedia::isUploadPath($uploaded) && Storage::disk('public')->exists($uploaded)
                        ? $uploaded
                        : FeatureMedia::bundledVideoPath($name);

                    $video = $videoPath
                        ? '<video src="'.e(Storage::disk('public')->url($videoPath)).'" poster="'.$screenshot.'" muted loop playsinline controls preload="none" style="width:100%;border-radius:8px;border:1px solid rgba(0,0,0,.1)"></video>'
                        : '<div style="display:flex;align-items:center;justify-content:center;aspect-ratio:16/10;border-radius:8px;border:1px dashed rgba(0,0,0,.2);font-size:12px;color:#6b7280;text-align:center;padding:8px">No animation installed yet</div>';

                    return new HtmlString('<div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">'
                        .'<figure style="margin:0"><img src="'.$screenshot.'" alt="" loading="lazy" style="width:100%;border-radius:8px;border:1px solid rgba(0,0,0,.1)"><figcaption style="font-size:11px;color:#6b7280;margin-top:4px">Screenshot</figcaption></figure>'
                        .'<figure style="margin:0">'.$video.'<figcaption style="font-size:11px;color:#6b7280;margin-top:4px">Animation</figcaption></figure>'
                        .'</div>');
                }),
                ToggleButtons::make("choice_{$key}")
                    ->label('Show visitors')
                    ->options([FeatureMedia::SCREENSHOT => 'Screenshot', FeatureMedia::ANIMATION => 'Animation'])
                    ->icons([FeatureMedia::SCREENSHOT => Heroicon::OutlinedPhoto, FeatureMedia::ANIMATION => Heroicon::OutlinedFilm])
                    ->default(FeatureMedia::SCREENSHOT)
                    ->inline()
                    ->required(),
                FileUpload::make("video_{$key}")
                    ->label('Your own video (optional)')
                    ->helperText('MP4 or WebM, up to 12 MB, silent. Replaces the built-in animation for this screenshot. Remove it to go back to the built-in one.')
                    ->disk('public')
                    ->directory(FeatureMedia::DIRECTORY.'/uploads')
                    ->acceptedFileTypes(['video/mp4', 'video/webm'])
                    ->maxSize(12 * 1024)
                    ->live(),
            ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $choices = [];
        $videos = [];

        foreach (FeatureMedia::names() as $name) {
            $key = self::key($name);
            $choices[$name] = (string) ($data["choice_{$key}"] ?? FeatureMedia::SCREENSHOT);
            $video = $data["video_{$key}"] ?? null;
            $videos[$name] = is_string($video) ? $video : null;
        }

        FeatureMedia::save($choices, $videos);

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
        $videos = FeatureMedia::uploadedVideos();
        $state = [];

        foreach (FeatureMedia::names() as $name) {
            $key = self::key($name);
            $state["choice_{$key}"] = ($choices[$name] ?? null) === FeatureMedia::ANIMATION ? FeatureMedia::ANIMATION : FeatureMedia::SCREENSHOT;
            $state["video_{$key}"] = $videos[$name] ?? null;
        }

        $this->form->fill($state);
    }

    protected static function key(string $name): string
    {
        return str_replace('-', '_', $name);
    }
}
