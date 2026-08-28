<?php

namespace App\Http\Controllers;

use App\Models\FaqItem;
use Illuminate\View\View;

class HelpController extends Controller
{
    public function index(): View
    {
        $groups = FaqItem::where('is_published', true)
            ->orderBy('sort_order')
            ->get()
            ->groupBy('category');

        return view('marketing.help', compact('groups'));
    }
}
