<?php

return [
    // Which provider implementation to bind. Swap by changing this value and
    // adding a new implementation of App\Services\AI\AIProvider.
    'provider' => env('AI_PROVIDER', 'openai'),

    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
        'text_model' => env('OPENAI_TEXT_MODEL', 'gpt-4o-mini'),
        'image_model' => env('OPENAI_IMAGE_MODEL', 'dall-e-3'),
        'timeout' => env('OPENAI_TIMEOUT', 90),
    ],
];
