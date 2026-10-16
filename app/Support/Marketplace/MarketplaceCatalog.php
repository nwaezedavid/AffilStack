<?php

namespace App\Support\Marketplace;

use App\Models\Addon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * The approved add-ons customers can browse, filtered by search, type and
 * category — shared by the public marketplace and the dashboard.
 */
class MarketplaceCatalog
{
    /**
     * @return LengthAwarePaginator<int, Addon>
     */
    public static function browse(Request $request, int $perPage = 24): LengthAwarePaginator
    {
        $search = trim((string) $request->query('q', ''));
        $type = (string) $request->query('type', '');
        $category = (string) $request->query('category', '');

        return Addon::query()
            ->listed()
            ->with(['liveVersion', 'developer'])
            ->when(array_key_exists($type, config('marketplace.types')), fn (Builder $query) => $query->where('type', $type))
            ->when(array_key_exists($category, config('marketplace.categories')), fn (Builder $query) => $query->whereHas('liveVersion', fn (Builder $version) => $version->where('category', $category)))
            ->when($search !== '', fn (Builder $query) => $query->whereHas('liveVersion', fn (Builder $version) => $version->where(function (Builder $match) use ($search) {
                $like = '%'.str_replace(['%', '_'], ['\%', '\_'], mb_substr($search, 0, 80)).'%';
                $match->where('name', 'like', $like)->orWhere('tagline', 'like', $like);
            })))
            ->orderByDesc('is_featured')
            ->orderByDesc('installs_count')
            ->orderByDesc('published_at')
            ->paginate($perPage)
            ->withQueryString();
    }
}
