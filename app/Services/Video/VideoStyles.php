<?php

namespace App\Services\Video;

use InvalidArgumentException;

/**
 * The one-click video styles customers choose from. Each style names the
 * engine that renders it: HeyGen for an AI presenter speaking to camera,
 * Higgsfield for short clips with the product itself on screen. Credit
 * prices, lengths and on/off switches are set per style in the admin
 * (VideoStudioSetting::style()); these are the defaults.
 */
class VideoStyles
{
    public const HEYGEN = 'heygen';

    public const HIGGSFIELD = 'higgsfield';

    /**
     * @var array<string, array{label: string, description: string, provider: string, icon: string, credits: int, duration: int}>
     */
    public const STYLES = [
        'talking_review' => [
            'label' => 'Talking review',
            'description' => 'An AI presenter tells viewers why they love the product, like a real creator review.',
            'provider' => self::HEYGEN,
            'icon' => 'user-circle',
            'credits' => 110,
            'duration' => 50,
        ],
        'product_showcase' => [
            'label' => 'Product showcase',
            'description' => 'A short, eye-catching clip with your product on screen and a voiceover selling it.',
            'provider' => self::HIGGSFIELD,
            'icon' => 'sparkles',
            'credits' => 120,
            'duration' => 10,
        ],
        'problem_solution' => [
            'label' => 'Problem → solution',
            'description' => 'Opens on a relatable problem, then reveals your product as the fix. Great for ads.',
            'provider' => self::HIGGSFIELD,
            'icon' => 'light-bulb',
            'credits' => 150,
            'duration' => 15,
        ],
    ];

    /**
     * @return array{label: string, description: string, provider: string, icon: string, credits: int, duration: int}
     */
    public static function get(string $key): array
    {
        if (! isset(self::STYLES[$key])) {
            throw new InvalidArgumentException("Unknown video style [{$key}].");
        }

        return self::STYLES[$key];
    }

    public static function exists(string $key): bool
    {
        return isset(self::STYLES[$key]);
    }

    public static function usesHiggsfield(string $key): bool
    {
        return self::exists($key) && self::STYLES[$key]['provider'] === self::HIGGSFIELD;
    }
}
