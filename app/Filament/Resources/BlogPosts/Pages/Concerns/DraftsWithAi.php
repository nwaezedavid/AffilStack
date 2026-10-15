<?php

namespace App\Filament\Resources\BlogPosts\Pages\Concerns;

use App\Services\AI\AIGenerationException;
use App\Services\Blog\BlogPostWriter;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The "Draft with AI" header action shared by the create and edit pages:
 * fills the form with an AI-written article to review before sending.
 */
trait DraftsWithAi
{
    protected function draftWithAiAction(): Action
    {
        return Action::make('draftWithAi')
            ->label('Draft with AI')
            ->icon(Heroicon::OutlinedSparkles)
            ->color('gray')
            ->modalDescription('Writes a full article into the form (replacing what\'s there). Nothing is sent to the blog until you choose to.')
            ->schema([
                TextInput::make('topic')->label('What should the post be about?')->required()->maxLength(300)
                    ->placeholder('e.g. How to find local businesses that need web hosting'),
                TextInput::make('keyword')->label('Focus keyword (optional)')->maxLength(120),
                Textarea::make('notes')->label('Anything to include? (optional)')->rows(3)->maxLength(1000),
            ])
            ->action(function (array $data): void {
                try {
                    $draft = app(BlogPostWriter::class)->draft($data['topic'], $data['keyword'] ?? null, $data['notes'] ?? null);
                } catch (AIGenerationException $e) {
                    Notification::make()->title('Couldn\'t write the draft')->body($e->getMessage())->danger()->send();

                    return;
                } catch (Throwable $e) {
                    Log::error('Blog post AI draft failed', ['error' => $e->getMessage()]);
                    Notification::make()->title('Couldn\'t write the draft')->body('Something went wrong — please try again.')->danger()->send();

                    return;
                }

                $this->form->fill(array_merge($this->form->getRawState(), $draft));

                Notification::make()->title('Draft ready — review it, then save')->success()->send();
            });
    }
}
