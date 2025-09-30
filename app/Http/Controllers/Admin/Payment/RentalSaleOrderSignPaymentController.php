<?php

namespace App\Http\Controllers\Admin\Payment;

use App\Attributes\PermissionType;
use App\Enum\Rental\PaPaStatus;
use App\Enum\Rental\RpPayStatus;
use App\Enum\Rental\RpPtId;
use App\Enum\Rental\SoOrderStatus;
use App\Enum\Rental\SoRentalType;
use App\Enum\Vehicle\VeStatusRental;
use App\Http\Controllers\Controller;
use App\Models\Rental\Payment\RentalPayment;
use App\Models\Rental\Payment\RentalPaymentAccount;
use App\Models\Rental\Sale\RentalSaleOrder;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

#[PermissionType('签约收款')]
class RentalSaleOrderSignPaymentController extends Controller
{
    public static function labelOptions(Controller $controller): void
    {
        $controller->response()->withExtras(
        );
    }

    public function index() {}

    public function create(string $so_id): Response
    {
        abort_if(!is_numeric($so_id), 404);

        $this->response()->withExtras(
            RentalPaymentAccount::options(),
            RpPayStatus::options(),
            RentalSaleOrder::options(
                where: function (Builder $builder) {
                    $builder->whereIn('so.order_status', [SoOrderStatus::PENDING]);
                }
            ),
        );

        if ($so_id > 0) {
            /** @var RentalSaleOrder $rentalSaleOrder */
            $rentalSaleOrder = RentalSaleOrder::query()
                ->where('order_status', '=', SoOrderStatus::PENDING)
                ->findOrFail($so_id)
            ;
            $rentalSaleOrder->load('RentalCustomer', 'RentalVehicle', 'RentalPayments', 'RentalPayments.RentalSaleOrder', 'RentalPayments.RentalPaymentType', 'SignRentalPayments');

            // 补充 实收押金
            if (SoRentalType::LONG_TERM == $rentalSaleOrder->rental_type->value) {
                $rentalSaleOrder->deposit_amount_true        = $rentalSaleOrder->deposit_amount ?? '0.00';
                $rentalSaleOrder->management_fee_amount_true = $rentalSaleOrder->management_fee_amount ?? '0.00';
                $rentalSaleOrder->management_fee_amount      = $rentalSaleOrder->management_fee_amount ?? '0.00';
            } elseif (SoRentalType::SHORT_TERM == $rentalSaleOrder->rental_type->value) {
                $rentalSaleOrder->total_rent_amount_true = $rentalSaleOrder->total_rent_amount ?? '0.00';
                $rentalSaleOrder->deposit_amount_true    = $rentalSaleOrder->deposit_amount ?? '0.00';
            }
            $rentalSaleOrder->actual_pay_date = now()->format('Y-m-d');
        } else {
            $rentalSaleOrder = [];
        }

        return $this->response()->withData($rentalSaleOrder)->respond();
    }

    public function store(Request $request, RentalSaleOrder $rentalSaleOrder)
    {
        $is_long_term  = SoRentalType::LONG_TERM === $rentalSaleOrder->rental_type->value;
        $is_short_term = SoRentalType::SHORT_TERM === $rentalSaleOrder->rental_type->value;

        $validator = Validator::make(
            $request->all(),
            [
                'deposit_amount'                  => ['bail', 'required', 'numeric'],
                'deposit_amount_true'             => ['bail', 'required', 'numeric'],
                'management_fee_amount'           => ['bail', 'nullable', 'decimal:0,2', 'gte:0'],
                'management_fee_amount_true'      => ['bail', Rule::requiredIf($is_long_term), Rule::excludeIf($is_short_term), 'decimal:0,2', 'gte:0'],
                'total_rent_amount'               => ['bail', Rule::requiredIf($is_short_term), Rule::excludeIf($is_long_term), 'decimal:0,2', 'gte:0'],
                'total_rent_amount_true'          => ['bail', Rule::requiredIf($is_short_term), Rule::excludeIf($is_long_term), 'decimal:0,2', 'gte:0'],
                'insurance_base_fee_amount'       => ['bail', Rule::requiredIf($is_short_term), Rule::excludeIf($is_long_term), 'decimal:0,2', 'gte:0'],
                'insurance_additional_fee_amount' => ['bail', Rule::requiredIf($is_short_term), Rule::excludeIf($is_long_term), 'decimal:0,2', 'gte:0'],
                'other_fee_amount'                => ['bail', Rule::requiredIf($is_short_term), Rule::excludeIf($is_long_term), 'decimal:0,2', 'gte:0'],
                'actual_pay_date'                 => ['bail', 'required', 'date'],
                'pa_id'                           => ['bail', 'required', Rule::exists(RentalPaymentAccount::class)->where('pa_status', PaPaStatus::ENABLED)],
            ],
            [],
            trans_property(RentalSaleOrder::class) + trans_property(RentalPayment::class),
        )
            ->after(function (\Illuminate\Validation\Validator $validator) use ($rentalSaleOrder, &$rentalVehicle, &$rentalCustomer) {
                if (!$validator->failed()) {
                    if (!$rentalSaleOrder->check_order_status([SoOrderStatus::PENDING], $validator)) {
                        return;
                    }
                }
            })
        ;
        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $input = $validator->validated();

        DB::transaction(function () use ($rentalSaleOrder, &$input) {
            foreach (RpPtId::getFeeTypes($rentalSaleOrder->rental_type->value) as $label => $pt_id) {
                $should_pay_amount = $input[$label];
                $actual_pay_amount = $input[$label.'_true'] ?? null;
                if (
                    (null !== $actual_pay_amount && bccomp($actual_pay_amount, '0', 2) > 0)
                    || (null === $actual_pay_amount && bccomp($should_pay_amount, '0', 2) > 0)
                ) {
                    RentalPayment::query()->updateOrCreate([
                        'so_id' => $rentalSaleOrder->so_id,
                        'pt_id' => $pt_id,
                    ], [
                        'should_pay_date'   => $rentalSaleOrder->rental_start,
                        'should_pay_amount' => $should_pay_amount,
                        'pay_status'        => RpPayStatus::PAID,
                        'actual_pay_date'   => $input['actual_pay_date'],
                        'actual_pay_amount' => $actual_pay_amount ?? $should_pay_amount,
                        'pa_id'             => $input['pa_id'],
                    ]);
                }
            }

            $rentalSaleOrder->update([
                'order_status' => SoOrderStatus::SIGNED,
                'signed_at'    => now(),
            ]);

            $rentalSaleOrder->RentalVehicle->updateStatus(status_rental: VeStatusRental::RENTED);
        });

        return $this->response()->withData($input)->respond();
    }

    public function show(RentalPayment $rentalPayment) {}

    public function edit(RentalPayment $rentalPayment) {}

    public function update(Request $request, RentalSaleOrder $rentalSaleOrder) {}

    public function destroy(RentalPayment $rentalPayment) {}

    protected function options(?bool $with_group_count = false): void
    {
        $this->response()->withExtras(
        );
    }
}
