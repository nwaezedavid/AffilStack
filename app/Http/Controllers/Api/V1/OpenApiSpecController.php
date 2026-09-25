<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;

/**
 * API roadmap item #4 — serves the hand-authored spec at
 * resources/openapi/openapi.yaml (kept in sync with routes/api.php by
 * hand, the same way this app's other config-as-documentation files are:
 * there is no generator in this stack that introspects routes/validation
 * rules into an accurate spec on its own). Public and unauthenticated on
 * purpose — see routes/api.php's own comment on why.
 */
class OpenApiSpecController extends Controller
{
    public function show(): Response
    {
        $yaml = file_get_contents(resource_path('openapi/openapi.yaml'));

        return response($yaml, 200, [
            'Content-Type' => 'application/yaml; charset=utf-8',
            'Content-Disposition' => 'inline; filename="affilstack-openapi.yaml"',
        ]);
    }
}
