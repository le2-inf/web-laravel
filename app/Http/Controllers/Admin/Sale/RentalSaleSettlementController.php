<?php

namespace App\Http\Controllers\Admin\Sale;

use App\Attributes\PermissionAction;
use App\Attributes\PermissionType;
use App\Enum\Rental\DtDtExportType;
use App\Enum\Rental\DtDtStatus;
use App\Enum\Rental\DtDtType;
use App\Enum\Rental\RpIsValid;
use App\Enum\Rental\RpPayStatus;
use App\Enum\Rental\RpPtId;
use App\Enum\Rental\RsDeleteOption;
use App\Enum\Rental\SoOrderStatus;
use App\Enum\Rental\SsReturnStatus;
use App\Enum\Vehicle\VeStatusRental;
use App\Enum\Vehicle\VeStatusService;
use App\Http\Controllers\Controller;
use App\Models\Admin\Admin;
use App\Models\Rental\Payment\RentalPayment;
use App\Models\Rental\Sale\RentalDocTpl;
use App\Models\Rental\Sale\RentalSaleOrder;
use App\Models\Rental\Sale\RentalSaleSettlement;
use App\Services\DocTplService;
use App\Services\PaginateService;
use App\Services\Uploader;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

#[PermissionType('退车结算管理')]
class RentalSaleSettlementController extends Controller
{
    public static function labelOptions(Controller $controller): void
    {
        $controller->response()->withExtras(
            RsDeleteOption::labelOptions(),
        );
    }

    #[PermissionAction(PermissionAction::INDEX)]
    public function index(Request $request): Response
    {
        $this->options(true);
        $this->response()->withExtras(
        );

        $query = RentalSaleSettlement::indexQuery();

        $paginate = new PaginateService(
            [],
            [['ss.ss_id', 'desc']],
            ['kw', 'rs_return_datetime', 'rs_return_status'],
            []
        );

        $paginate->paginator($query, $request, [
            'kw__func' => function ($value, Builder $builder) {
                $builder->where(function (Builder $builder) use ($value) {
                    $builder->where('ve.plate_no', 'like', '%'.$value.'%')
                        ->orWhere('cu.contact_name', 'like', '%'.$value.'%')
                        ->orWhere('cu.contact_phone', 'like', '%'.$value.'%')
                        ->orWhere('ss.ss_remark', 'like', '%'.$value.'%')
                    ;
                });
            },
        ]);

        return $this->response()->withData($paginate)->respond();
    }

    #[PermissionAction(PermissionAction::ADD)]
    public function create(Request $request): Response
    {
        $this->options();
        $this->response()->withExtras();

        $rentalSaleOrder = null;

        $validator = Validator::make(
            $request->all(),
            [
                'so_id' => ['required', 'integer'],
            ],
            [],
            trans_property(RentalSaleSettlement::class)
        )
            ->after(function ($validator) use ($request, &$rentalSaleOrder) {
                if (!$validator->failed()) {
                    /** @var RentalSaleOrder $rentalSaleOrder */
                    $rentalSaleOrder = RentalSaleOrder::query()->findOrFail($request->input('so_id'));
                }
            })
        ;

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $input = $validator->validated();

        $rentalSaleSettlement = $rentalSaleOrder->RentalSaleSettlement;
        if (!$rentalSaleSettlement) {
            /** @var RentalPayment $rentalPayment */
            $rentalPayment = $rentalSaleOrder->RentalPayments()->where('pt_id', '=', RpPtId::DEPOSIT)->first();

            $rentalSaleSettlement = new RentalSaleSettlement([
                'so_id'                      => $rentalSaleOrder->so_id,
                'deposit_amount'             => $rentalPayment->should_pay_amount ?? 0, // 押金应收金额
                'received_deposit'           => $rentalPayment->actual_pay_amount ?? 0, // 押金实收金额
                'early_return_penalty'       => '0',
                'overdue_inspection_penalty' => '0',
                'overdue_rent'               => '0',
                'overdue_penalty'            => '0',
                'accident_depreciation_fee'  => '0',
                'insurance_surcharge'        => '0',
                'violation_withholding_fee'  => '0',
                'violation_penalty'          => '0',
                'repair_fee'                 => '0',
                'insurance_deductible'       => '0',
                'forced_collection_fee'      => '0',
                'other_deductions'           => '0',
                'refund_amount'              => '0',
                'settlement_amount'          => '0',
                'return_datetime'            => now()->format('Y-m-d H:i:00'),
                'delete_option'              => RsDeleteOption::DELETE,
                'processed_by'               => Auth::id(),
            ]);

            $this->response()->withExtras(
                Admin::optionsWithRoles(),
            );
        } else {
            $this->response()->withExtras(
                RentalDocTpl::options(function (Builder $query) {
                    $query->where('dt.dt_type', '=', DtDtType::RENTAL_SETTLEMENT);
                }),
                Admin::optionsWithRoles(),
            );
        }

        return $this->response()->withData($rentalSaleSettlement)->respond();
    }

    #[PermissionAction(PermissionAction::DOC)]
    public function doc(Request $request, RentalSaleSettlement $rentalSaleSettlement, DocTplService $docTplService)
    {
        $input = $request->validate([
            'mode'  => ['required', Rule::in(DtDtExportType::label_keys())],
            'dt_id' => ['required', Rule::exists(RentalDocTpl::class)->where('dt_type', DtDtType::RENTAL_SETTLEMENT)->where('dt_status', DtDtStatus::ENABLED)],
        ]);

        $rentalDocTpl = RentalDocTpl::query()->find($input['dt_id']);

        $url = $docTplService->GenerateDoc($rentalDocTpl, $input['mode'], $rentalSaleSettlement);

        return $this->response()->withData($url)->respond();
    }

    #[PermissionAction(PermissionAction::ADD)]
    public function store(Request $request): Response
    {
        return $this->update($request, null);
    }

    #[PermissionAction(PermissionAction::SHOW)]
    public function show(Request $request, RentalSaleSettlement $rentalSaleSettlement): Response
    {
        return $this->response()->withData($rentalSaleSettlement)->respond();
    }

    #[PermissionAction(PermissionAction::EDIT)]
    public function edit(RentalSaleSettlement $rentalSaleSettlement): Response
    {
        $this->options();
        $this->response()->withExtras(
        );

        return $this->response()->withData($rentalSaleSettlement)->respond();
    }

    #[PermissionAction(PermissionAction::EDIT)]
    public function update(Request $request, ?RentalSaleSettlement $rentalSaleSettlement): Response
    {
        $validator = Validator::make(
            $request->all(),
            [
                'so_id'                      => ['required', 'integer'],
                'deposit_amount'             => ['nullable', 'numeric'],
                'received_deposit'           => ['nullable', 'numeric'],
                'early_return_penalty'       => ['nullable', 'numeric'],
                'overdue_inspection_penalty' => ['nullable', 'numeric'],
                'overdue_rent'               => ['nullable', 'numeric'],
                'overdue_penalty'            => ['nullable', 'numeric'],
                'accident_depreciation_fee'  => ['nullable', 'numeric'],
                'insurance_surcharge'        => ['nullable', 'numeric'],
                'violation_withholding_fee'  => ['nullable', 'numeric'],
                'violation_penalty'          => ['nullable', 'numeric'],
                'repair_fee'                 => ['nullable', 'numeric'],
                'insurance_deductible'       => ['nullable', 'numeric'],
                'forced_collection_fee'      => ['nullable', 'numeric'],
                'other_deductions'           => ['nullable', 'numeric'],
                'other_deductions_remark'    => ['nullable', 'string'],
                'refund_amount'              => ['nullable', 'numeric'],
                'refund_details'             => ['nullable', 'string'],
                'settlement_amount'          => ['nullable', 'numeric'],
                'deposit_return_amount'      => ['nullable', 'numeric'],
                'deposit_return_date'        => ['nullable', 'date'],
                'return_datetime'            => ['required', 'date'],
                'delete_option'              => ['required', Rule::in(RsDeleteOption::label_keys())],
                'ss_remark'                  => ['nullable', 'string'],
                'processed_by'               => ['bail', 'nullable', 'integer', Rule::exists(Admin::class, 'id')],
            ]
            + Uploader::validator_rule_upload_array('additional_photos'),
            [],
            trans_property(RentalSaleSettlement::class)
        )->after(function ($validator) use ($request, &$rentalSaleSettlement, &$rentalSaleOrder) {
            // @var RentalSaleOrder $rentalSaleOrder

            if (!$validator->failed()) {
                // 计算结算费
                $result = '0';
                foreach (RentalSaleSettlement::calcOpts as $key => $opt) {
                    $value  = $request->input($key);
                    $result = bcadd($result, $opt.$value, 2);
                }

                if (bccomp($result, '0', 2) > 0) {
                    if (0 !== bccomp($request->input(['settlement_amount']), $result, 2)) {
                        $validator->errors()->add('settlement_amount', '结算费计算错误');

                        return;
                    }
                }

                if (bccomp($result, '0', 2) < 0) {
                    if (0 !== bccomp($request->input(['deposit_return_amount']), bcmul($result, '-1', 2), 2)) {
                        $validator->errors()->add('deposit_return_amount', '应退押金金额计算错误');

                        return;
                    }
                }

                if ($rentalSaleSettlement) { // 修改的时候
                    if (SsReturnStatus::CONFIRMED === $rentalSaleSettlement->return_status) {
                        $validator->errors()->add('return_status', '已审核，不能被修改');
                    }
                }

                $rentalSaleOrder = RentalSaleOrder::query()->findOrFail($request->input('so_id'));

                if (!$rentalSaleOrder->check_order_status([SoOrderStatus::SIGNED], $validator)) {
                    return;
                }

                // vehicle
                $rentalVehicle = $rentalSaleOrder->RentalVehicle;

                $pass = $rentalVehicle->check_status(VeStatusService::YES, [VeStatusRental::RENTED], [], $validator);
                if (!$pass) {
                    return;
                }
            }
        });

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $input = $validator->validated();

        DB::transaction(function () use (&$input, &$rentalSaleSettlement) {
            $_rentalSaleSettlement = RentalSaleSettlement::query()->updateOrCreate(
                array_intersect_key($input, array_flip(['so_id'])),
                $input + ['return_status' => SsReturnStatus::UNCONFIRMED],
            );

            $rentalSaleSettlement = $_rentalSaleSettlement;
        });

        return $this->response()->withData($rentalSaleSettlement)->respond();
    }

    public function destroy(RentalSaleSettlement $rentalSaleSettlement) {}

    #[PermissionAction(PermissionAction::ADD)]
    #[PermissionAction(PermissionAction::EDIT)]
    public function upload(Request $request): Response
    {
        return Uploader::upload($request, 'rental_settlement', ['additional_photos'], $this);
    }

    #[PermissionAction(PermissionAction::APPROVE)]
    public function approve(Request $request, RentalSaleSettlement $rentalSaleSettlement): Response
    {
        $validator = Validator::make(
            $request->all(),
            [
            ],
            [],
            trans_property(RentalPayment::class)
        )
            ->after(function (\Illuminate\Validation\Validator $validator) use ($rentalSaleSettlement) {
                if (!$validator->failed()) {
                    if (SsReturnStatus::CONFIRMED === $rentalSaleSettlement->return_status) {
                        $validator->errors()->add('return_status', '不能重复审核');
                    }
                }
            })
        ;
        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        //        $input = $validator->validated();

        $rentalSaleOrder = $rentalSaleSettlement->RentalSaleOrder;

        $unPayCount = RentalPayment::query()
            ->where('so_id', '=', $rentalSaleOrder->so_id)
            ->where('is_valid', '=', RpIsValid::VALID)
            ->where('pay_status', '=', RpPayStatus::UNPAID)
            ->where('pt_id', '!=', RpPtId::VEHICLE_RETURN_SETTLEMENT_FEE)
            ->count()
        ;

        DB::transaction(function () use ($rentalSaleOrder, $unPayCount, $rentalSaleSettlement) {
            $rentalSaleOrder->update([
                'order_status'                                            => $unPayCount > 0 ? SoOrderStatus::EARLY_TERMINATION : SoOrderStatus::COMPLETED,
                $unPayCount > 0 ? 'early_termination_at' : 'completed_at' => now(),
            ]);

            $rentalSaleOrder->RentalVehicle->updateStatus(status_rental: VeStatusRental::PENDING);

            switch ($rentalSaleSettlement->delete_option) {
                case RsDeleteOption::DELETE:
                    RentalPayment::query()
                        ->where('so_id', '=', $rentalSaleOrder->so_id)
                        ->where('pay_status', '=', RpPayStatus::UNPAID)
                        ->where('pt_id', '!=', RpPtId::VEHICLE_RETURN_SETTLEMENT_FEE)
                        ->update([
                            'is_valid' => RpIsValid::INVALID,
                        ])
                    ;

                    break;

                case RsDeleteOption::DO_NOT_DELETE:
                default:
                    break;
            }

            if ($rentalSaleSettlement->settlement_amount > 0 || $rentalSaleSettlement->deposit_return_amount > 0) {
                RentalPayment::query()->updateOrCreate([
                    'so_id' => $rentalSaleOrder->so_id,
                    'pt_id' => $rentalSaleSettlement->deposit_return_amount > 0 ? RpPtId::REFUND_DEPOSIT : RpPtId::VEHICLE_RETURN_SETTLEMENT_FEE,
                ], [
                    'should_pay_date'   => $rentalSaleSettlement->deposit_return_date,
                    'should_pay_amount' => bccomp($rentalSaleSettlement->deposit_return_amount, '0', 2) > 0 ? '-'.$rentalSaleSettlement->deposit_return_amount : $rentalSaleSettlement->settlement_amount,
                    'ss_remark'         => (function () use ($rentalSaleSettlement): string {
                        $remark_array = array_combine(
                            array_intersect_key(trans_property(RentalSaleSettlement::class), array_flip(array_keys(RentalSaleSettlement::calcOpts))),
                            array_intersect_key($rentalSaleSettlement->toArray(), array_flip(array_keys(RentalSaleSettlement::calcOpts)))
                        );
                        $remark_array = array_filter($remark_array, fn ($v) => 0.0 != floatval($v));

                        return implode(';', array_map(fn ($key, $value) => "{$key}:{$value}", array_keys($remark_array), $remark_array));
                    })(),
                ]);
            }
            //                RentalPayment::query()->where([
            //                    'so_id' => $rentalSaleOrder->so_id,
            //                ])->delete();

            $rentalSaleSettlement->update([
                'return_status' => SsReturnStatus::CONFIRMED,
                'approved_by'   => Auth::id(),
                'approved_at'   => now(),
            ]);
        });

        return $this->response()->withData($rentalSaleSettlement)->withMessages(message_success(__METHOD__))->respond();
    }

    protected function options(?bool $with_group_count = false): void
    {
        $this->response()->withExtras(
            RsDeleteOption::options(),
            $with_group_count ? SsReturnStatus::options_with_count(RentalSaleSettlement::class) : SsReturnStatus::options(),
        );
    }
}
