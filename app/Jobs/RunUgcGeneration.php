<?php

namespace App\Jobs;

use App\Models\Generation;
use App\Notifications\GenerationCompleted;
use App\Services\Modules\UgcService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RunUgcGeneration implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(public Generation $generation, public string $method) {}

    public function handle(UgcService $service): void
    {
        $service->{$this->method}($this->generation);

        $this->generation->refresh();

        $this->generation->user->notify(new GenerationCompleted(
            module: $this->generation->module,
            title: $this->generation->offer->product_name,
            success: $this->generation->status === 'completed',
            url: route('offers.show', $this->generation->offer_id),
            errorMessage: $this->generation->error_message,
        ));
    }
}
