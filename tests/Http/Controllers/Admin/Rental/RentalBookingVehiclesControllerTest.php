<?php

namespace Tests\Http\Controllers\Admin\Rental;

use App\Enum\Booking\BvBType;
use App\Enum\Booking\BvIsListed;
use App\Enum\Booking\RbvProps;
use App\Http\Controllers\Admin\Sale\RentalBookingVehicleController;
use App\Models\Rental\Sale\RentalBookingVehicle;
use App\Models\Rental\Vehicle\RentalVehicle;
use Illuminate\Foundation\Testing\WithFaker;
use PHPUnit\Framework\Attributes\CoversNothing;
use Tests\TestCase;

/**
 * @property RentalVehicle        $rentalVehicle
 * @property RentalBookingVehicle $bv
 *
 * @internal
 */
#[CoversNothing]
#[\AllowDynamicProperties]
class RentalBookingVehiclesControllerTest extends TestCase
{
    use WithFaker;

    protected function setUp(): void
    {
        parent::setUp();

        RentalVehicle::query()->whereLike('plate_no', 'TEST-%')->delete();
        RentalBookingVehicle::query()->whereLike('plate_no', 'TEST-%')->delete();

        $this->vehicle = RentalVehicle::factory()->create(['plate_no' => 'TEST-004']);
    }

    public function testIndexReturnsOk()
    {
        $bookingVehicle = RentalBookingVehicle::factory()->for($this->vehicle)->create();

        $res = $this->getJson(
            action([RentalBookingVehicleController::class, 'index'])
        );
        $res->assertOk();
        // 列表结构可能是自定义分页体，这里只断言基本成功即可
        $this->assertIsArray($res->json());
    }

    public function testShowReturnsSingleItem()
    {
        $bookingVehicle = RentalBookingVehicle::factory()->for($this->vehicle)->create();

        $res = $this->getJson(
            action([RentalBookingVehicleController::class, 'show'], [$bookingVehicle])
        );
        $res->assertOk()->assertJsonStructure(['data']);
    }

    public function testStoreCreatesRecord()
    {
        $payload = [
            'b_type'             => BvBType::WEEKLY_RENT,
            'plate_no'           => $this->vehicle->plate_no,
            'pickup_date'        => now()->toDateString(),
            'rent_per_amount'    => 1000,
            'deposit_amount'     => 5000,
            'min_rental_periods' => 2,
            'registration_date'  => now()->toDateString(),
            'b_mileage'          => 0,
            'service_interval'   => 0,
            'b_props'            => $this->faker->optional()->randomElements(array_keys(RbvProps::kv), $this->faker->numberBetween(0, sizeof(RbvProps::kv))),
            'b_note'             => 'note here',
        ];

        $res = $this->postJson(
            action(
                [RentalBookingVehicleController::class, 'store'],
                $payload
            )
        );
        $res->assertOk()
            ->assertJsonStructure(['data'])
        ;

        // 断言数据库已存在对应记录（通过 Eloquent 查询，避免直接依赖表名）
        $this->assertTrue(
            RentalBookingVehicle::query()
                ->where('plate_no', $this->vehicle->plate_no)
                ->where('rent_per_amount', 1000)
                ->exists()
        );
    }

    public function testStoreValidationFailsWhenRequiredMissing()
    {
        // 少传关键字段：b_type / plate_no / pickup_date / rent_per_amount / deposit_amount / min_rental_periods / registration_date
        $res = $this->postJson(
            action(
                [RentalBookingVehicleController::class, 'store'],
                [
                    'b_type' => BvBType::WEEKLY_RENT,
                    // 故意缺失其他必填项
                ]
            )
        );

        $res->assertStatus(422)
            ->assertJsonValidationErrors(['plate_no', 'pickup_date', 'rent_per_amount', 'deposit_amount', 'min_rental_periods', 'registration_date'])
        ;
    }

    public function testUpdateUpdatesRecord()
    {
        $bookingVehicle = RentalBookingVehicle::factory()->for($this->vehicle)->create();

        // 更新时，控制器里对 b_type / plate_no 使用了 excludeIf，因此它们非必传
        $payload = [
            'plate_no'           => $this->vehicle->plate_no,
            'b_type'             => BvBType::WEEKLY_RENT,
            'pickup_date'        => now()->toDateString(),
            'rent_per_amount'    => 2000,
            'deposit_amount'     => 3000,
            'min_rental_periods' => 4,
            'registration_date'  => now()->toDateString(),
            'b_mileage'          => 12345,
            'service_interval'   => 6000,
            'b_props'            => $this->faker->optional()->randomElements(array_keys(RbvProps::kv), $this->faker->numberBetween(0, sizeof(RbvProps::kv))),
            'b_note'             => 'updated note',
        ];

        $res = $this->putJson(
            action([RentalBookingVehicleController::class, 'update'], [$bookingVehicle]),
            $payload
        );
        $res->assertOk()->assertJsonStructure(['data']);

        $bookingVehicle->refresh();
        $this->assertSame(2000, (int) $bookingVehicle->rent_per_amount);
        $this->assertSame(3000, (int) $bookingVehicle->deposit_amount);
        $this->assertSame(4, (int) $bookingVehicle->min_rental_periods);
        $this->assertSame(12345, (int) $bookingVehicle->b_mileage);
        $this->assertSame(6000, (int) $bookingVehicle->service_interval);
    }

    public function testDestroyDeletesRecord()
    {
        $bookingVehicle = RentalBookingVehicle::factory()->for($this->vehicle)->create();

        $res = $this->deleteJson(
            action([RentalBookingVehicleController::class, 'destroy'], [$bookingVehicle])
        );
        $res->assertOk()->assertJsonStructure(['data']);

        $this->assertModelMissing($bookingVehicle);
    }

    public function testEditReturnsDefaultFormDataAndOptions()
    {
        $bookingVehicle = RentalBookingVehicle::factory()->for($this->vehicle)->create();

        $res = $this->getJson(
            action([RentalBookingVehicleController::class, 'edit'], [$bookingVehicle])
        );
        $res->assertOk()->assertJsonStructure(['data']);
    }

    public function testCreateReturnsDefaultFormDataAndOptions()
    {
        $res = $this->getJson(
            action([RentalBookingVehicleController::class, 'create'])
        );
        $res->assertOk()->assertJsonStructure(
            [
                'data' => [
                    'b_type',
                    'pickup_date',
                    'registration_date',
                ],
            ]
        );
    }

    #[Test]
    public function testStatusUpdatesIsListedSuccess()
    {
        $bookingVehicle = RentalBookingVehicle::factory()->for($this->vehicle)->create();

        // 取一个“合法值”
        $valid = array_keys(BvIsListed::LABELS)[0];

        $res = $this->putJson(
            action([RentalBookingVehicleController::class, 'status'], [$bookingVehicle]),
            [
                'is_listed' => $valid,
            ]
        );

        $res->assertOk()->assertJsonStructure(['data']);

        $bookingVehicle->refresh();
        $actual = $bookingVehicle->is_listed->value;
        $this->assertSame($valid, $actual, 'is_listed 未被正确更新');
    }

    #[Test]
    public function testStatusValidationFailsWhenMissingIsListed()
    {
        $bookingVehicle = RentalBookingVehicle::factory()->for($this->vehicle)->create();

        $res = $this->putJson(
            action([RentalBookingVehicleController::class, 'status'], [$bookingVehicle]),
            [
                // 故意不传 is_listed
            ]
        );

        $res->assertStatus(422)
            ->assertJsonValidationErrors(['is_listed'])
        ;
    }

    #[Test]
    public function testStatusValidationFailsOnInvalidEnumValue()
    {
        $bookingVehicle = RentalBookingVehicle::factory()->for($this->vehicle)->create();

        // 提供一个明显非法的值
        $invalid = '___NOT_A_VALID_ENUM___';

        $res = $this->putJson(
            action([RentalBookingVehicleController::class, 'status'], [$bookingVehicle]),
            [
                'is_listed' => $invalid,
            ]
        );

        $res->assertStatus(422)
            ->assertJsonValidationErrors(['is_listed'])
        ;
    }
}
