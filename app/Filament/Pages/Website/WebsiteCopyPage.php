<?php

namespace App\Filament\Pages\Website;

use App\Filament\Concerns\ScopedToDepartment;
use App\Support\Website\SiteCopy;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * Base for the Website → … copy editors (Features overview, FAQ page, Help
 * Centre, Pricing page): every field is a SiteCopy key, pre-filled with the
 * words that are live right now; text identical to the built-in default is
 * stored blank so it keeps following the shipped copy.
 */
abstract class WebsiteCopyPage extends Page
{
    use ScopedToDepartment;

    protected static string $department = 'content';

    protected static string|\UnitEnum|null $navigationGroup = 'Website';

    protected string $view = 'filament.pages.website-copy-page';

    /**
     * @var array<string, mixed>
     */
    public ?array $data = [];

    /**
     * @return array<int, string> SiteCopy keys edited here
     */
    abstract protected static function keys(): array;

    /**
     * @return array<int, Component>
     */
    abstract protected function fields(): array;

    abstract public function publicUrl(): string;

    public function mount(): void
    {
        $values = [];

        foreach (static::keys() as $key) {
            $values[$key] = SiteCopy::text($key);
        }

        $this->form->fill($values);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components($this->fields());
    }

    public function save(): void
    {
        $data = $this->form->getState();

        SiteCopy::save(array_intersect_key($data, array_flip(static::keys())), SiteCopy::defaults());

        Notification::make()->title('Saved')->body('Your changes are live.')->success()->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('viewPage')
                ->label('View the page')
                ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                ->color('gray')
                ->url(fn (): string => $this->publicUrl())
                ->openUrlInNewTab(),
        ];
    }
}
