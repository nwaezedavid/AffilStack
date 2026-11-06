<?php

namespace App\Filament\Resources\MobileDevices;

use App\Filament\Concerns\ScopedToDepartment;
use App\Filament\Resources\MobileDevices\Pages\ListMobileDevices;
use App\Filament\Resources\MobileDevices\Tables\MobileDevicesTable;
use App\Models\MobileDevice;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * Every phone signed in to the AffilStack app: who, which app version,
 * whether it accepts notifications, when it was last used — with a test
 * notification and "Sign out" for support. Read-only otherwise; phones are
 * added by signing in from the app (see MobileAuthServer).
 */
class MobileDeviceResource extends Resource
{
    use ScopedToDepartment;

    protected static string $department = 'system';

    protected static ?string $model = MobileDevice::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDevicePhoneMobile;

    protected static string|\UnitEnum|null $navigationGroup = 'System';

    protected static ?string $navigationLabel = 'App Devices';

    protected static ?string $modelLabel = 'app device';

    protected static ?string $recordTitleAttribute = 'name';

    public static function table(Table $table): Table
    {
        return MobileDevicesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMobileDevices::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
