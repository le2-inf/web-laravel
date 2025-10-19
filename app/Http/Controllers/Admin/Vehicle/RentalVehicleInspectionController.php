<?php

namespace App\Http\Controllers\Admin\Vehicle;

use App\Attributes\PermissionAction;
use App\Attributes\PermissionType;
use App\Enum\Exist;
use App\Enum\Rental\DtDtExportType;
use App\Enum\Rental\DtDtStatus;
use App\Enum\Rental\DtDtType;
use App\Enum\Rental\RpIsValid;
use App\Enum\Rental\RpPayStatus;
use App\Enum\Rental\RpPtId;
use App\Enum\Rental\SoOrderStatus;
use App\Enum\Vehicle\VeStatusDispatch;
use App\Enum\Vehicle\VeStatusRental;
use App\Enum\Vehicle\VeStatusService;
use App\Enum\Vehicle\ViInspectionType;
use App\Enum\Vehicle\ViVehicleDamageStatus;
use App\Exceptions\ClientException;
use App\Http\Controllers\Controller;
use App\Models\Admin\Admin;
use App\Models\Rental\Payment\RentalPayment;
use App\Models\Rental\Payment\RentalPaymentAccount;
use App\Models\Rental\Sale\RentalDocTpl;
use App\Models\Rental\Sale\RentalSaleOrder;
use App\Models\Rental\Vehicle\RentalVehicle;
use App\Models\Rental\Vehicle\RentalVehicleInspection;
use App\Models\Rental\Vehicle\RentalVehicleUsage;
use App\Services\DocTplService;
use App\Services\PaginateService;
use App\Services\Uploader;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

#[PermissionType('验车管理')]
class RentalVehicleInspectionController extends Controller
{
    public static function labelOptions(Controller $controller): void
    {
        $controller->response()->withExtras(
            ViInspectionType::labelOptions(),
            ViVehicleDamageStatus::labelOptions(),
            Exist::labelOptions(),
        );
    }

    #[PermissionAction(PermissionAction::INDEX)]
    public function index(Request $request): Response
    {
        $this->options(true);
        $this->response()->withExtras(
            RentalVehicle::options(),
        );

        $query   = RentalVehicleInspection::indexQuery();
        $columns = RentalVehicleInspection::indexColumns();

        $paginate = new PaginateService(
            [],
            [['vi.vi_id', 'desc']],
            ['kw', 'vi_inspection_type', 'vi_ve_id', 'vi_kw', 'vi_inspection_datetime'],
            []
        );

        $paginate->paginator(
            $query,
            $request,
            [
                'kw__func' => function ($value, Builder $builder) {
                    $builder->where(function (Builder $builder) use ($value) {
                        $builder->where('ve.plate_no', 'like', '%'.$value.'%')
                            ->orWhere('vi.vi_remark', 'like', '%'.$value.'%')
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
            RpPayStatus::options(),
            RentalPaymentAccount::options(),
            Admin::optionsWithRoles(),
        );

        $rentalVehicleInspection = new RentalVehicleInspection([
            'inspection_info' => [],
            'processed_by'    => Auth::id(),
        ]);

        //        $rentalVehicleInspection->load('RentalVehicle', 'RentalSaleOrder', 'RentalSaleOrder.RentalCustomer', 'RentalPayment', 'RentalPayment.RentalPaymentType');

        return $this->response()->withData($rentalVehicleInspection)->respond();
    }

    #[PermissionAction(PermissionAction::ADD)]
    public function store(Request $request): Response
    {
        return $this->update($request, null);
    }

    #[PermissionAction(PermissionAction::SHOW)]
    public function show(RentalVehicleInspection $rentalVehicleInspection): Response
    {
        return $this->response()->withData($rentalVehicleInspection)->respond();
    }

    #[PermissionAction(PermissionAction::EDIT)]
    public function edit(RentalVehicleInspection $rentalVehicleInspection): Response
    {
        $this->options();
        $this->response()->withExtras(
            RentalDocTpl::options(function (Builder $query) {
                $query->where('dt.dt_type', '=', DtDtType::RENTAL_VEHICLE_INSPECTION);
            }),
            RpPayStatus::options(),
            RentalPaymentAccount::options(),
            Admin::optionsWithRoles(),
        );

        $rentalVehicleInspection->load('RentalVehicle', 'RentalSaleOrder', 'RentalSaleOrder.RentalCustomer', 'RentalSaleOrder.RentalVehicle', 'RentalPayment', 'RentalPayment.RentalPaymentAccount');

        return $this->response()->withData($rentalVehicleInspection)->respond();
    }

    #[PermissionAction(PermissionAction::DOC)]
    public function doc(Request $request, RentalVehicleInspection $rentalVehicleInspection, DocTplService $docTplService)
    {
        $input = $request->validate([
            'mode'  => ['required', Rule::in(DtDtExportType::label_keys())],
            'dt_id' => ['required', Rule::exists(RentalDocTpl::class)->where('dt_type', DtDtType::RENTAL_VEHICLE_INSPECTION)->where('dt_status', DtDtStatus::ENABLED)],
        ]);

        $rentalVehicleInspection->load('RentalVehicle', 'RentalSaleOrder', 'RentalSaleOrder.RentalCustomer');

        $rentalDocTpl = RentalDocTpl::query()->find($input['dt_id']);

        $url = $docTplService->GenerateDoc($rentalDocTpl, $input['mode'], $rentalVehicleInspection);

        return $this->response()->withData($url)->respond();
    }

    #[PermissionAction(PermissionAction::EDIT)]
    public function update(Request $request, ?RentalVehicleInspection $rentalVehicleInspection): Response
    {
        // 创建验证器实例
        $validator = Validator::make(
            $request->all(),
            [
                'so_id'                 => ['bail', Rule::requiredIf($request->boolean('add_should_pay')), 'nullable', 'integer'],
                'inspection_type'       => ['bail', 'required', 'string', Rule::in(ViInspectionType::label_keys())],
                'policy_copy'           => ['bail', 'nullable', Rule::in(Exist::label_keys())],
                'driving_license'       => ['bail', 'nullable', Rule::in(Exist::label_keys())],
                'operation_license'     => ['bail', 'nullable', Rule::in(Exist::label_keys())],
                'vehicle_damage_status' => ['bail', 'nullable', Rule::in(ViVehicleDamageStatus::label_keys())],
                'inspection_datetime'   => ['bail', 'required', 'date'],
                'vi_mileage'            => ['bail', 'required', 'integer', 'min:0'],
                'processed_by'          => ['bail', 'nullable', 'integer', Rule::exists(Admin::class, 'id')],
                'damage_deduction'      => ['bail', 'nullable', 'decimal:0,2', 'gte:0'],
                'vi_remark'             => ['bail', 'nullable', 'string'],
                'add_should_pay'        => ['bail', 'nullable', 'boolean'],

                'inspection_info'               => ['bail', 'nullable', 'array'],
                'inspection_info.*.description' => ['bail', 'nullable', 'string'],

                'rental_payment.pt_id'             => ['bail', Rule::requiredIf($request->boolean('add_should_pay')), Rule::in(RpPtId::VEHICLE_DAMAGE)],
                'rental_payment.should_pay_date'   => ['bail', Rule::requiredIf($request->boolean('add_should_pay')), 'nullable', 'date'],
                'rental_payment.should_pay_amount' => ['bail', Rule::requiredIf($request->boolean('add_should_pay')), 'nullable', 'numeric'],
                'rental_payment.rp_remark'         => ['bail', 'nullable', 'string'],
            ]
            + Uploader::validator_rule_upload_array('additional_photos')
            + Uploader::validator_rule_upload_array('inspection_info.*.info_photos'),
            [],
            trans_property(RentalVehicleInspection::class) + Arr::dot(['rental_payment' => trans_property(RentalPayment::class)])
        )->after(function (\Illuminate\Validation\Validator $validator) use ($rentalVehicleInspection, $request, &$rentalVehicle, &$rentalCustomer) {
            if (!$validator->failed()) {
                if (null === $request->input('vi_id')) {
                    // rental_order
                    if ($so_id = $request->input('so_id')) {
                        /** @var RentalSaleOrder $rental_order */
                        $rental_order = RentalSaleOrder::query()->find($so_id);
                        if (!$rental_order) {
                            $validator->errors()->add('so_id', 'The rental_order does not exist.');

                            return;
                        }

                        if (!$rental_order->check_order_status([SoOrderStatus::SIGNED], $validator)) {
                            return;
                        }

                        /** @var RentalVehicle $rentalVehicle */
                        $rentalVehicle = $rental_order->RentalVehicle;
                        if (!$rentalVehicle) {
                            $validator->errors()->add('ve_id', 'The vehicle does not exist.');

                            return;
                        }

                        $pass = $rentalVehicle->check_status(VeStatusService::YES, [VeStatusRental::RENTED], [ViInspectionType::DISPATCH == $request->input('inspection_type') ? VeStatusDispatch::NOT_DISPATCHED : VeStatusDispatch::DISPATCHED], $validator);
                        if (!$pass) {
                            return;
                        }
                    }

                    // 不能关闭财务记录的判断：修改状态 + 收付款数据存在 + 收付款数据为已支付 + 当前是要关闭。
                    if (null !== $rentalVehicleInspection) { // 当是修改逻辑
                        $RentalPayment = $rentalVehicleInspection->RentalPayment;
                        if ($RentalPayment->exists && RpPayStatus::PAID === $RentalPayment->pay_status->value) {
                            if (!$request->boolean('add_should_pay')) {
                                $validator->errors()->add('RentalPayment', '关联的支付已经支付，不能关闭财务记录。');
                            }
                        }
                    }
                }
            }
        });

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $input = $validator->validated();

        if ($input['so_id']) {
            $input['rental_payment']['so_id'] = $input['so_id'];
        }

        DB::transaction(function () use (&$input, &$rentalVehicleInspection, &$rentalVehicle) {
            /** @var RentalVehicleInspection $rentalVehicleInspection */
            if (null === $rentalVehicleInspection) {
                $input['ve_id'] = $rentalVehicle['ve_id'];

                $rentalVehicleInspection = RentalVehicleInspection::query()->updateOrCreate(
                    array_intersect_key($input, array_flip(['so_id', 've_id', 'inspection_type', 'inspection_datetime'])),
                    $input
                );

                switch ($rentalVehicleInspection->inspection_type) {
                    case ViInspectionType::DISPATCH:
                        $rentalVehicleInspection->RentalVehicle->updateStatus(
                            status_dispatch: VeStatusDispatch::DISPATCHED
                        );

                        RentalVehicleUsage::query()->updateOrCreate(
                            [
                                'so_id'       => $rentalVehicleInspection->so_id,
                                've_id'       => $rentalVehicleInspection->ve_id,
                                'start_vi_id' => $rentalVehicleInspection->vi_id,
                            ],
                            [
                                'end_vi_id' => null,
                            ]
                        );

                        break;

                    case ViInspectionType::RETURN:
                        $update_ve = $rentalVehicleInspection->RentalVehicle->updateStatus(
                            status_dispatch: VeStatusDispatch::NOT_DISPATCHED
                        );

                        $update_vu = RentalVehicleUsage::query()->where(
                            [
                                'so_id' => $rentalVehicleInspection->so_id,
                                've_id' => $rentalVehicleInspection->ve_id,
                            ]
                        )
                            ->whereNull('end_vi_id')
                            ->orderByDesc('vu_id')
                            ->first()
                            ?->update([
                                'end_vi_id' => $rentalVehicleInspection->vi_id,
                            ])
                        ;

                        break;

                    default:
                        break;
                }

                if ($rentalVehicleInspection->add_should_pay) {
                    $rentalVehicleInspection->RentalPayment()->create($input['rental_payment']);
                }
            } else {
                $rentalVehicleInspection->update($input);

                if ($rentalVehicleInspection->add_should_pay) {
                    $RentalPayment = $rentalVehicleInspection->RentalPayment;
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
                        $rentalVehicleInspection->RentalPayment()->create($input['rental_payment']);
                    }
                } else {
                    $rentalVehicleInspection->RentalPayment()->where('pay_status', '=', RpPayStatus::UNPAID)->update(
                        [
                            'is_valid' => RpIsValid::INVALID,
                        ]
                    );
                }
            }
        });

        return $this->response()->withData($rentalVehicleInspection)->respond();
    }

    #[PermissionAction(PermissionAction::DELETE)]
    public function destroy(RentalVehicleInspection $rentalVehicleInspection): Response
    {
        $rentalVehicleInspection->delete();

        return $this->response()->withData($rentalVehicleInspection)->respond();
    }

    #[PermissionAction(PermissionAction::ADD)]
    public function rentalSaleOrdersOption(Request $request): Response
    {
        $validator = Validator::make(
            $request->all(),
            [
                'inspection_type' => ['bail', 'required', 'string', Rule::in(ViInspectionType::label_keys())],
            ]
        );

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $input = $validator->validated();

        $this->response()->withExtras(
            match ($input['inspection_type']) {
                ViInspectionType::DISPATCH => RentalSaleOrder::options(
                    where: function (Builder $builder) {
                        $builder->whereIn('ve.status_rental', [VeStatusRental::RENTED])
                            ->whereIn('ve.status_dispatch', [VeStatusDispatch::NOT_DISPATCHED])
                            ->whereIn('so.order_status', [SoOrderStatus::SIGNED])
                        ;
                    }
                ),
                ViInspectionType::RETURN => RentalSaleOrder::options(
                    where: function (Builder $builder) {
                        $builder->whereIn('ve.status_rental', [VeStatusRental::RENTED])
                            ->whereIn('ve.status_dispatch', [VeStatusDispatch::DISPATCHED])
                            ->whereIn('so.order_status', [SoOrderStatus::SIGNED])
                        ;
                    }
                ),
            }
        );

        return $this->response()->respond();
    }

    #[PermissionAction(PermissionAction::ADD)]
    #[PermissionAction(PermissionAction::EDIT)]
    public function upload(Request $request): Response
    {
        return Uploader::upload($request, 'vehicle_inspection', ['additional_photos', 'info_photos'], $this);
    }

    protected function options(?bool $with_group_count = false): void
    {
        $this->response()->withExtras(
            $with_group_count ? ViInspectionType::options_with_count(RentalVehicleInspection::class) : ViInspectionType::options(),
            ViVehicleDamageStatus::options(),
            Exist::options(),
        );
    }
}
