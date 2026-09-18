<?php

namespace App\Filament\Resources\Testimonials\Pages;

use App\Filament\Resources\Testimonials\TestimonialResource;
use App\Models\Testimonial;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;

class ListTestimonials extends ListRecords
{
    protected static string $resource = TestimonialResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    /**
     * "The testimonial section will appear as soon as I have a minimum of
     * 3 updated" — surfaced here so it's obvious why the homepage isn't
     * showing testimonials yet, instead of leaving that threshold silent.
     */
    public function getSubheading(): string|Htmlable|null
    {
        $publishedCount = Testimonial::where('is_published', true)->count();
        $pendingCount = Testimonial::where('status', Testimonial::STATUS_PENDING)->count();

        $status = $publishedCount >= Testimonial::MINIMUM_TO_DISPLAY
            ? "Showing on the homepage — {$publishedCount} published."
            : 'Hidden from the homepage until '.(Testimonial::MINIMUM_TO_DISPLAY - $publishedCount).' more testimonial(s) are published (minimum '.Testimonial::MINIMUM_TO_DISPLAY.').';

        if ($pendingCount > 0) {
            $status .= " {$pendingCount} customer submission(s) awaiting your review.";
        }

        return $status;
    }
}
