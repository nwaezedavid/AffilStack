<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\SwipeFileEntry;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SwipeFileController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->only(['type', 'niche', 'q']);

        $entries = SwipeFileEntry::query()
            ->where('is_published', true)
            ->when($filters['type'] ?? null, fn ($q, $type) => $q->where('type', $type))
            ->when($filters['niche'] ?? null, fn ($q, $niche) => $q->where('niche', $niche))
            ->when($filters['q'] ?? null, fn ($q, $search) => $q->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('content', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%");
            }))
            ->orderBy('niche')
            ->orderBy('sort_order')
            ->paginate(24)
            ->withQueryString();

        return view('dashboard.swipe-files.index', compact('entries', 'filters'));
    }
}
