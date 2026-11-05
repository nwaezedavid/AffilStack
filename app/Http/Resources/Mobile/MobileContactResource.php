<?php

namespace App\Http\Resources\Mobile;

use App\Models\CrmContact;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A CRM contact as the phone app shows it (tap-to-email, call, WhatsApp).
 *
 * @mixin CrmContact
 */
class MobileContactResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'company' => $this->company,
            'title' => $this->title,
            'email' => $this->email,
            'phone' => $this->phone,
            'website' => $this->website,
            'location' => $this->location,
            'status' => $this->status,
            'tags' => array_values(array_filter(array_map('trim', is_array($this->tags) ? $this->tags : explode(',', (string) $this->tags)))),
            'notes' => $this->notes,
            'lead_score' => $this->lead_score,
            'source' => $this->source,
            'lists' => $this->whenLoaded('lists', fn () => $this->lists->map(fn ($list): array => ['id' => $list->id, 'name' => $list->name])->values()),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
