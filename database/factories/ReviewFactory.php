<?php

namespace Database\Factories;

use App\Models\Review;
use Illuminate\Database\Eloquent\Factories\Factory;

class ReviewFactory extends Factory
{
    protected $model = Review::class;

    public function definition(): array
    {
        return [
            'author_name' => fake()->name(),
            'author_role' => fake()->jobTitle(),
            'company_name' => fake()->company(),
            'title' => fake()->sentence(4),
            'content' => fake()->paragraphs(2, true),
            'rating' => fake()->numberBetween(4, 5),
            'status' => true,
            'is_testimonial' => true,
        ];
    }
}
