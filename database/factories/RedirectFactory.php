<?php

namespace Database\Factories;

use App\Models\Redirect;
use Illuminate\Database\Eloquent\Factories\Factory;

class RedirectFactory extends Factory
{
    protected $model = Redirect::class;

    public function definition(): array
    {
        return [
            'from_url' => '/' . fake()->slug(2),
            'to_url' => '/' . fake()->slug(3),
            'status_code' => fake()->randomElement([301, 302, 307, 308]),
            'status' => true,
            'sourceable_type' => null,
            'sourceable_id' => null,
            'is_auto_generated' => false,
        ];
    }
}
