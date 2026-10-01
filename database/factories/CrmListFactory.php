<?php

namespace Database\Factories;

use App\Models\CrmList;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CrmList>
 */
class CrmListFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'offer_id' => null,
            'name' => fake()->words(2, true).' prospects',
            'color' => 'brand',
        ];
    }
}
