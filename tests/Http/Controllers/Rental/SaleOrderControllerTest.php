<?php

namespace Tests\Http\Controllers\Rental;

use App\Enum\Rental\RpPtId;
use App\Enum\Rental\SoOrderStatus;
use App\Enum\Rental\SoPaymentDay_Month;
use App\Enum\Rental\SoPaymentDayType;
use App\Enum\Rental\SoRentalType;
use App\Enum\Vehicle\VeStatusDispatch;
use App\Enum\Vehicle\VeStatusRental;
use App\Enum\Vehicle\VeStatusService;
use App\Http\Controllers\Admin\Sale\SaleOrderController;
use App\Models\Customer\Customer;
use App\Models\Payment\Payment;
use App\Models\Sale\SaleOrder;
use App\Models\Vehicle\Vehicle;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\CoversNothing;
use Tests\TestCase;

/**
 * @property Customer $customer
 * @property Vehicle  $vehicle
 *
 * @internal
 */
#[CoversNothing]
#[\AllowDynamicProperties]
class SaleOrderControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Customer::query()->whereLike('contact_name', '测试客户%')->delete();
        Vehicle::query()->whereLike('plate_no', 'TEST-%')->delete();

        $this->customer = Customer::factory()->create([
            'contact_name'  => '测试客户'.Str::upper(Str::random(4)),
            'contact_phone' => '199'.rand(10000000, 99999999),
        ]);

        $this->vehicle = Vehicle::factory()->create([
            'plate_no'        => 'TEST-001',
            'status_service'  => VeStatusService::YES,
            'status_rental'   => VeStatusRental::LISTED,
            'status_dispatch' => VeStatusDispatch::NOT_DISPATCHED,
        ]);
    }

    public function testIndexReturnsPaginatedOrdersList(): void
    {
        $order = SaleOrder::factory()
            ->for($this->customer)
            ->for($this->vehicle)
            ->create()
        ;

        $response = $this->getJson(action([SaleOrderController::class, 'index']));

        $response->assertOk()
            ->assertJsonFragment(['contract_number' => $order->contract_number])
        ;

        $extra = $response->json('extra');
        $this->assertIsArray($extra);
        $this->assertArrayHasKey('CustomerOptions', $extra);
        $this->assertArrayHasKey('VehicleOptions', $extra);
    }

    public function testCreateProvidesDefaultOrderSkeleton(): void
    {
        $response = $this->getJson(action([SaleOrderController::class, 'create']));

        $response->assertOk();

        $this->assertSame(SoRentalType::LONG_TERM, $response->json('data.rental_type'));
        $this->assertArrayHasKey('CustomerOptions', $response->json('extra'));
    }

    public function testShowReturnsOrderWithPayments(): void
    {
        $order = SaleOrder::factory()
            ->for($this->customer)
            ->for($this->vehicle)->create()
        ;

        $payment = Payment::factory()
            ->for($order)
            ->create()
        ;

        $response = $this->getJson(
            action([SaleOrderController::class, 'show'], [$order->getKey()])
        );

        $response->assertOk()
            ->assertJsonPath('data.so_id', $order->getKey())
            ->assertJsonPath('data.payments.0.rp_id', $payment->getKey())
        ;
    }

    public function testStoreCreatesLongTermOrderWithPayments(): void
    {
        $payload = SaleOrder::factory()
            ->for($this->customer)
            ->for($this->vehicle)->raw()
        ;
        if (SoRentalType::LONG_TERM === $payload['rental_type']) {
            $payment             = Payment::factory()->raw();
            $payload['payments'] = [$payment];
        } else {
            $payload['payments'] = [];
        }

        $response = $this->postJson(action([SaleOrderController::class, 'store']), $payload);

        $response->assertOk()
            ->assertJsonFragment(['contract_number' => $payload['contract_number']])
        ;

        $this->assertDatabaseHas((new SaleOrder())->getTable(), [
            'contract_number' => $payload['contract_number'],
            'cu_id'           => $this->customer->getKey(),
        ]);

        $created = SaleOrder::query()
            ->where('contract_number', $payload['contract_number'])
            ->with('Payments')
            ->firstOrFail()
        ;

        $this->assertCount(count($payload['payments']), $created->Payments);
        $this->assertSame(
            VeStatusRental::RESERVED,
            $this->vehicle->fresh()->status_rental->value
        );
    }

    public function testUpdateReplacesPaymentsAndPersistsComputedFields(): void
    {
        $order = SaleOrder::factory()
            ->for($this->customer)
            ->for($this->vehicle)->create(['order_status' => SoOrderStatus::PENDING])
        ;

        Payment::factory()
            ->for($order)
            ->create()
        ;

        $payload = SaleOrder::factory()
            ->for($this->customer)
            ->for($this->vehicle)->raw()
        ;

        if (SoRentalType::LONG_TERM === $payload['rental_type']) {
            $payment             = Payment::factory()->raw();
            $payload['payments'] = [$payment];
        } else {
            $payload['payments'] = [];
        }

        $response = $this->putJson(
            action([SaleOrderController::class, 'update'], [$order->getKey()]),
            $payload
        );

        $response->assertOk()
            ->assertJsonPath('data.rent_amount', bcadd($payload['rent_amount'], '0', 2))
            ->assertJsonPath('data.total_rent_amount', bcadd($payload['total_rent_amount'], '0', 2))
        ;

        $order->refresh()->load('Payments');

        $this->assertSame((float) $payload['rent_amount'], (float) $order->rent_amount);
    }

    public function testDestroyRemovesOrder(): void
    {
        $order = SaleOrder::factory()
            ->for($this->customer)
            ->for($this->vehicle)
            ->create()
        ;

        $response = $this->deleteJson(
            action([SaleOrderController::class, 'destroy'], [$order->getKey()])
        );

        $response->assertOk();

        $this->assertDatabaseMissing((new SaleOrder())->getTable(), ['so_id' => $order->getKey()]);
    }

    public function testPaymentsOptionGeneratesSchedule(): void
    {
        $params = [
            'rental_type'           => SoRentalType::LONG_TERM,
            'payment_day_type'      => SoPaymentDayType::MONTHLY_PREPAID,
            'deposit_amount'        => '300.00',
            'management_fee_amount' => '50.00',
            'rental_start'          => '2024-01-01',
            'installments'          => 2,
            'rent_amount'           => '800.00',
            'payment_day'           => SoPaymentDay_Month::DAY_5,
        ];

        $response = $this->getJson(
            action([SaleOrderController::class, 'paymentsOption'], $params)
        );

        $response->assertOk();
        $response->assertJsonCount(4, 'data');
        $this->assertSame(RpPtId::DEPOSIT, $response->json('data.0.pt_id'));
        $this->assertSame(RpPtId::MANAGEMENT_FEE, $response->json('data.1.pt_id'));
        $this->assertSame(RpPtId::RENT, $response->json('data.2.pt_id'));
    }
}
