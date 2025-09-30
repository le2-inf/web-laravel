<?php

namespace Tests\Http\Controllers\Rental;

use App\Enum\Rental\RpPtId;
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
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\CoversNothing;
use Tests\TestCase;

/**
 * @internal
 */
#[CoversNothing]
class RentalSaleOrderControllerTest extends TestCase
{
    private RentalCustomer $customer;

    private RentalVehicle $vehicle;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = RentalCustomer::factory()->create([
            'contact_name'  => '测试客户'.Str::upper(Str::random(4)),
            'contact_phone' => '199'.rand(10000000, 99999999),
        ]);

        $this->vehicle = RentalVehicle::factory()->create([
            'status_service'  => VeStatusService::YES,
            'status_rental'   => VeStatusRental::LISTED,
            'status_dispatch' => VeStatusDispatch::NOT_DISPATCHED,
        ]);
    }

    public function testIndexReturnsPaginatedOrdersList(): void
    {
        $order = $this->createLongTermOrder();

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
        $order = $this->createLongTermOrder();

        $payment = RentalPayment::factory()
            ->for($order, 'RentalSaleOrder')
            ->state([
                'pt_id'             => RpPtId::RENT,
                'should_pay_amount' => '1000.00',
                'should_pay_date'   => now()->toDateString(),
            ])
            ->create()
        ;

        $response = $this->getJson(
            action([RentalSaleOrderController::class, 'show'], ['rental_sale_order' => $order->getKey()])
        );

        $response->assertOk()
            ->assertJsonPath('data.so_id', $order->getKey())
            ->assertJsonPath('data.rental_payments.0.rp_id', $payment->getKey())
        ;
    }

    public function testStoreCreatesLongTermOrderWithPayments(): void
    {
        $payload  = $this->longTermPayload();
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
        $order = $this->createLongTermOrder();

        RentalPayment::factory()
            ->for($order, 'RentalSaleOrder')
            ->state([
                'pt_id'             => RpPtId::RENT,
                'should_pay_amount' => '1000.00',
                'should_pay_date'   => '2024-01-01',
            ])
            ->create()
        ;

        $payload = $this->longTermPayload([
            'contract_number' => $order->contract_number,
            'rent_amount'     => '1200.00',
            'installments'    => 2,
            'rental_end'      => '2024-03-01',
            'rental_payments' => [
                [
                    'pt_id'             => RpPtId::RENT,
                    'should_pay_date'   => '2024-01-05',
                    'should_pay_amount' => '1200.00',
                    'rp_remark'         => 'updated installment 1',
                ],
                [
                    'pt_id'             => RpPtId::RENT,
                    'should_pay_date'   => '2024-02-05',
                    'should_pay_amount' => '1200.00',
                    'rp_remark'         => 'updated installment 2',
                ],
            ],
        ]);

        $response = $this->putJson(
            action([RentalSaleOrderController::class, 'update'], ['rental_sale_order' => $order->getKey()]),
            $payload
        );

        $response->assertOk()
            ->assertJsonPath('data.rent_amount', '1200.00')
            ->assertJsonPath('data.total_rent_amount', '2400.00')
        ;

        $order->refresh()->load('RentalPayments');

        $this->assertSame('1200.00', $order->rent_amount);
        $this->assertCount(2, $order->RentalPayments);
        $this->assertSame('1200.00', $order->RentalPayments[0]->should_pay_amount);
    }

    public function testDestroyRemovesOrder(): void
    {
        $order = $this->createLongTermOrder();

        $response = $this->deleteJson(
            action([RentalSaleOrderController::class, 'destroy'], ['rental_sale_order' => $order->getKey()])
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

    private function longTermPayload(array $overrides = []): array
    {
        $start = '2024-01-01';
        $end   = '2024-02-01';

        return array_merge([
            'rental_type'           => SoRentalType::LONG_TERM,
            'rental_payment_type'   => SoRentalPaymentType::MONTHLY_PREPAID,
            'cu_id'                 => $this->customer->getKey(),
            've_id'                 => $this->vehicle->getKey(),
            'contract_number'       => 'CN-'.Str::upper(Str::random(10)),
            'free_days'             => 0,
            'rental_start'          => $start,
            'installments'          => 1,
            'rental_end'            => $end,
            'deposit_amount'        => '500.00',
            'management_fee_amount' => '50.00',
            'rent_amount'           => '1000.00',
            'payment_day'           => SoPaymentDay_Month::DAY_1,
            'rental_payments'       => [
                [
                    'pt_id'             => RpPtId::RENT,
                    'should_pay_date'   => $start,
                    'should_pay_amount' => '1000.00',
                    'rp_remark'         => 'installment 1',
                ],
            ],
        ], $overrides);
    }

    private function createLongTermOrder(array $overrides = []): RentalSaleOrder
    {
        $start        = $overrides['rental_start'] ?? '2024-01-01';
        $end          = $overrides['rental_end'] ?? '2024-02-01';
        $installments = $overrides['installments'] ?? 1;
        $rentAmount   = $overrides['rent_amount'] ?? '1000.00';
        $deposit      = $overrides['deposit_amount'] ?? '500.00';
        $management   = $overrides['management_fee_amount'] ?? '50.00';
        $contract     = $overrides['contract_number'] ?? 'CN-'.Str::upper(Str::random(8));

        $totalRent   = bcmul((string) $installments, $rentAmount, 2);
        $totalAmount = bcadd(bcadd($totalRent, $deposit, 2), $management, 2);

        $state = array_merge([
            'rental_type'           => SoRentalType::LONG_TERM,
            'rental_payment_type'   => SoRentalPaymentType::MONTHLY_PREPAID,
            'contract_number'       => $contract,
            'free_days'             => 0,
            'rental_start'          => $start,
            'installments'          => $installments,
            'rental_days'           => Carbon::parse($start)->diffInDays(Carbon::parse($end), true) + 1,
            'rental_end'            => $end,
            'deposit_amount'        => $deposit,
            'management_fee_amount' => $management,
            'rent_amount'           => $rentAmount,
            'payment_day'           => $overrides['payment_day'] ?? SoPaymentDay_Month::DAY_1,
            'total_rent_amount'     => $totalRent,
            'total_amount'          => $totalAmount,
        ], Arr::except($overrides, [
            'contract_number',
            'rental_start',
            'installments',
            'rental_end',
            'deposit_amount',
            'management_fee_amount',
            'rent_amount',
            'payment_day',
        ]));

        return RentalSaleOrder::factory()
            ->for($this->customer, 'RentalCustomer')
            ->for($this->vehicle, 'RentalVehicle')
            ->state($state)
            ->create()
        ;
    }
}
