<?php

namespace Database\Factories;

use App\Models\Testimonial;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Testimonial>
 */
class TestimonialFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => null,
            'author_name' => fake()->name(),
            'author_role' => fake()->jobTitle(),
            'avatar_path' => null,
            'quote' => fake()->paragraph(2),
            'rating' => 5,
            'sort_order' => 0,
            'is_published' => true,
            'status' => Testimonial::STATUS_APPROVED,
        ];
    }

    /**
     * A customer's own submission, awaiting admin review — see
     * TestimonialController and TestimonialsTable's Approve/Decline
     * actions.
     */
    public function pending(): static
    {
        return $this->state(fn () => [
            'user_id' => User::factory(),
            'is_published' => false,
            'status' => Testimonial::STATUS_PENDING,
        ]);
    }
}
