<?php

namespace Database\Factories;

use App\Models\Blog;
use Illuminate\Database\Eloquent\Factories\Factory;

class BlogFactory extends Factory
{
    protected $model = Blog::class;

    public function definition(): array
    {
        return [
            'title' => fake()->unique()->sentence(4),
            'short_description' => fake()->sentence(),
            'excerpt' => fake()->sentence(),
            'content' => fake()->paragraphs(5, true),
            'author' => fake()->name(),
            'published_at' => now()->subDays(fake()->numberBetween(1, 90)),
            'status' => true,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Blog $blog) {
            $blog->slug()->create([
                'slug' => fake()->unique()->slug(),
            ]);
        });
    }
}
