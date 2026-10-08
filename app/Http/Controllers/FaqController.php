<?php

namespace App\Http\Controllers;

use App\Models\FaqItem;
use Illuminate\View\View;

/**
 * The dedicated public FAQ page (/faq) — every published FaqItem, grouped
 * by category, with FAQPage structured data. The Help Centre (/help) links
 * here instead of repeating the questions.
 */
class FaqController extends Controller
{
    public function index(): View
    {
        return view('marketing.faq', ['groups' => FaqItem::publishedGrouped()]);
    }
}
