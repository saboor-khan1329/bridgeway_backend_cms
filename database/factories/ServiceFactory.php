<?php

namespace Database\Factories;

use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

class ServiceFactory extends Factory
{
    protected $model = Service::class;

    public function definition(): array
    {
        return [
            'parent_id' => null,
            'title' => fake()->unique()->sentence(3),
            'short_description' => fake()->sentence(),
            'banner_title' => fake()->sentence(4),
            'banner_description' => fake()->paragraph(),
            'section_2_heading' => null,
            'section_2_description' => null,
            'section_2_button_name' => null,
            'section_2_button_url' => null,
            'section_3_heading' => null,
            'section_3_description' => null,
            'section_3_button_name' => null,
            'section_3_button_url' => null,
            'section_4_heading' => null,
            'section_4_description' => null,
            'section_5_heading' => null,
            'section_5_description' => null,
            'section_5_button_name' => null,
            'section_5_button_url' => null,
            'section_6_heading' => null,
            'section_6_description' => null,
            'section_6_button_name' => null,
            'section_6_button_url' => null,
            'linked_services_v1_heading' => null,
            'linked_services_v1_sub_description' => null,
            'section_7_heading' => null,
            'section_7_description' => null,
            'section_7_button_name' => null,
            'section_7_button_url' => null,
            'section_8_heading' => null,
            'section_8_description' => null,
            'section_8_button_name' => null,
            'section_8_button_url' => null,
            'linked_services_v2_heading' => null,
            'linked_services_v2_sub_description' => null,
            'related_locations_heading' => null,
            'related_locations_sub_heading' => null,
            'section_9_heading' => null,
            'section_9_description' => null,
            'section_9_button_name' => null,
            'section_9_button_url' => null,
            'related_blogs_heading' => null,
            'related_blogs_sub_heading' => null,
            'linked_services_v3_heading' => null,
            'linked_services_v3_sub_description' => null,
            'section_3_sectors_faqs' => null,
            'section_4_sectors_faqs' => null,
            'status' => true,
            'is_featured' => false,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Service $service) {
            $service->slug()->create([
                'slug' => fake()->unique()->slug(),
            ]);
        });
    }
}
