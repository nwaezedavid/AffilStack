<?php

namespace App\Http\Controllers\Api\V1\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Resources\Mobile\MobileGenerationResource;
use App\Models\Generation;
use Illuminate\Http\Request;

/**
 * One piece of content or video — the app polls this while it's being
 * written or rendered.
 */
class GenerationsController extends Controller
{
    public function show(Request $request, Generation $generation): MobileGenerationResource
    {
        abort_unless($generation->isAccessibleBy($request->user()), 404);

        return new MobileGenerationResource($generation->load('offer:id,product_name'));
    }
}
