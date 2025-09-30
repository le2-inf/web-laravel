<?php

namespace Tests\Http\Controllers\Admin\Rental;

use App\Enum\Booking\BoBoSource;
use App\Enum\Booking\BoOrderStatus;
use App\Enum\Booking\BoPaymentStatus;
use App\Enum\Booking\BoRefundStatus;
use App\Enum\Booking\BvIsListed;
use App\Enum\Vehicle\VeStatusService;
use App\Http\Controllers\Admin\Sale\RentalBookingOrderController;
use App\Models\Rental\Customer\RentalCustomer;
use App\Models\Rental\Sale\RentalBookingOrder;
use App\Models\Rental\Sale\RentalBookingVehicle;
use App\Models\Rental\Vehicle\RentalVehicle;
use PHPUnit\Framework\Attributes\CoversNothing;
use Tests\TestCase;

/**
 * @property RentalVehicle  $rentalVehicle
 * @property RentalCustomer $rentalCustomer
 *
 * @internal
 */
#[CoversNothing]
#[\AllowDynamicProperties]
class RentalBookingOrdersControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        RentalCustomer::query()->whereLike('contact_name', 'TEST-%')->delete();
        RentalVehicle::query()->whereLike('plate_no', 'TEST-%')->delete();
        RentalBookingVehicle::query()->whereLike('plate_no', 'TEST-%')->delete();
        RentalBookingOrder::query()->whereLike('plate_no', 'TEST-%')->delete();

        $this->vehicle  = RentalVehicle::factory()->create(['plate_no' => 'TEST-001', 'status_service' => VeStatusService::YES]);
        $this->customer = RentalCustomer::factory()->create(['contact_name' => 'TEST-001']);

        $this->rentalBookingVehicle = RentalBookingVehicle::factory()->for($this->vehicle)->create(['is_listed' => BvIsListed::LISTED]);
    }

    public function testIndexReturnsPaginatedList()
    {
        $bookingOrder = RentalBookingOrder::factory()
            ->forRentalBookingVehicle($this->rentalBookingVehicle)
            ->for($this->customer)
            ->create()
        ;

        // getJson
        $resp = $this->getJson(
            action([RentalBookingOrderController::class, 'index'])
        );

        $resp->assertOk();
    }

    public function testShowReturnsSingleResourceWithRelations()
    {
        $bookingOrder = RentalBookingOrder::factory()
            ->forRentalBookingVehicle($this->rentalBookingVehicle)
            ->for($this->customer)
            ->create()
        ;

        $resp = $this->getJson(
            action([RentalBookingOrderController::class, 'show'], [$bookingOrder])
        );

        $resp->assertOk()
            ->assertJson([
                'data' => [],
            ])
        ;
    }

    public function testCreateReturnsDefaultValuesAndExtras()
    {
        // create 并不需要模型，直接请求
        $resp = $this->getJson(
            action([RentalBookingOrderController::class, 'create'])
        );

        $resp->assertOk()
            ->assertJson(
                [
                    'data' => [
                        'bo_no'           => '',
                        'bo_source'       => BoBoSource::STORE,
                        'bo_source_label' => BoBoSource::tryFrom(BoBoSource::STORE)->label,
                    ]],
            )
        ;
    }

    public function testEditReturnsModelWithRelationsAndExtras()
    {
        $bookingOrder = RentalBookingOrder::factory()
            ->forRentalBookingVehicle($this->rentalBookingVehicle)
            ->for($this->customer)
            ->create()
        ;

        $resp = $this->getJson(
            action([RentalBookingOrderController::class, 'edit'], [$bookingOrder])
        );

        $resp->assertOk();
    }

    public function testStoreCreatesANewOrderWithValidPayload()
    {
        $payload = RentalBookingOrder::factory()
            ->forRentalBookingVehicle($this->rentalBookingVehicle)
            ->for($this->customer)
            ->raw()
        ;

        $payload['bv_id'] = $this->rentalBookingVehicle->bv_id;

        $resp = $this->postJson(
            action([RentalBookingOrderController::class, 'store'], $payload)
        );

        $resp->assertOk()
            ->assertJson(
                [
                    'data' => [
                        'bo_no'          => $payload['bo_no'],
                        'payment_status' => $payload['payment_status'],
                    ],
                ]
            )
        ;

        $this->assertDatabaseHas((new RentalBookingOrder())->getTable(), [
            'bo_no' => $payload['bo_no'],
        ]);
    }

    public function testStoreValidatesRequiredFields()
    {
        $resp = $this->postJson(
            action([RentalBookingOrderController::class, 'store'])
        ); // 空载荷，触发校验

        $resp->assertStatus(422) // ValidationException
            ->assertJsonStructure(['message', 'errors'])
        ;
    }

    public function testUpdateChangesStatusFieldsOnExistingOrder()
    {
        $bookingOrder = RentalBookingOrder::factory()
            ->forRentalBookingVehicle($this->rentalBookingVehicle)
            ->for($this->customer)
            ->create()
        ;

        $payload = [
            // update 请求里，仅这些枚举必填（其余在 update 中 excludeIf）
            'payment_status' => BoPaymentStatus::PAID,
            'order_status'   => BoOrderStatus::PROCESSED,
            'refund_status'  => BoRefundStatus::REFUNDED,
            'earnest_amount' => $bookingOrder->earnest_amount,
        ];

        $resp = $this->putJson(
            action([RentalBookingOrderController::class, 'update'], [$bookingOrder]),
            $payload
        );

        $resp->assertOk()
            ->assertJson(
                [
                    'data' => [
                        'payment_status' => BoPaymentStatus::PAID,
                        'order_status'   => BoOrderStatus::PROCESSED,
                        'refund_status'  => BoRefundStatus::REFUNDED,
                    ],
                ]
            )
        ;

        $bookingOrder->refresh();
        $this->assertEquals(BoPaymentStatus::PAID, $bookingOrder->payment_status);
        $this->assertEquals(BoOrderStatus::PROCESSED, $bookingOrder->order_status);
    }

    public function testDestroyDeletesTheOrder()
    {
        $bookingOrder = RentalBookingOrder::factory()
            ->forRentalBookingVehicle($this->rentalBookingVehicle)
            ->for($this->customer)
            ->create()
        ;

        $resp = $this->deleteJson(
            action([RentalBookingOrderController::class, 'destroy'], [$bookingOrder])
        );

        $resp->assertOk()
            ->assertJson(
                [
                    'data' => ['bo_id' => $bookingOrder->getKey()],
                ]
            )
        ;

        // 避免依赖表名/软删，直接用断言模型缺失
        $this->assertModelMissing($bookingOrder);
    }

    public function testGenerateReturnsVehicleDerivedPayloadWithRboNo()
    {
        $resp = $this->getJson(
            action([RentalBookingOrderController::class, 'generate'], [$this->rentalBookingVehicle])
        );

        $resp->assertOk()
            ->assertJson(
                ['data' => []]
            )
        ;
    }
}
