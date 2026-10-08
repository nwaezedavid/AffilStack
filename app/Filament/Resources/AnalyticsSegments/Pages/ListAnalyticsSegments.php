<?php

namespace App\Filament\Resources\AnalyticsSegments\Pages;

use App\Filament\Resources\AnalyticsSegments\AnalyticsSegmentResource;
use App\Services\Analytics\SegmentEvaluator;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\HtmlString;

class ListAnalyticsSegments extends ListRecords
{
    protected static string $resource = AnalyticsSegmentResource::class;

    protected ?string $subheading = 'Groups of people to show ads to — or to exclude from them. Counts are live. Downloads contain only SHA-256 hashed emails/phones of people who allowed advertising cookies, formatted for Meta Custom Audiences and Google Customer Match.';

    public function mount(): void
    {
        SegmentEvaluator::ensurePresets();

        parent::mount();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('howTo')
                ->label('How to upload')
                ->icon('heroicon-o-question-mark-circle')
                ->color('gray')
                ->modalHeading('Using these audiences in your ads')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Got it')
                ->modalContent(new HtmlString(
                    '<div style="font-size:.875rem; line-height:1.6; display:flex; flex-direction:column; gap:.9rem;">'
                    .'<p><strong>Meta (Facebook &amp; Instagram):</strong> Ads Manager → Audiences → Create audience → Custom audience → Customer list → upload the "Download for Meta" file. Columns (email, phone, country) are already normalised and SHA-256 hashed, so Meta matches them without ever seeing the raw data.</p>'
                    .'<p><strong>Google Ads:</strong> Tools → Shared library → Audience manager → + → Customer list → "Upload hashed data" → upload the "Download for Google Ads" file (Email, Phone columns, hashed the way Customer Match requires — gmail dots removed, phone in +E.164).</p>'
                    .'<p><strong>Exclusion lists</strong> (e.g. Customers): add them under "Exclude" in your acquisition ad sets so you stop paying to reach people who already bought.</p>'
                    .'<p><strong>Anonymous visitors</strong> can\'t be uploaded (we don\'t know who they are). To retarget them, use the Meta/TikTok pixel or Google tag events — they fire for visitors who allowed advertising cookies.</p>'
                    .'<p><strong>Keep lists fresh:</strong> re-download and replace the list weekly; people who withdraw consent drop out automatically.</p>'
                    .'</div>'
                )),
            CreateAction::make()->label('New audience'),
        ];
    }
}
