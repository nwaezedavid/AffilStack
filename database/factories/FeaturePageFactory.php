<?php

namespace Database\Factories;

use App\Models\FeaturePage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FeaturePage>
 */
class FeaturePageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = ucfirst(fake()->unique()->words(2, true));

        return [
            'slug' => str($name)->slug()->toString(),
            'name' => $name,
            'icon' => 'sparkles',
            'menu_group' => 'grow',
            'menu_description' => fake()->sentence(6),
            'hero_headline' => fake()->sentence(6),
            'hero_subheadline' => fake()->sentence(16),
            'hero_default_image' => 'offer-research.webp',
            'sections' => [
                ['heading' => fake()->sentence(4), 'body' => fake()->paragraph(), 'bullets' => [fake()->sentence(4)], 'default_image' => 'offer-research.webp', 'image_alt' => 'Screenshot'],
            ],
            'steps' => [['title' => 'Step one', 'description' => fake()->sentence()]],
            'faqs' => [['question' => fake()->sentence().'?', 'answer' => fake()->paragraph()]],
            'sort_order' => 0,
            'show_in_menu' => true,
            'is_published' => true,
        ];
    }

    public function unpublished(): static
    {
        return $this->state(['is_published' => false]);
    }
}
