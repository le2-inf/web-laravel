<?php

namespace Database\Factories\Vehicle;

use App\Enum\Vehicle\VmvStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Vehicle\VehicleManualViolation>
 */
class VehicleManualViolationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'violation_datetime' => fake_current_period_dt(),
            'violation_content'  => $this->faker->sentence($nbWords = 6, $variableNbWords = true),
            'location'           => $this->faker->streetAddress,
            'fine_amount'        => $this->faker->randomFloat(2, 50, 1000),
            'penalty_points'     => $this->faker->numberBetween(1, 12),
            'status'             => VmvStatus::label_key_random(),
            'vmv_remark'         => $this->faker->text,
        ];
    }
}
