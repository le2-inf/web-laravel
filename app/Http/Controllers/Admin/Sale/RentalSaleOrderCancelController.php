<?php

namespace App\Http\Controllers\Admin\Sale;

use App\Attributes\PermissionAction;
use App\Attributes\PermissionType;
use App\Enum\Rental\RpIsValid;
use App\Enum\Rental\SoOrderStatus;
use App\Enum\Vehicle\VeStatusDispatch;
use App\Enum\Vehicle\VeStatusRental;
use App\Enum\Vehicle\VeStatusService;
use App\Http\Controllers\Controller;
use App\Models\Rental\Sale\RentalSaleOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

#[PermissionType('租车订单取消管理')]
class RentalSaleOrderCancelController extends Controller
{
    public static function labelOptions(Controller $controller): void
    {
        $controller->response()->withExtras(
        );
    }

    #[PermissionAction(PermissionAction::INVOKE)]
    public function update(Request $request, RentalSaleOrder $rentalSaleOrder): Response
    {
        $validator = Validator::make(
            $request->all(),
            []
        )
            ->after(function (\Illuminate\Validation\Validator $validator) use ($rentalSaleOrder) {
                if (!$rentalSaleOrder->check_order_status([SoOrderStatus::PENDING, SoOrderStatus::SIGNED], $validator)) {
                    return;
                }

                if (!$rentalVehicle = $rentalSaleOrder->RentalVehicle) {
                    $validator->errors()->add('ve_id', 'The vehicle does not exist.');

                    return;
                }

                if (!$rentalVehicle->check_status(VeStatusService::YES, [VeStatusRental::RESERVED, VeStatusRental::RENTED], [VeStatusDispatch::NOT_DISPATCHED], $validator)) {
                    return;
                }
            })
        ;

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $input = $validator->validated();

        DB::transaction(function () use (&$rentalSaleOrder) {
            $rentalSaleOrder = $rentalSaleOrder->newQuery()->useWritePdo()
                ->whereKey($rentalSaleOrder->getKey())->lockForUpdate()->firstOrFail()
            ;

            match ($rentalSaleOrder->order_status->value) {
                SoOrderStatus::PENDING => (function () use ($rentalSaleOrder) {
                    $rentalSaleOrder->RentalVehicle->updateStatus(
                        status_rental: VeStatusRental::LISTED,
                    );

                    $rentalSaleOrder->RentalPayments()->update([
                        'is_valid' => RpIsValid::INVALID,
                    ]);
                })(),
                SoOrderStatus::SIGNED => (function () use ($rentalSaleOrder) {
                    $rentalSaleOrder->RentalVehicle->updateStatus(
                        status_rental: VeStatusRental::PENDING,
                    );

                    $rentalSaleOrder->RentalPayments->each(function ($item, $key) {
                        $item->update([
                            'is_valid' => RpIsValid::INVALID,
                        ]);
                    });
                })(),
            };

            $rentalSaleOrder->order_status = SoOrderStatus::CANCELLED;
            $rentalSaleOrder->canceled_at  = now();
            $rentalSaleOrder->save();
        });

        $rentalSaleOrder->refresh();

        return $this->response()->withData($rentalSaleOrder)->respond();
    }

    protected function options(?bool $with_group_count = false): void
    {
        $this->response()->withExtras(
        );
    }
}
