<?php

namespace App\Filament\Resources\BlogPosts\Pages;

use App\Filament\Resources\BlogPosts\BlogPostResource;
use App\Filament\Resources\BlogPosts\Pages\Concerns\DraftsWithAi;
use App\Models\BlogPost;
use App\Services\Blog\BlogFeed;
use App\Services\Blog\WordPressPublisher;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Throwable;

class EditBlogPost extends EditRecord
{
    use DraftsWithAi;

    protected static string $resource = BlogPostResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('sendDraft')
                ->label(fn (): string => $this->post()->isOnWordPress() ? 'Update on the blog (as draft)' : 'Send to the blog as a draft')
                ->icon(Heroicon::OutlinedPaperAirplane)
                ->color('gray')
                ->action(fn () => $this->push('draft')),
            Action::make('publish')
                ->label(fn (): string => $this->post()->wp_status === 'publish' ? 'Update the live post' : 'Publish on the blog')
                ->icon(Heroicon::OutlinedGlobeAlt)
                ->requiresConfirmation()
                ->modalDescription('Saves your changes and makes the post live on the blog.')
                ->action(fn () => $this->push('publish')),
            Action::make('viewOnBlog')
                ->label('View on the blog')
                ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                ->color('gray')
                ->url(fn (): ?string => $this->post()->wp_url)
                ->openUrlInNewTab()
                ->visible(fn (): bool => filled($this->post()->wp_url)),
            ActionGroup::make([
                $this->draftWithAiAction(),
                DeleteAction::make()->modalDescription('Deletes it here only. A copy already sent to the blog stays there — delete it in WordPress if needed.'),
            ]),
        ];
    }

    protected function post(): BlogPost
    {
        /** @var BlogPost */
        return $this->getRecord();
    }

    protected function push(string $status): void
    {
        $this->save(shouldRedirect: false, shouldSendSavedNotification: false);

        if (! WordPressPublisher::isConfigured()) {
            Notification::make()
                ->title('Connect the blog first')
                ->body('Add the blog address, WordPress username and application password in Website → Blog settings.')
                ->warning()
                ->send();

            return;
        }

        try {
            $post = app(WordPressPublisher::class)->push($this->post(), $status);
            BlogFeed::flush();

            Notification::make()
                ->title($status === 'publish' ? 'Published on the blog' : 'Saved on the blog as a draft')
                ->body($post->wp_url)
                ->success()
                ->send();
        } catch (Throwable $e) {
            Notification::make()->title('The blog didn\'t accept it')->body($e->getMessage())->danger()->persistent()->send();
        }

        $this->refreshFormData(['wp_status', 'wp_url', 'synced_at', 'last_error']);
    }
}
