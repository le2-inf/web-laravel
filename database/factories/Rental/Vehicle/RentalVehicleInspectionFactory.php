<?php

namespace Database\Factories\Rental\Vehicle;

use App\Enum\Exist;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Rental\Vehicle\RentalVehicleInspection>
 */
class RentalVehicleInspectionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'inspection_type'       => '',
            'policy_copy'           => Exist::label_key_random(),
            'driving_license'       => Exist::label_key_random(),
            'operation_license'     => Exist::label_key_random(),
            'vehicle_damage_status' => Exist::label_key_random(),
            'inspection_datetime'   => fake_current_period_dt(),
            'vi_mileage'            => $this->faker->numberBetween(5000, 200000),
            'damage_deduction'      => $this->faker->optional()->randomFloat(2, 0, 1000),
            'vi_remark'             => $this->faker->optional()->text(200),
            'add_should_pay'        => $this->faker->boolean() ? 1 : 0,
            'additional_photos'     => fake_many_photos(),
            'inspection_info'       => [['info_photos' => fake_many_photos(), 'description' => $this->faker->optional()->sentence()]],
        ];
    }
}
