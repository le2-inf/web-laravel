<?php

namespace Tests\Http\Controllers\Rental;

use App\Enum\Rental\RpPtId;
use App\Enum\Rental\SoOrderStatus;
use App\Enum\Rental\SoPaymentDay_Month;
use App\Enum\Rental\SoRentalPaymentType;
use App\Enum\Rental\SoRentalType;
use App\Enum\Vehicle\VeStatusDispatch;
use App\Enum\Vehicle\VeStatusRental;
use App\Enum\Vehicle\VeStatusService;
use App\Http\Controllers\Admin\Sale\RentalSaleOrderController;
use App\Models\Rental\Customer\RentalCustomer;
use App\Models\Rental\Payment\RentalPayment;
use App\Models\Rental\Sale\RentalSaleOrder;
use App\Models\Rental\Vehicle\RentalVehicle;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\CoversNothing;
use Tests\TestCase;

/**
 * @property RentalCustomer $customer
 * @property RentalVehicle  $vehicle
 *
 * @internal
 */
#[CoversNothing]
#[\AllowDynamicProperties]
class RentalSaleOrderControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        RentalCustomer::query()->whereLike('contact_name', '测试客户%')->delete();
        RentalVehicle::query()->whereLike('plate_no', 'TEST-%')->delete();

        $this->customer = RentalCustomer::factory()->create([
            'contact_name'  => '测试客户'.Str::upper(Str::random(4)),
            'contact_phone' => '199'.rand(10000000, 99999999),
        ]);

        $this->vehicle = RentalVehicle::factory()->create([
            'plate_no'        => 'TEST-001',
            'status_service'  => VeStatusService::YES,
            'status_rental'   => VeStatusRental::LISTED,
            'status_dispatch' => VeStatusDispatch::NOT_DISPATCHED,
        ]);
    }

    public function testIndexReturnsPaginatedOrdersList(): void
    {
        $order = RentalSaleOrder::factory()
            ->for($this->customer)
            ->for($this->vehicle)
            ->create()
        ;

        $response = $this->getJson(action([RentalSaleOrderController::class, 'index']));

        $response->assertOk()
            ->assertJsonFragment(['contract_number' => $order->contract_number])
        ;

        $extra = $response->json('extra');
        $this->assertIsArray($extra);
        $this->assertArrayHasKey('RentalCustomerOptions', $extra);
        $this->assertArrayHasKey('RentalVehicleOptions', $extra);
    }

    public function testCreateProvidesDefaultOrderSkeleton(): void
    {
        $response = $this->getJson(action([RentalSaleOrderController::class, 'create']));

        $response->assertOk();

        $this->assertSame(SoRentalType::LONG_TERM, $response->json('data.rental_type'));
        $this->assertArrayHasKey('RentalCustomerOptions', $response->json('extra'));
    }

    public function testShowReturnsOrderWithPayments(): void
    {
        $order = RentalSaleOrder::factory()
            ->for($this->customer)
            ->for($this->vehicle)->create()
        ;

        $payment = RentalPayment::factory()
            ->for($order)
            ->create()
        ;

        $response = $this->getJson(
            action([RentalSaleOrderController::class, 'show'], [$order->getKey()])
        );

        $response->assertOk()
            ->assertJsonPath('data.so_id', $order->getKey())
            ->assertJsonPath('data.rental_payments.0.rp_id', $payment->getKey())
        ;
    }

    public function testStoreCreatesLongTermOrderWithPayments(): void
    {
        $payload = RentalSaleOrder::factory()
            ->for($this->customer)
            ->for($this->vehicle)->raw()
        ;
        if (SoRentalType::LONG_TERM === $payload['rental_type']) {
            $payment                    = RentalPayment::factory()->raw();
            $payload['rental_payments'] = [$payment];
        } else {
            $payload['rental_payments'] = [];
        }

        $response = $this->postJson(action([RentalSaleOrderController::class, 'store']), $payload);

        $response->assertOk()
            ->assertJsonFragment(['contract_number' => $payload['contract_number']])
        ;

        $this->assertDatabaseHas((new RentalSaleOrder())->getTable(), [
            'contract_number' => $payload['contract_number'],
            'cu_id'           => $this->customer->getKey(),
        ]);

        $created = RentalSaleOrder::query()
            ->where('contract_number', $payload['contract_number'])
            ->with('RentalPayments')
            ->firstOrFail()
        ;

        $this->assertCount(count($payload['rental_payments']), $created->RentalPayments);
        $this->assertSame(
            VeStatusRental::RESERVED,
            $this->vehicle->fresh()->status_rental->value
        );
    }

    public function testUpdateReplacesPaymentsAndPersistsComputedFields(): void
    {
        $order = RentalSaleOrder::factory()
            ->for($this->customer)
            ->for($this->vehicle)->create(['order_status' => SoOrderStatus::PENDING])
        ;

        RentalPayment::factory()
            ->for($order)
            ->create()
        ;

        $payload = RentalSaleOrder::factory()
            ->for($this->customer)
            ->for($this->vehicle)->raw()
        ;

        if (SoRentalType::LONG_TERM === $payload['rental_type']) {
            $payment                    = RentalPayment::factory()->raw();
            $payload['rental_payments'] = [$payment];
        } else {
            $payload['rental_payments'] = [];
        }

        $response = $this->putJson(
            action([RentalSaleOrderController::class, 'update'], [$order->getKey()]),
            $payload
        );

        $response->assertOk()
            ->assertJsonPath('data.rent_amount', bcadd($payload['rent_amount'], '0', 2))
            ->assertJsonPath('data.total_rent_amount', bcadd($payload['total_rent_amount'], '0', 2))
        ;

        $order->refresh()->load('RentalPayments');

        $this->assertSame((float) $payload['rent_amount'], (float) $order->rent_amount);
    }

    public function testDestroyRemovesOrder(): void
    {
        $order = RentalSaleOrder::factory()
            ->for($this->customer)
            ->for($this->vehicle)
            ->create()
        ;

        $response = $this->deleteJson(
            action([RentalSaleOrderController::class, 'destroy'], [$order->getKey()])
        );

        $response->assertOk();

        $this->assertDatabaseMissing((new RentalSaleOrder())->getTable(), ['so_id' => $order->getKey()]);
    }

    public function testRentalPaymentsOptionGeneratesSchedule(): void
    {
        $params = [
            'rental_type'           => SoRentalType::LONG_TERM,
            'rental_payment_type'   => SoRentalPaymentType::MONTHLY_PREPAID,
            'deposit_amount'        => '300.00',
            'management_fee_amount' => '50.00',
            'rental_start'          => '2024-01-01',
            'installments'          => 2,
            'rent_amount'           => '800.00',
            'payment_day'           => SoPaymentDay_Month::DAY_5,
        ];

        $response = $this->getJson(
            action([RentalSaleOrderController::class, 'rentalPaymentsOption'], $params)
        );

        $response->assertOk();
        $response->assertJsonCount(4, 'data');
        $this->assertSame(RpPtId::DEPOSIT, $response->json('data.0.pt_id'));
        $this->assertSame(RpPtId::MANAGEMENT_FEE, $response->json('data.1.pt_id'));
        $this->assertSame(RpPtId::RENT, $response->json('data.2.pt_id'));
    }
}
