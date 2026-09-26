<?php

namespace App\Jobs;

use App\Models\Generation;
use App\Notifications\GenerationCompleted;
use App\Services\Modules\BlogArticleService;
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

    public function handle(BlogArticleService $service): void
    {
        $service->generate($this->generation);

        $this->generation->refresh();

        $this->generation->user->notify(new GenerationCompleted(
            module: 'blog_article',
            title: $this->generation->offer->product_name,
            success: $this->generation->status === 'completed',
            url: route('offers.show', $this->generation->offer_id),
            errorMessage: $this->generation->error_message,
        ));
    }
}
