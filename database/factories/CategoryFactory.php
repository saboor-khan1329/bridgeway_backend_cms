<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

class CategoryFactory extends Factory
{
    protected $model = Category::class;

    public function definition(): array
    {
        return [
            'type' => fake()->randomElement(Category::TYPES),
            'category_type' => null,
            'name' => fake()->unique()->words(2, true),
            'parent_id' => null,
            'depth' => 0,
            'short_description' => fake()->sentence(),
            'sub_heading1' => null,
            'sub_heading_description' => null,
            'banner_title' => fake()->sentence(3),
            'banner_description' => fake()->paragraph(),
            'section_cta_heading' => null,
            'section_cta_description' => null,
            'section_cta_button_name' => null,
            'section_cta_button_url' => null,
            'is_featured' => false,
            'status' => true,
        ];
    }

    public function service(): static
    {
        return $this->state(fn () => [
            'type' => 'service',
            'category_type' => Category::SERVICE_CATEGORY_TYPES[0],
        ]);
    }

    public function location(): static
    {
        return $this->state(fn () => ['type' => 'location']);
    }

    public function sector(): static
    {
        return $this->state(fn () => [
            'type' => 'service',
            'category_type' => 'sector',
        ]);
    }

    public function blog(): static
    {
        return $this->state(fn () => ['type' => 'blog']);
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Category $category) {
            $category->slug()->create([
                'slug' => fake()->unique()->slug(),
            ]);
        });
    }
}
