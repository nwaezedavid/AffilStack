<?php

namespace App\Http\Resources\Mobile;

use App\Models\Offer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A product (offer) as the phone app shows it. The research fields are
 * only sent on the detail screen (withResearch()).
 *
 * @mixin Offer
 */
class MobileOfferResource extends JsonResource
{
    protected bool $withResearch = false;

    public function withResearch(): static
    {
        $this->withResearch = true;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_name' => $this->product_name,
            'product_url' => $this->product_url,
            'affiliate_network' => $this->affiliate_network,
            'status' => $this->status,
            'created_at' => $this->created_at?->toIso8601String(),
            'generations_count' => $this->whenCounted('generations'),
            'research' => $this->when($this->withResearch, fn (): array => [
                'ideal_customer' => $this->ideal_customer_summary,
                'where_to_find' => $this->where_to_find,
                'recommended_channel' => $this->recommended_channel,
                'recommended_angle' => $this->recommended_angle,
            ]),
        ];
    }
}
