<?php

namespace Database\Factories\Rental\Vehicle;

use App\Enum\Vehicle\VeStatusDispatch;
use App\Enum\Vehicle\VeStatusRental;
use App\Enum\Vehicle\VeStatusService;
use App\Models\Rental\Vehicle\RentalVehicle;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Rental\Vehicle\RentalVehicle>
 */
class RentalVehicleFactory extends Factory
{
    /** 绑定模型（可省略，但显式更清晰） */
    protected $model = RentalVehicle::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // 购置日期在过去 5 年内；有效期在购置后 1~5 年
        $purchaseAt = $this->faker->dateTimeBetween('-5 years', 'now');
        $validUntil = (clone $purchaseAt)->modify('+'.$this->faker->numberBetween(1, 5).' years');

        return [
            // 主键 ve_id 为自增，不在工厂里设置

            'plate_no'        => strtoupper($this->faker->bothify('??-#####')), // 长度 << 64
            'vm_id'           => null, // 外键可空；如需生成有效外键，可在自定义 state 里赋值
            'status_service'  => VeStatusService::label_key_random(),
            'status_rental'   => VeStatusRental::label_key_random(),
            'status_dispatch' => VeStatusDispatch::label_key_random(),

            // jsonb 可空；给出一个简单对象，Eloquent 会自动 JSON 编码
            'license_face_photo' => $this->faker->boolean(60) ? [
                'url'  => $this->faker->imageUrl(800, 600, 'document', true),
                'hash' => $this->faker->sha1(),
            ] : null,
            'license_back_photo' => $this->faker->boolean(60) ? [
                'url'  => $this->faker->imageUrl(800, 600, 'document', true),
                'hash' => $this->faker->sha1(),
            ] : null,

            've_owner'   => $this->faker->name(),
            've_address' => $this->faker->address(),
            've_usage'   => $this->faker->randomElement(['commercial', 'personal', 'rental']),
            've_type'    => $this->faker->randomElement(['sedan', 'suv', 'van', 'truck', 'ev']),
            've_company' => $this->faker->company(),

            // VIN 通常 17 位大写字母数字（不严格排除 I/O/Q）
            've_vin_code'  => strtoupper(Str::random(17)),
            've_engine_no' => strtoupper($this->faker->bothify('??##########')), // 12 位左右

            've_purchase_date'    => $purchaseAt->format('Y-m-d'),
            've_valid_until_date' => $validUntil->format('Y-m-d'),

            've_mileage' => $this->faker->numberBetween(0, 300000),
            've_color'   => $this->faker->safeColorName(),

            'vehicle_manager' => null, // 外键可空；需要的话可在 state 中指定有效 admin id
        ];
    }
}
