<?php

namespace App\Filament\Pages\Documentation;

use App\Filament\Clusters\Documentation;
use App\Filament\Pages\BrandSettings;
use App\Filament\Pages\Documentation\Concerns\VisibleToAnyAdmin;
use App\Filament\Resources\BrandLogos\BrandLogoResource;
use App\Filament\Resources\Testimonials\TestimonialResource;
use App\Filament\Resources\Tutorials\TutorialResource;
use App\Models\Testimonial;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class ContentAndBranding extends Page
{
    use VisibleToAnyAdmin;

    protected static ?string $cluster = Documentation::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPaintBrush;

    protected static ?string $navigationLabel = 'Content & Branding';

    protected static ?string $title = 'Content & Branding';

    protected static ?int $navigationSort = 3;

    protected string $view = 'filament.pages.documentation.section-page';

    public function headerIcon(): Heroicon
    {
        return Heroicon::OutlinedPaintBrush;
    }

    public function intro(): string
    {
        return 'Everything that shapes what a visitor actually sees on the public site, from the logo to the '.
            'homepage\'s social proof to the Learning Centre.';
    }

    /**
     * @return array<int, array{icon: Heroicon, title: string, url: string|null, body: array<int, string>}>
     */
    public function sections(): array
    {
        return [
            [
                'icon' => Heroicon::OutlinedSwatch,
                'title' => 'Branding: logo, colors & homepage',
                'url' => BrandSettings::getUrl(),
                'body' => [
                    'Two separate logo uploads, not one: the rectangular/wide logo is used in the site header, the '.
                        'sign-in/sign-up pages, and the dashboard sidebar; the square/circular one is the browser '.
                        'tab icon and mobile home-screen icon. Uploading either updates every one of those places '.
                        'immediately.',
                    'The homepage hero can show an image or an embedded YouTube video, and the main site menu — '.
                        'including whether to show a Blog link — is fully editable here too (the "Affiliate '.
                        'Program" link is the one exception; see Affiliate Program for how to hide that one '.
                        'specifically).',
                ],
            ],
            [
                'icon' => Heroicon::OutlinedBuildingStorefront,
                'title' => 'Brand logos (homepage marquee)',
                'url' => BrandLogoResource::getUrl(),
                'body' => [
                    '"Add space in the admin dashboard to upload the logos of each brand I\'ve worked with, shown '.
                        'in a constantly-moving loop" — an unlimited list of logos, each with an optional link and '.
                        'a manual sort order, rendered on the homepage as a smooth, infinitely-looping scroll (it '.
                        'pauses on hover and respects a visitor\'s reduced-motion setting).',
                    'The marquee section disappears from the homepage entirely if the list is empty — there\'s no '.
                        'separate "show/hide" toggle to remember, just add or remove logos here.',
                ],
            ],
            [
                'icon' => Heroicon::OutlinedChatBubbleBottomCenterText,
                'title' => 'Testimonials (review & approve)',
                'url' => TestimonialResource::getUrl(),
                'body' => [
                    'You can write a testimonial directly here — it publishes immediately, just toggle "Is '.
                        'published" — or a customer can submit their own from Dashboard > Share a Review. A '.
                        'customer\'s submission always starts as "Pending review" and stays off the homepage until '.
                        'you look at it.',
                    'A pending row gets two one-click actions: Approve (marks it approved and publishes it in the '.
                        'same click) or Decline (hides it for good; the customer isn\'t notified either way, so '.
                        'edit their quote first if you\'d rather fix a typo and approve it than decline it). The '.
                        'sidebar badge always shows how many are waiting on you.',
                    'Whichever way a testimonial was created, the homepage section itself only appears once at '.
                        'least '.Testimonial::MINIMUM_TO_DISPLAY.' are published — a page with one or two testimonials '.
                        'feels sparse rather than reassuring, so it stays hidden until there\'s a real, credible set.',
                ],
            ],
            [
                'icon' => Heroicon::OutlinedAcademicCap,
                'title' => 'Tutorials (Learning Centre)',
                'url' => TutorialResource::getUrl(),
                'body' => [
                    '"A dedicated page for tutorials on how the platform works, visible to the public as a '.
                        'learning centre" — give a tutorial a title, optional category and description, and paste '.
                        'a YouTube link; it\'s embedded automatically (click-to-play, so the public page doesn\'t '.
                        'load every video\'s player up front) at /learn, grouped by category with an automatic '.
                        '"General" bucket for anything left uncategorized.',
                ],
            ],
        ];
    }
}
