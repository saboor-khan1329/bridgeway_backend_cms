<?php

namespace Database\Factories;

use App\Models\Location;
use Illuminate\Database\Eloquent\Factories\Factory;

class LocationFactory extends Factory
{
    protected $model = Location::class;

    public function definition(): array
    {
        return [
            'parent_id' => null,
            'title' => fake()->unique()->city(),
            'sub_heading' => null,
            'short_description' => fake()->sentence(),
            'description' => fake()->paragraphs(3, true),
            'banner_title' => fake()->sentence(4),
            'banner_description' => fake()->paragraph(),
            'section_2_heading' => null,
            'section_2_description' => null,
            'section_2_button_name' => null,
            'section_2_button_url' => null,
            'linked_services_v1_heading' => null,
            'linked_services_v1_sub_description' => null,
            'section_3_locations_faqs' => null,
            'section_4_locations_faqs' => null,
            'section_5_heading' => null,
            'section_5_description' => null,
            'section_6_heading' => null,
            'section_6_description' => null,
            'linked_child_locations_heading' => null,
            'linked_child_locations_sub_description' => null,
            'section_7_heading' => null,
            'section_7_description' => null,
            'section_8_heading' => null,
            'section_8_description' => null,
            'linked_services_v2_heading' => null,
            'linked_services_v2_sub_description' => null,
            'related_blogs_heading' => null,
            'related_blogs_sub_heading' => null,
            'linked_services_v3_heading' => null,
            'linked_services_v3_sub_description' => null,
            'linked_services_v4_heading' => null,
            'linked_services_v4_sub_description' => null,
            'section_9_map_src' => null,
            'status' => true,
            'is_featured' => false,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Location $location) {
            $location->slug()->create([
                'slug' => fake()->unique()->slug(),
            ]);
        });
    }
}
