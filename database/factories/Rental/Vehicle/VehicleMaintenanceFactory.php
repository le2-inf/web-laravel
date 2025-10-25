<?php

namespace Database\Factories\Rental\Vehicle;

use App\Enum\Vehicle\VmCustodyVehicle;
use App\Enum\Vehicle\VmPickupStatus;
use App\Enum\Vehicle\VmSettlementMethod;
use App\Enum\Vehicle\VmSettlementStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Vehicle\VehicleMaintenance>
 */
class VehicleMaintenanceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'entry_datetime'        => fake_current_period_dt(),
            'maintenance_amount'    => $this->faker->randomFloat(2, 50, 10000),
            'entry_mileage'         => $this->faker->numberBetween(0, 200000),
            'next_maintenance_date' => fake_current_period_d(modify: '+3 months'),
            'departure_datetime'    => fake_current_period_dt(),
            'maintenance_mileage'   => $this->faker->numberBetween(0, 5000),
            'settlement_status'     => VmSettlementStatus::label_key_random(),
            'pickup_status'         => VmPickupStatus::label_key_random(),
            'settlement_method'     => VmSettlementMethod::label_key_random(),
            'custody_vehicle'       => VmCustodyVehicle::label_key_random(),
            'vm_remark'             => $this->faker->optional()->sentence,
            'additional_photos'     => fake_many_photos(),
            'maintenance_info'      => [],
        ];
    }
}
