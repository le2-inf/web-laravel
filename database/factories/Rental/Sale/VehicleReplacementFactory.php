<?php

namespace Database\Factories\Rental\Sale;

use App\Enum\Rental\VrReplacementStatus;
use App\Enum\Rental\VrReplacementType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Sale\VehicleReplacement>
 */
class VehicleReplacementFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'replacement_type'       => $replacement_type = VrReplacementType::label_key_random(),
            'replacement_date'       => VrReplacementType::PERMANENT === $replacement_type ? fake_current_period_d() : null,
            'replacement_start_date' => VrReplacementType::TEMPORARY === $replacement_type ? fake_current_period_d() : null,
            'replacement_end_date'   => VrReplacementType::TEMPORARY === $replacement_type ? fake_current_period_d() : null,
            'replacement_status'     => VrReplacementStatus::label_key_random(),
            'additional_photos'      => fake_many_photos(),
            'vr_remark'              => $this->faker->optional()->text(200),
        ];
    }
}
