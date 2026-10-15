<?php

namespace App\Http\Controllers\Api\Blog;

use App\Http\Controllers\Controller;
use App\Services\Blog\BlogKit;
use Illuminate\Http\JsonResponse;

/**
 * GET /api/blog/kit — the logo, colours, menu, footer, call to action and
 * newsletter settings the AffilStack WordPress theme renders. Public: it
 * only repeats what affilstack.com already shows. See BlogKit.
 */
class BlogKitController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()
            ->json(BlogKit::data(), 200, [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            ->header('Cache-Control', 'public, max-age=300');
    }
}
