<?php

namespace App\Http\Controllers\Admin\Vehicle;

use App\Attributes\PermissionAction;
use App\Attributes\PermissionType;
use App\Enum\Rental\RpIsValid;
use App\Enum\Rental\RpPayStatus;
use App\Enum\Rental\RpPtId;
use App\Enum\Vehicle\ScScStatus;
use App\Enum\Vehicle\VeStatusService;
use App\Enum\Vehicle\VrCustodyVehicle;
use App\Enum\Vehicle\VrPickupStatus;
use App\Enum\Vehicle\VrRepairAttribute;
use App\Enum\Vehicle\VrRepairStatus;
use App\Enum\Vehicle\VrSettlementMethod;
use App\Enum\Vehicle\VrSettlementStatus;
use App\Exceptions\ClientException;
use App\Http\Controllers\Controller;
use App\Models\Rental\Payment\RentalPayment;
use App\Models\Rental\Payment\RentalPaymentAccount;
use App\Models\Rental\Sale\RentalSaleOrder;
use App\Models\Rental\Vehicle\RentalServiceCenter;
use App\Models\Rental\Vehicle\RentalVehicle;
use App\Models\Rental\Vehicle\RentalVehicleRepair;
use App\Services\PaginateService;
use App\Services\Uploader;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

#[PermissionType('维修管理')]
class RentalVehicleRepairController extends Controller
{
    public static function labelOptions(Controller $controller): void
    {
        $controller->response()->withExtras(
            VrRepairAttribute::labelOptions(),
            VrCustodyVehicle::labelOptions(),
            VrPickupStatus::labelOptions(),
            VrSettlementMethod::labelOptions(),
            VrSettlementStatus::labelOptions(),
            VrRepairStatus::labelOptions(),
        );
    }

    #[PermissionAction(PermissionAction::INDEX)]
    public function index(Request $request): Response
    {
        $this->options(true);
        $this->response()->withExtras(
            RentalVehicle::options(),
        );

        $query   = RentalVehicleRepair::indexQuery();
        $columns = RentalVehicleRepair::indexColumns();

        $paginate = new PaginateService(
            [],
            [['vr.vr_id', 'desc']],
            ['kw', 'vr_ve_id', 'vr_entry_datetime', 'vr_repair_status'],
            []
        );

        $paginate->paginator(
            $query,
            $request,
            [
                'kw__func' => function ($value, Builder $builder) {
                    $builder->where(function (Builder $builder) use ($value) {
                        $builder->where('ve.plate_no', 'like', '%'.$value.'%')
                            ->orWhere('vr.vr_remark', 'like', '%'.$value.'%')
                        ;
                    });
                },
            ],
            $columns
        );

        return $this->response()->withData($paginate)->respond();
    }

    #[PermissionAction(PermissionAction::ADD)]
    public function create(Request $request): Response
    {
        $this->options();
        $this->response()->withExtras(
            RentalVehicle::options(),
            RentalServiceCenter::options(),
            RpPayStatus::options(),
            RentalPaymentAccount::options(),
        );

        $rentalVehicleRepair = new RentalVehicleRepair([
            'entry_datetime' => now(),
            'repair_info'    => [],
        ]);

        //        $rentalVehicleRepair->load('RentalVehicle', 'RentalSaleOrder', 'RentalSaleOrder.RentalCustomer', 'RentalPayment', 'RentalPayment.RentalPaymentType');

        return $this->response()->withData($rentalVehicleRepair)->respond();
    }

    #[PermissionAction(PermissionAction::ADD)]
    public function store(Request $request): Response
    {
        return $this->update($request, null);
    }

    #[PermissionAction(PermissionAction::SHOW)]
    public function show(RentalVehicleRepair $rentalVehicleRepair): Response
    {
        $this->options();

        return $this->response()->withData($rentalVehicleRepair)->respond();
    }

    #[PermissionAction(PermissionAction::EDIT)]
    public function edit(RentalVehicleRepair $rentalVehicleRepair): Response
    {
        $this->options();
        $this->response()->withExtras(
            RentalVehicle::options(),
            VrRepairStatus::finalValue(),
            RentalServiceCenter::options(),
            RpPayStatus::options(),
            RentalPaymentAccount::options(),
        );

        if ($rentalVehicleRepair->ve_id) {
            $this->response()->withExtras(
                RentalSaleOrder::options(
                    where: function (Builder $builder) use ($rentalVehicleRepair) {
                        $builder->where('so.ve_id', '=', $rentalVehicleRepair->ve_id);
                    }
                ),
            );
        }

        $rentalVehicleRepair->load('RentalVehicle', 'RentalSaleOrder', 'RentalSaleOrder.RentalCustomer', 'RentalPayment', 'RentalPayment.RentalPaymentType', 'RentalPayment.RentalPaymentAccount');

        return $this->response()->withData($rentalVehicleRepair)->respond();
    }

    #[PermissionAction(PermissionAction::EDIT)]
    public function update(Request $request, ?RentalVehicleRepair $rentalVehicleRepair = null): Response
    {
        $validator = Validator::make(
            $request->all(),
            [
                've_id'              => ['bail', 'required', 'integer'],
                'so_id'              => ['bail', Rule::requiredIf($request->boolean('add_should_pay')), 'nullable', 'integer'],
                'entry_datetime'     => ['bail', 'required', 'date'],
                'vr_mileage'         => ['bail', 'nullable', 'integer', 'min:0'],
                'repair_cost'        => ['bail', 'nullable', 'decimal:0,2', 'gte:0'],
                'delay_days'         => ['bail', 'nullable', 'integer', 'min:0'],
                'sc_id'              => ['bail', 'required', 'integer', 'min:1', Rule::exists(RentalServiceCenter::class)->where('status', ScScStatus::ENABLED)],
                'repair_content'     => ['bail', 'required', 'string'],
                'departure_datetime' => ['bail', 'nullable', 'date'],
                'repair_status'      => ['bail', 'required', 'string', Rule::in(VrRepairStatus::label_keys())],
                'settlement_status'  => ['bail', 'required', 'string', Rule::in(VrSettlementStatus::label_keys())],
                'pickup_status'      => ['bail', 'required', 'string', Rule::in(VrPickupStatus::label_keys())],
                'settlement_method'  => ['bail', 'required', 'string', Rule::in(VrSettlementMethod::label_keys())],
                'custody_vehicle'    => ['bail', 'required', 'string', Rule::in(VrCustodyVehicle::label_keys())],
                'repair_attribute'   => ['bail', 'required', 'string', Rule::in(VrRepairAttribute::label_keys())],
                'vr_remark'          => ['bail', 'nullable', 'string'],
                'add_should_pay'     => ['bail', 'sometimes', 'boolean'],

                'repair_info'                 => ['bail', 'nullable', 'array'],
                'repair_info.*.description'   => ['bail', 'nullable', 'string'],
                'repair_info.*.part_name'     => ['bail', 'nullable', 'string', 'max:255'],
                'repair_info.*.part_cost'     => ['bail', 'nullable', 'decimal:0,2', 'gte:0'],
                'repair_info.*.part_quantity' => ['bail', 'nullable', 'integer', 'min:1'],

                'rental_payment.pt_id'             => 'bail', [Rule::requiredIf($request->boolean('add_should_pay')), Rule::in(RpPtId::REPAIR_FEE)],
                'rental_payment.should_pay_date'   => ['bail', Rule::requiredIf($request->boolean('add_should_pay')), 'nullable', 'date'],
                'rental_payment.should_pay_amount' => ['bail', Rule::requiredIf($request->boolean('add_should_pay')), 'nullable', 'numeric'],
                'rental_payment.rp_remark'         => ['bail', 'nullable', 'string'],
            ]
            + Uploader::validator_rule_upload_array('additional_photos')
            + Uploader::validator_rule_upload_array('repair_info.*.info_photos'),
            [],
            trans_property(RentalVehicleRepair::class) + Arr::dot(['rental_payment' => trans_property(RentalPayment::class)])
        )
            ->after(function (\Illuminate\Validation\Validator $validator) use ($rentalVehicleRepair, $request, &$rentalVehicle) {
                if (!$validator->failed()) {
                    $so_id = $request->input('so_id');

                    if ($so_id) {
                        /** @var RentalSaleOrder $rentalSaleOrder */
                        $RentalSaleOrder = RentalSaleOrder::query()->find($so_id);
                        if (!$RentalSaleOrder) {
                            $validator->errors()->add('so_id', 'The rental_order does not exist.');

                            return;
                        }
                    }

                    // ve_id
                    $ve_id = $request->input('ve_id');

                    /** @var RentalVehicle $rentalVehicle */
                    $rentalVehicle = RentalVehicle::query()->find($ve_id);
                    if (!$rentalVehicle) {
                        $validator->errors()->add('ve_id', 'The vehicle does not exist.');

                        return;
                    }

                    $pass = $rentalVehicle->check_status(VeStatusService::YES, [], [], $validator);
                    if (!$pass) {
                        return;
                    }

                    // 不能关闭财务记录的判断：修改状态 + 收付款数据存在 + 收付款数据为已支付 + 当前是要关闭。
                    if (null !== $rentalVehicleRepair) { // 当是修改逻辑
                        $RentalPayment = $rentalVehicleRepair->RentalPayment;
                        if ($RentalPayment->exists && RpPayStatus::PAID === $RentalPayment->pay_status->value) {
                            if (!$request->boolean('add_should_pay')) {
                                $validator->errors()->add('RentalPayment', '关联的支付已经支付，不能关闭财务记录。');
                            }
                        }
                    }
                }
            })
        ;

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $input = $validator->validated();

        $input['rental_payment']['so_id'] = $input['so_id'];

        DB::transaction(function () use (&$input, &$rentalVehicle, &$rentalVehicleRepair) {
            if (null === $rentalVehicleRepair) {
                $rentalVehicleRepair = RentalVehicleRepair::query()->create($input);

                if ($rentalVehicleRepair->add_should_pay) {
                    $rentalVehicleRepair->RentalPayment()->create($input['rental_payment']);
                }
            } else {
                $rentalVehicleRepair->update($input);

                if ($rentalVehicleRepair->add_should_pay) {
                    $RentalPayment = $rentalVehicleRepair->RentalPayment;
                    if ($RentalPayment->exists) {
                        if (RpPayStatus::PAID === $RentalPayment->pay_status->value) {
                            $RentalPayment->fill($input['rental_payment']);
                            if ($RentalPayment->isDirty()) {
                                throw new ClientException('财务信息已支付，不能做修改。'); // 不能修改财务记录的判断：修改状态 + 收款数据存在 + 收款记录为已支付 + 收款记录要做更新($model->isDirty()) =>
                            }
                        } else {
                            $RentalPayment->update($input['rental_payment']);
                        }
                    } else {
                        $rentalVehicleRepair->RentalPayment()->create($input['rental_payment']);
                    }
                } else {
                    $rentalVehicleRepair->RentalPayment()->where('pay_status', '=', RpPayStatus::UNPAID)->update(
                        [
                            'is_valid' => RpIsValid::INVALID,
                        ]
                    );
                }
            }
        });

        return $this->response()->withData($rentalVehicleRepair)->respond();
    }

    #[PermissionAction(PermissionAction::DELETE)]
    public function destroy(RentalVehicleRepair $rentalVehicleRepair): Response
    {
        $validator = Validator::make(
            [],
            []
        )
            ->after(function (\Illuminate\Validation\Validator $validator) {
                if (!$validator->failed()) {
                }
            })
        ;

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $rentalVehicleRepair->delete();

        return $this->response()->withData($rentalVehicleRepair)->respond();
    }

    #[PermissionAction(PermissionAction::ADD)]
    public function rentalSaleOrdersOption(Request $request): Response
    {
        $validator = Validator::make(
            $request->all(),
            [
                've_id' => ['bail', 'required', 'integer'],
            ]
        );

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $input = $validator->validated();

        $this->response()->withExtras(
            RentalSaleOrder::options(
                where: function (Builder $builder) use ($input) {
                    $builder->where('so.ve_id', '=', $input['ve_id']);
                }
            )
        );

        return $this->response()->respond();
    }

    #[PermissionAction(PermissionAction::ADD)]
    #[PermissionAction(PermissionAction::EDIT)]
    public function upload(Request $request): Response
    {
        return Uploader::upload($request, 'vehicle_repair', ['additional_photos', 'info_photos'], $this);
    }

    protected function options(?bool $with_group_count = false): void
    {
        $this->response()->withExtras(
            VrRepairAttribute::options(),
            VrCustodyVehicle::options(),
            VrPickupStatus::options(),
            VrSettlementMethod::options(),
            VrSettlementStatus::options(),
            $with_group_count ? VrRepairStatus::options_with_count(RentalVehicleRepair::class) : VrRepairStatus::options(),
        );
    }
}
