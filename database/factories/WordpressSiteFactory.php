<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\WordpressSite;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<WordpressSite>
 */
class WordpressSiteFactory extends Factory
{
    public function definition(): array
    {
        $host = fake()->unique()->domainWord().'.example.com';

        return [
            'user_id' => User::factory(),
            'site_url' => 'https://'.$host,
            'rest_url' => 'https://'.$host.'/wp-json/',
            'name' => Str::title(fake()->words(2, true)),
            'secret' => Str::random(64),
            'status' => 'connected',
            'connected_at' => now(),
            'plugin_version' => '1.0.0',
        ];
    }

    public function pending(): static
    {
        return $this->state(fn () => ['status' => 'pending', 'connected_at' => null]);
    }
}
