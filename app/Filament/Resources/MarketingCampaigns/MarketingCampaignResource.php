<?php

namespace App\Filament\Resources\MarketingCampaigns;

use App\Filament\Concerns\ScopedToDepartment;
use App\Filament\Resources\MarketingCampaigns\Pages\ListMarketingCampaigns;
use App\Filament\Resources\MarketingCampaigns\Tables\MarketingCampaignsTable;
use App\Models\MarketingCampaign;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * Brain's (the Marketing Agent's) drafted and running ad campaigns — see
 * BrainAgentService. Drafting and one-click launch both happen from this
 * table (ListMarketingCampaigns' header action, and the row actions in
 * MarketingCampaignsTable), so no code is needed to run a campaign: connect
 * Brain in AI Agents > Brain Settings first, then draft, review, and
 * approve from here.
 */
class MarketingCampaignResource extends Resource
{
    use ScopedToDepartment;

    protected static string $department = 'ai_agents';

    protected static ?string $model = MarketingCampaign::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMegaphone;

    protected static string|\UnitEnum|null $navigationGroup = 'AI Agents';

    protected static ?string $navigationLabel = 'Marketing Campaigns';

    protected static ?string $recordTitleAttribute = 'title';

    public static function table(Table $table): Table
    {
        return MarketingCampaignsTable::configure($table);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMarketingCampaigns::route('/'),
        ];
    }
}
