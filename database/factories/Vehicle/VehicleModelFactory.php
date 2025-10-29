<?php

namespace Database\Factories\Vehicle;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Vehicle\VehicleModel>
 */
class VehicleModelFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'vm_id'      => $this->incTablePKValue(),
            'brand_name' => $brand_name,
            'model_name' => $this->faker->word(),
        ];
    }
}
