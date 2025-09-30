<?php

namespace App\Http\Controllers\Admin\Sale;

use App\Attributes\PermissionAction;
use App\Attributes\PermissionType;
use App\Enum\Booking\BoBoSource;
use App\Enum\Booking\BoBType;
use App\Enum\Booking\BoOrderStatus;
use App\Enum\Booking\BoPaymentStatus;
use App\Enum\Booking\BoRefundStatus;
use App\Enum\Booking\BvIsListed;
use App\Enum\Booking\RboProps;
use App\Enum\Booking\RbvProps;
use App\Enum\Vehicle\VeStatusService;
use App\Http\Controllers\Controller;
use App\Models\Rental\Customer\RentalCustomer;
use App\Models\Rental\Sale\RentalBookingOrder;
use App\Models\Rental\Sale\RentalBookingVehicle;
use App\Models\Rental\Vehicle\RentalVehicle;
use App\Services\PaginateService;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

#[PermissionType('预定订单管理')]
class RentalBookingOrderController extends Controller
{
    public static function labelOptions(Controller $controller): void
    {
        $controller->response()->withExtras(
        );
    }

    #[PermissionAction(PermissionAction::INDEX)]
    public function index(Request $request): Response
    {
        $this->options(true);
        $query = RentalBookingOrder::indexQuery();

        $paginate = new PaginateService(
            [],
            [['bo.bo_id', 'desc']],
            ['kw'],
            []
        );

        $paginate->paginator($query, $request, [
            'kw__func' => function ($value, Builder $builder) {
                $builder->where(function (Builder $builder) use ($value) {
                    $builder->whereLike('bo.bo_no', '%'.$value.'%')
                        ->orWhereLike('bo.plate_no', '%'.$value.'%')
                        ->orWhereLike('cu.contact_name', '%'.$value.'%')
                    ;
                });
            },
        ]);

        return $this->response()->withData($paginate)->respond();
    }

    #[PermissionAction(PermissionAction::SHOW)]
    public function show(RentalBookingOrder $rentalBookingOrder): Response
    {
        $rentalBookingOrder->load(['RentalVehicle', 'RentalCustomer']);

        return $this->response()->withData($rentalBookingOrder)->respond();
    }

    #[PermissionAction(PermissionAction::ADD)]
    public function create(Request $request): Response
    {
        $rentalBookingOrder = new RentalBookingOrder([
            'bo_no'     => '',
            'bo_source' => BoBoSource::STORE,

            'payment_status' => BoPaymentStatus::UNPAID,
            'order_status'   => BoOrderStatus::UNPROCESSED,
            'refund_status'  => BoRefundStatus::NOREFUND,
        ]);

        $this->options();
        $this->response()->withExtras(
            RentalBookingVehicle::options(),
            RentalCustomer::options(),
        );

        return $this->response()->withData($rentalBookingOrder)->respond();
    }

    #[PermissionAction(PermissionAction::EDIT)]
    public function edit(RentalBookingOrder $rentalBookingOrder): Response
    {
        $rentalBookingOrder->load('RentalVehicle', 'RentalCustomer');

        $this->options();

        return $this->response()->withData($rentalBookingOrder)->respond();
    }

    #[PermissionAction(PermissionAction::ADD)]
    public function store(Request $request): Response
    {
        return $this->update($request, null);
    }

    #[PermissionAction(PermissionAction::EDIT)]
    public function update(Request $request, ?RentalBookingOrder $rentalBookingOrder): Response
    {
        $validator = Validator::make(
            $request->all(),
            [
                'bv_id'              => ['bail', Rule::excludeIf(null !== $rentalBookingOrder), 'required', 'integer', Rule::exists(RentalBookingVehicle::class)->where('is_listed', BvIsListed::LISTED)],
                'bo_no'              => ['bail', Rule::excludeIf(null !== $rentalBookingOrder), 'required', 'string', 'max:64', Rule::unique(RentalBookingOrder::class)->ignore($rentalBookingOrder)],
                'bo_source'          => ['bail', Rule::excludeIf(null !== $rentalBookingOrder), 'required', Rule::in(BoBoSource::label_keys())],
                'cu_id'              => ['bail', Rule::excludeIf(null !== $rentalBookingOrder), 'required', 'integer', Rule::exists(RentalCustomer::class)],
                'plate_no'           => ['bail', Rule::excludeIf(null !== $rentalBookingOrder), 'required', 'string', Rule::exists(RentalVehicle::class, 'plate_no')->where('status_service', VeStatusService::YES)],
                'b_type'             => ['bail', Rule::excludeIf(null !== $rentalBookingOrder), 'required', 'string', Rule::in(BoBType::label_keys())],
                'pickup_date'        => ['bail', Rule::excludeIf(null !== $rentalBookingOrder), 'required', 'date_format:Y-m-d'],
                'rent_per_amount'    => ['bail', Rule::excludeIf(null !== $rentalBookingOrder), 'required', 'decimal:0,2', 'gte:0'],
                'deposit_amount'     => ['bail', Rule::excludeIf(null !== $rentalBookingOrder), 'required', 'decimal:0,2', 'gte:0'],
                'b_props'            => ['bail', Rule::excludeIf(null !== $rentalBookingOrder), 'nullable', 'array'],
                'b_props.*'          => ['bail', Rule::excludeIf(null !== $rentalBookingOrder), 'string', Rule::in(array_keys(RbvProps::kv))],
                'registration_date'  => ['bail', Rule::excludeIf(null !== $rentalBookingOrder), 'required', 'date_format:Y-m-d'],
                'v_mileage'          => ['bail', Rule::excludeIf(null !== $rentalBookingOrder), 'nullable', 'integer', 'min:0'],
                'service_interval'   => ['bail', Rule::excludeIf(null !== $rentalBookingOrder), 'nullable', 'integer', 'min:0'],
                'min_rental_periods' => ['bail', Rule::excludeIf(null !== $rentalBookingOrder), 'required', 'integer', 'min:0'],
                'payment_status'     => ['bail', 'required', Rule::in(BoPaymentStatus::label_keys())],
                'order_status'       => ['bail', 'required', Rule::in(BoOrderStatus::label_keys())],
                'refund_status'      => ['bail', 'required', Rule::in(BoRefundStatus::label_keys())],
                'b_notes'            => ['bail', Rule::excludeIf(null !== $rentalBookingOrder), 'nullable', 'string'],
                'earnest_amount'     => ['bail', 'required', 'decimal:0,2', 'gte:0'],
            ],
            [],
            trans_property(RentalBookingOrder::class)
        )
            ->after(function (\Illuminate\Validation\Validator $validator) use ($rentalBookingOrder, $request) {
                if (!$validator->failed()) {
                    if (null === $rentalBookingOrder) { // 添加的时候
                        $rentalBookingVehicle = RentalBookingVehicle::query()->find($request->input('bv_id'));
                        if ($rentalBookingVehicle->b_type->value != $request->input('b_type')
                            || $rentalBookingVehicle->plate_no != $request->input('plate_no')
                            || $rentalBookingVehicle->pickup_date != $request->input('pickup_date')
                            || $rentalBookingVehicle->rent_per_amount != $request->input('rent_per_amount')
                            || $rentalBookingVehicle->deposit_amount != $request->input('deposit_amount')
                            || $rentalBookingVehicle->min_rental_periods != $request->input('min_rental_periods')
                            || $rentalBookingVehicle->registration_date != $request->input('registration_date')
                            || $rentalBookingVehicle->b_mileage != $request->input('b_mileage')
                            || $rentalBookingVehicle->service_interval != $request->input('service_interval')
                            || $rentalBookingVehicle->b_props != $request->input('b_props')
                            || $rentalBookingVehicle->b_note != $request->input('b_note')
                        ) {
                            $validator->errors()->add('bv_id', '信息已经更新，请重新下单');
                        }
                    }
                }
            })
        ;
        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $input = $validator->validated();

        if (null === $rentalBookingOrder) {
            $rentalBookingOrder = RentalBookingOrder::query()->create($input + ['order_at' => now()]);
        } else {
            $rentalBookingOrder->update($input);
        }

        $rentalBookingOrder->load(['RentalVehicle', 'RentalCustomer']);

        return $this->response()->withData($rentalBookingOrder)->respond();
    }

    #[PermissionAction(PermissionAction::DELETE)]
    public function destroy(RentalBookingOrder $rentalBookingOrder): Response
    {
        $rentalBookingOrder->delete();

        return $this->response()->withData($rentalBookingOrder)->respond();
    }

    #[PermissionAction(PermissionAction::ADD)]
    public function generate(Request $request, RentalBookingVehicle $rentalBookingVehicle): Response
    {
        $rentalBookingVehicle->append('bo_no');
        $rentalBookingVehicle->load(['RentalVehicle']);

        $result = array_filter($rentalBookingVehicle->toArray());

        return $this->response()->withData($result)->respond();
    }

    protected function options(?bool $with_group_count = false): void
    {
        $this->response()->withExtras(
            BoBType::options(),
            BoBoSource::options(),
            BoPaymentStatus::options(),
            BoOrderStatus::options(),
            BoRefundStatus::options(),
            RboProps::options(),
            BoBType::labelDic(),
        );
    }
}
