<?php

namespace Database\Factories\Vehicle;

use App\Enum\Vehicle\ScScStatus;
use App\Models\Vehicle\ServiceCenter;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServiceCenter>
 */
class CenterFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sc_name'             => $this->faker->company().'修理厂',
            'sc_address'          => $this->faker->address(), // 示例：地区/行政区编码整数
            'contact_name'        => $this->faker->name(),
            'contact_phone'       => $this->faker->optional()->phoneNumber(),
            'sc_status'           => ScScStatus::label_key_random(),
            'sc_note'             => $this->faker->optional()->sentence(),
            'permitted_admin_ids' => [],
        ];
    }
}
