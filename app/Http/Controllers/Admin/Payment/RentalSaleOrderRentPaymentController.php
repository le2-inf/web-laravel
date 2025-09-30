<?php

namespace App\Http\Controllers\Admin\Payment;

use App\Attributes\PermissionType;
use App\Enum\Rental\PaPaStatus;
use App\Enum\Rental\RpPayStatus;
use App\Enum\Rental\SoOrderStatus;
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

#[PermissionType('收租金')]
class RentalSaleOrderRentPaymentController extends Controller
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
                    $builder->whereIn('so.order_status', [SoOrderStatus::SIGNED]);
                }
            ),
        );

        if ($so_id > 0) {
            $rentalSaleOrder = RentalSaleOrder::query()
                ->where('order_status', '=', SoOrderStatus::SIGNED)
                ->findOrFail($so_id)
            ;
            $rentalSaleOrder->load('RentalCustomer', 'RentalVehicle', 'UnpaidRentRentalPayments');

            $this->response()->withExtras(RentalPayment::option($rentalSaleOrder->UnpaidRentRentalPayments));
        } else {
            $rentalSaleOrder = [];
        }

        return $this->response()->withData($rentalSaleOrder)->respond();
    }

    public function store(Request $request, RentalSaleOrder $rentalSaleOrder): Response
    {
        $selectedData = $request->input('unpaid_rent_rental_payments')[$request->input('selectedIndex')] ?? null;

        abort_if(!$selectedData, 404);

        $validator = Validator::make(
            $selectedData,
            [
                'rp_id'             => ['bail', 'required', 'int'],
                'pay_status'        => ['bail', 'required', Rule::in([RpPayStatus::PAID])],
                'actual_pay_date'   => ['bail', 'required', 'date'],
                'actual_pay_amount' => ['bail', 'required', 'numeric'],
                'pa_id'             => ['bail', 'required', Rule::exists(RentalPaymentAccount::class)->where('pa_status', PaPaStatus::ENABLED)],
                'rp_remark'         => ['bail', 'nullable', 'string'],
            ],
            [],
            trans_property(RentalPayment::class)
        )
            ->after(function (\Illuminate\Validation\Validator $validator) use ($rentalSaleOrder, &$rentalVehicle, &$rentalCustomer) {
                if (!$validator->failed()) {
                    if (!$rentalSaleOrder->check_order_status([SoOrderStatus::SIGNED], $validator)) {
                        return;
                    }
                }
            })
        ;
        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $input = $validator->validated();

        DB::transaction(function () use (&$input) {
            $RentalPayment = RentalPayment::query()->where('rp_id', $input['rp_id'])->lockForUpdate()->first();
            $RentalPayment->update($input);
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
