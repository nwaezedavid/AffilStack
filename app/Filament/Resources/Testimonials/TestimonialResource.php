<?php

namespace App\Filament\Resources\Testimonials;

use App\Filament\Concerns\ScopedToDepartment;
use App\Filament\Resources\Testimonials\Pages\CreateTestimonial;
use App\Filament\Resources\Testimonials\Pages\EditTestimonial;
use App\Filament\Resources\Testimonials\Pages\ListTestimonials;
use App\Filament\Resources\Testimonials\Schemas\TestimonialForm;
use App\Filament\Resources\Testimonials\Tables\TestimonialsTable;
use App\Models\Testimonial;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * "The testimonial section will appear as soon as I have a minimum of 3
 * updated in the admin dashboard area." See Testimonial::published() for
 * that threshold and the public homepage section it feeds.
 */
class TestimonialResource extends Resource
{
    use ScopedToDepartment;

    protected static string $department = 'content';

    protected static ?string $model = Testimonial::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleBottomCenterText;

    protected static string|\UnitEnum|null $navigationGroup = 'Content';

    protected static ?string $recordTitleAttribute = 'author_name';

    /**
     * Surfaces a customer's submitted-but-unreviewed testimonials right on
     * the sidebar, so "review, edit and approve it" doesn't depend on
     * remembering to check.
     */
    public static function getNavigationBadge(): ?string
    {
        $pending = Testimonial::where('status', Testimonial::STATUS_PENDING)->count();

        return $pending > 0 ? (string) $pending : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function form(Schema $schema): Schema
    {
        return TestimonialForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TestimonialsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTestimonials::route('/'),
            'create' => CreateTestimonial::route('/create'),
            'edit' => EditTestimonial::route('/{record}/edit'),
        ];
    }
}
