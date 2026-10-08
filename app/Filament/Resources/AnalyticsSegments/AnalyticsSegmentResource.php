<?php

namespace App\Filament\Resources\AnalyticsSegments;

use App\Filament\Clusters\Analytics;
use App\Filament\Concerns\ScopedToDepartment;
use App\Filament\Resources\AnalyticsSegments\Pages\CreateAnalyticsSegment;
use App\Filament\Resources\AnalyticsSegments\Pages\EditAnalyticsSegment;
use App\Filament\Resources\AnalyticsSegments\Pages\ListAnalyticsSegments;
use App\Filament\Resources\AnalyticsSegments\Schemas\AnalyticsSegmentForm;
use App\Filament\Resources\AnalyticsSegments\Tables\AnalyticsSegmentsTable;
use App\Models\AnalyticsSegment;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * Agent 04: advertising audiences ("these cookies should also help when
 * advertising"). Five presets plus any admin-defined segment, each with a
 * live count and one-click Meta Custom Audience / Google Customer Match
 * CSV exports — hashed identifiers only, and only people who allowed
 * advertising cookies. See SegmentEvaluator / AnalyticsExporter.
 */
class AnalyticsSegmentResource extends Resource
{
    use ScopedToDepartment;

    protected static string $department = 'site';

    protected static ?string $model = AnalyticsSegment::class;

    protected static ?string $cluster = Analytics::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?string $navigationLabel = 'Ad Audiences';

    protected static ?string $modelLabel = 'audience';

    protected static ?string $pluralModelLabel = 'ad audiences';

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return AnalyticsSegmentForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AnalyticsSegmentsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAnalyticsSegments::route('/'),
            'create' => CreateAnalyticsSegment::route('/create'),
            'edit' => EditAnalyticsSegment::route('/{record}/edit'),
        ];
    }
}
