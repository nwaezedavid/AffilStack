<?php

namespace App\Http\Controllers;

use App\Models\Tutorial;
use Illuminate\View\View;

/**
 * The public Learning Centre — "a dedicated page for tutorials on how the
 * platform works ... visible to the public." See Tutorial::publishedGrouped()
 * for the category-grouped, cached read this renders.
 */
class TutorialController extends Controller
{
    public function index(): View
    {
        $groups = Tutorial::publishedGrouped();

        return view('marketing.tutorials', compact('groups'));
    }
}
