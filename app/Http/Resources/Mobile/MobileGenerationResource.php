<?php

namespace App\Http\Resources\Mobile;

use App\Models\Generation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A piece of AI content (or a video) as the phone app shows it: the text
 * as written plus a plain-text copy for "Copy" and "Share", or the video
 * address for a finished video.
 *
 * @mixin Generation
 */
class MobileGenerationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $isVideo = $this->module === 'ugc_video';
        $output = (string) $this->output;

        return [
            'id' => $this->id,
            'offer_id' => $this->offer_id,
            'offer_name' => $this->relationLoaded('offer') ? $this->offer?->product_name : null,
            'module' => $this->module,
            'status' => $this->status,
            'title' => data_get($this->output_meta, 'title') ?? data_get($this->output_meta, 'headline'),
            'text' => $isVideo || $this->status !== 'completed' ? null : $output,
            'plain_text' => $isVideo || $this->status !== 'completed' ? null : trim(html_entity_decode(strip_tags(str_replace(['</p>', '<br>', '<br/>', '<br />', '</h2>', '</h3>', '</li>'], "\n", $output)), ENT_QUOTES | ENT_HTML5)),
            'video_url' => $isVideo && $this->status === 'completed' && str_starts_with($output, 'http') ? $output : null,
            'video_style' => $isVideo ? data_get($this->input, 'quick_style') : null,
            'credits_spent' => (int) $this->credits_spent,
            'error' => $this->status === 'failed' ? $this->error_message : null,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
