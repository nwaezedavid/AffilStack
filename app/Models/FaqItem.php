<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

#[Fillable(['question', 'answer', 'category', 'sort_order', 'is_published'])]
class FaqItem extends Model
{
    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
        ];
    }

    /**
     * Audit item #7 (caching/performance) — the public /help page (and the
     * AI support chat's own priming, see SupportChatService) both grouped
     * every published FAQ by category on every request; these barely ever
     * change, so this caches the grouped result indefinitely and is busted
     * from booted() below on any FaqItem save/delete.
     *
     * Caches a flat list of plain attribute arrays, never Eloquent models —
     * see Plan::activePublicList() for why: a persistent cache store can
     * hand back an unusable __PHP_Incomplete_Class for a serialized Model
     * on the next request, where raw arrays always round-trip safely.
     * Grouping happens after rehydrating, on every read — cheap in-memory
     * work compared to the query it's replacing.
     *
     * @return Collection<string, \Illuminate\Database\Eloquent\Collection<int, self>>
     */
    public static function publishedGrouped(): Collection
    {
        $rows = Cache::rememberForever('faq_items:published_grouped', function () {
            return static::where('is_published', true)->orderBy('sort_order')->get()
                ->map(fn (self $item) => $item->getAttributes())->all();
        });

        return static::hydrate($rows)->groupBy('category');
    }

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget('faq_items:published_grouped'));
        static::deleted(fn () => Cache::forget('faq_items:published_grouped'));
    }
}
