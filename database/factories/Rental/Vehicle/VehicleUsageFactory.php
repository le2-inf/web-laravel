<?php

namespace Database\Factories\Rental\Vehicle;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Vehicle\VehicleUsage>
 */
class VehicleUsageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'vu_remark' => $this->faker->optional($weight = 0.9)->sentence($nbWords = 6, $variableNbWords = true),
        ];
    }
}
