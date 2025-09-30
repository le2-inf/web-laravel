<?php

namespace Database\Factories\Rental\Sale;

use App\Enum\Booking\BoBoSource;
use App\Enum\Booking\BoOrderStatus;
use App\Enum\Booking\BoPaymentStatus;
use App\Enum\Booking\BoRefundStatus;
use App\Enum\Booking\RboRboType;
use App\Models\Rental\Customer\RentalCustomer;
use App\Models\Rental\Sale\RentalBookingOrder;
use App\Models\Rental\Sale\RentalBookingVehicle;
use App\Models\Rental\Vehicle\RentalVehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RentalBookingOrder>
 */
class RentalBookingOrderFactory extends Factory
{
    protected $model = RentalBookingOrder::class;

    public function definition(): array
    {
        $pickup       = $this->faker->dateTimeBetween('-3 months', '+1 month');
        $registration = $this->faker->dateTimeBetween('-5 years', $pickup);

        return [
            'bo_no'     => 'RBO-'.$this->faker->unique()->bothify('####-######'),
            'bo_source' => BoBoSource::label_key_random(),

            // 生成并关联客户/车辆
            //            'cu_id'              => function () {
            //                return Customer::factory()->create()->getKey();
            //            },
            //            'plate_no'           => function () {
            //                return Vehicle::factory()->create()->plate_no;
            //            },

            // 校验规则要求存在于 vehicles.plate_no，这里与 plate_no 保持一致
            //            'b_type' => RboRboType::random(),

            //            'pickup_date'        => $pickup->format('Y-m-d'),
            //            'rent_per_amount'    => $this->faker->numberBetween(500, 5000),
            //            'deposit_amount'     => $this->faker->numberBetween(1000, 10000),
            //            'b_props'          => ['color' => $this->faker->safeColorName()],
            //            'registration_date'  => $registration->format('Y-m-d'),
            //            'mileage'            => $this->faker->numberBetween(0, 120000),
            //            'service_interval'   => $this->faker->randomElement([3000, 5000, 10000]),
            //            'min_rental_periods' => $this->faker->numberBetween(1, 12),

            'payment_status' => BoPaymentStatus::label_key_random(),
            'order_status'   => BoOrderStatus::label_key_random(),
            'refund_status'  => BoRefundStatus::label_key_random(),
            //            'b_notes'      => $this->faker->optional()->sentence(),
            'earnest_amount' => $this->faker->numberBetween(500, 1000),
        ];
    }

    public function forVehicle(RentalVehicle $rentalVehicle): self
    {
        return $this->state(fn () => [
            'plate_no' => $rentalVehicle->plate_no,
        ]);
    }

    public function forCustomer(RentalCustomer $rentalCustomer): self
    {
        return $this->state(fn () => [
            'cu_id' => $rentalCustomer->getKey(),
        ]);
    }

    public function forRentalBookingVehicle(RentalBookingVehicle $rentalBookingVehicle): self
    {
        return $this->state(fn () => [
            'b_type'             => $rentalBookingVehicle->b_type->value,
            'plate_no'           => $rentalBookingVehicle->plate_no,
            'pickup_date'        => $rentalBookingVehicle->pickup_date,
            'rent_per_amount'    => $rentalBookingVehicle->rent_per_amount,
            'deposit_amount'     => $rentalBookingVehicle->deposit_amount,
            'min_rental_periods' => $rentalBookingVehicle->min_rental_periods,
            'registration_date'  => $rentalBookingVehicle->registration_date,
            'b_mileage'          => $rentalBookingVehicle->b_mileage,
            'service_interval'   => $rentalBookingVehicle->service_interval,
            'b_props'            => $rentalBookingVehicle->b_props,
            'b_note'             => $rentalBookingVehicle->b_note,
        ]);
    }
}
