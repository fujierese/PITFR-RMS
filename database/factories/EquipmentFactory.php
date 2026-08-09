<?php

namespace Database\Factories;

use App\Models\Equipment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Equipment>
 */
class EquipmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->word(),
            'custodian_id' => 1, // default
            'authorized_custodian_ids' => [],
            'quantity' => fake()->numberBetween(1, 10),
            'quantity_available' => fake()->numberBetween(1, 10),
        ];
    }
}
