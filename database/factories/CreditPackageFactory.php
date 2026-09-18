<?php

namespace Database\Factories;

use App\Models\CreditPackage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CreditPackage>
 */
class CreditPackageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => ucfirst(fake()->unique()->word()).' pack',
            'description' => fake()->sentence(),
            'credits' => 200,
            'price_cents' => 1900,
            'currency' => 'USD',
            'is_featured' => false,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
