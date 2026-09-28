<?php

namespace Database\Factories;

use App\Models\Page;
use Illuminate\Database\Eloquent\Factories\Factory;

class PageFactory extends Factory
{
    protected $model = Page::class;

    public function definition(): array
    {
        return [
            'page_title' => fake()->unique()->sentence(3),
            'page_type' => null,
            'template_name' => Page::TEMPLATE_STATIC_V1,
            'banner_title' => fake()->sentence(4),
            'banner_description' => fake()->paragraph(),
            'banner_short_description' => fake()->sentence(),
            'button1_name' => 'Learn More',
            'button1_link' => '/contact',
            'button2_name' => 'Get Quote',
            'button2_link' => '/quote',
            'page_content' => fake()->paragraphs(3, true),
            'status' => true,
            'last_updated_at' => now(),
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Page $page) {
            $page->slug()->create([
                'slug' => fake()->unique()->slug(),
            ]);
        });
    }

    public function serviceType(): static
    {
        return $this->state(fn () => ['page_type' => 'service']);
    }

    public function sectorType(): static
    {
        return $this->state(fn () => ['page_type' => 'sector']);
    }
}
