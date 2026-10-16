<?php

namespace App\Jobs;

use App\Models\Generation;
use App\Notifications\GenerationCompleted;
use App\Services\Modules\BlogArticleService;
use App\Services\WordPress\WordPressDispatcher;
use App\Support\OfferFeatures;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RunBlogArticle implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    /**
     * Above OpenAI's own 90s request timeout (config/ai.php) so the worker
     * never kills a generation mid-call and leaves it stuck "processing".
     */
    public int $timeout = 180;

    public function __construct(public Generation $generation) {}

    public function handle(BlogArticleService $service, WordPressDispatcher $wordpress): void
    {
        $service->generate($this->generation);

        $this->generation->refresh();

        // Customers who turned on "Send new articles automatically" get the
        // finished article on their WordPress site as a draft.
        if ($this->generation->status === 'completed') {
            try {
                $wordpress->autoSend($this->generation);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        $this->generation->user->notify(new GenerationCompleted(
            module: 'blog_article',
            title: $this->generation->offer->product_name,
            success: $this->generation->status === 'completed',
            url: OfferFeatures::urlForGeneration($this->generation),
            errorMessage: $this->generation->error_message,
        ));
    }
}
