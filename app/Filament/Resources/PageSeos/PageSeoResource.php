<?php

namespace App\Filament\Resources\PageSeos;

use App\Filament\Concerns\ScopedToDepartment;
use App\Filament\Resources\PageSeos\Pages\EditPageSeo;
use App\Filament\Resources\PageSeos\Pages\ListPageSeos;
use App\Filament\Resources\PageSeos\Schemas\PageSeoForm;
use App\Filament\Resources\PageSeos\Tables\PageSeosTable;
use App\Models\PageSeo;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * SEO > Page SEO — one list of every public page (the route-based pages
 * stored in page_seo, plus every Site Page) with its SEO score, and the
 * editor for the route-based ones. Site Pages open in their own Content >
 * Site Pages editor, which carries the same SEO fields. Rows are created
 * automatically from PublicPages — there is nothing to "create".
 */
class PageSeoResource extends Resource
{
    use ScopedToDepartment;

    protected static string $department = 'site';

    protected static ?string $model = PageSeo::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentMagnifyingGlass;

    protected static string|\UnitEnum|null $navigationGroup = 'SEO';

    protected static ?string $navigationLabel = 'Page SEO';

    protected static ?string $modelLabel = 'page SEO';

    protected static ?string $pluralModelLabel = 'Page SEO';

    protected static ?string $slug = 'page-seo';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return PageSeoForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PageSeosTable::configure($table);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPageSeos::route('/'),
            'edit' => EditPageSeo::route('/{record}/edit'),
        ];
    }
}
