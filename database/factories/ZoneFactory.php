<?php

namespace Database\Factories;

use App\Models\Expansion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Zone>
 */
class ZoneFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'short_name' => fake()->unique()->lexify('zone??????'),
            'eq_zone_id' => fake()->numberBetween(1, 999),
            'name' => fake()->unique()->words(3, true),
            'expansion_id' => fn () => Expansion::firstOrCreate(['code' => 'classic'], ['name' => 'Classic', 'year' => 1999, 'sort_order' => 1])->id,
        ];
    }
}
