<?php

return [
    // Which provider implementation to bind. Swap by changing this value and
    // adding a new implementation of App\Services\AI\AIProvider.
    'provider' => env('AI_PROVIDER', 'openai'),

    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
        'text_model' => env('OPENAI_TEXT_MODEL', 'gpt-4o-mini'),
        // dall-e-3 was deprecated 2025-11-14 and shut down 2026-05-12 — a
        // fresh deployment using the old default would have every image
        // generation call fail outright. gpt-image-2 is OpenAI's current,
        // non-deprecated image model as of when this was last checked
        // (developers.openai.com/api/docs/pricing, Sept 2026); see
        // OpenAIProvider::generateImage(), which — unlike dall-e — reads a
        // base64 response rather than a url.
        'image_model' => env('OPENAI_IMAGE_MODEL', 'gpt-image-2'),
        'timeout' => env('OPENAI_TIMEOUT', 90),
    ],
];
