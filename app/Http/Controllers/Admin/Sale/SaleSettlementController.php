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
use App\Models\Payment\Payment;
use App\Models\Sale\DocTpl;
use App\Models\Sale\SaleOrder;
use App\Models\Sale\SaleSettlement;
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
class SaleSettlementController extends Controller
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

        $query   = SaleSettlement::indexQuery();
        $columns = SaleSettlement::indexColumns();

        $paginate = new PaginateService(
            [],
            [['ss.ss_id', 'desc']],
            ['kw', 'rs_return_datetime', 'rs_return_status'],
            []
        );

        $paginate->paginator(
            $query,
            $request,
            [
                'kw__func' => function ($value, Builder $builder) {
                    $builder->where(function (Builder $builder) use ($value) {
                        $builder->where('ve.plate_no', 'like', '%'.$value.'%')
                            ->orWhere('cu.contact_name', 'like', '%'.$value.'%')
                            ->orWhere('cu.contact_phone', 'like', '%'.$value.'%')
                            ->orWhere('ss.ss_remark', 'like', '%'.$value.'%')
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
        $this->response()->withExtras();

        $saleOrder = null;

        $validator = Validator::make(
            $request->all(),
            [
                'so_id' => ['required', 'integer'],
            ],
            [],
            trans_property(SaleSettlement::class)
        )
            ->after(function ($validator) use ($request, &$saleOrder) {
                if (!$validator->failed()) {
                    /** @var SaleOrder $saleOrder */
                    $saleOrder = SaleOrder::query()->findOrFail($request->input('so_id'));
                }
            })
        ;

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $input = $validator->validated();

        $saleSettlement = $saleOrder->SaleSettlement;
        if (!$saleSettlement) {
            /** @var Payment $payment */
            $payment = $saleOrder->Payments()->where('pt_id', '=', RpPtId::DEPOSIT)->first();

            $saleSettlement = new SaleSettlement([
                'so_id'                      => $saleOrder->so_id,
                'deposit_amount'             => $payment->should_pay_amount ?? 0, // 押金应收金额
                'received_deposit'           => $payment->actual_pay_amount ?? 0, // 押金实收金额
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
                DocTpl::options(function (Builder $query) {
                    $query->where('dt.dt_type', '=', DtDtType::RENTAL_SETTLEMENT);
                }),
                Admin::optionsWithRoles(),
            );
        }

        return $this->response()->withData($saleSettlement)->respond();
    }

    #[PermissionAction(PermissionAction::DOC)]
    public function doc(Request $request, SaleSettlement $saleSettlement, DocTplService $docTplService)
    {
        $input = $request->validate([
            'mode'  => ['required', Rule::in(DtDtExportType::label_keys())],
            'dt_id' => ['required', Rule::exists(DocTpl::class)->where('dt_type', DtDtType::RENTAL_SETTLEMENT)->where('dt_status', DtDtStatus::ENABLED)],
        ]);

        $docTpl = DocTpl::query()->find($input['dt_id']);

        $url = $docTplService->GenerateDoc($docTpl, $input['mode'], $saleSettlement);

        return $this->response()->withData($url)->respond();
    }

    #[PermissionAction(PermissionAction::ADD)]
    public function store(Request $request): Response
    {
        return $this->update($request, null);
    }

    #[PermissionAction(PermissionAction::SHOW)]
    public function show(Request $request, SaleSettlement $saleSettlement): Response
    {
        return $this->response()->withData($saleSettlement)->respond();
    }

    #[PermissionAction(PermissionAction::EDIT)]
    public function edit(SaleSettlement $saleSettlement): Response
    {
        $this->options();
        $this->response()->withExtras(
        );

        return $this->response()->withData($saleSettlement)->respond();
    }

    #[PermissionAction(PermissionAction::EDIT)]
    public function update(Request $request, ?SaleSettlement $saleSettlement): Response
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
            trans_property(SaleSettlement::class)
        )->after(function ($validator) use ($request, &$saleSettlement, &$saleOrder) {
            if (!$validator->failed()) {
                // 计算结算费
                $result = '0';
                foreach (SaleSettlement::calcOpts as $key => $opt) {
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

                if ($saleSettlement) { // 修改的时候
                    if (SsReturnStatus::CONFIRMED === $saleSettlement->return_status) {
                        $validator->errors()->add('return_status', '已审核，不能被修改');
                    }
                }

                $saleOrder = SaleOrder::query()->findOrFail($request->input('so_id'));

                if (!$saleOrder->check_order_status([SoOrderStatus::SIGNED], $validator)) {
                    return;
                }

                // vehicle
                $vehicle = $saleOrder->Vehicle;

                $pass = $vehicle->check_status(VeStatusService::YES, [VeStatusRental::RENTED], [], $validator);
                if (!$pass) {
                    return;
                }
            }
        });

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $input = $validator->validated();

        DB::transaction(function () use (&$input, &$saleSettlement) {
            $_saleSettlement = SaleSettlement::query()->updateOrCreate(
                array_intersect_key($input, array_flip(['so_id'])),
                $input + ['return_status' => SsReturnStatus::UNCONFIRMED],
            );

            $saleSettlement = $_saleSettlement;
        });

        return $this->response()->withData($saleSettlement)->respond();
    }

    public function destroy(SaleSettlement $saleSettlement) {}

    #[PermissionAction(PermissionAction::ADD)]
    #[PermissionAction(PermissionAction::EDIT)]
    public function upload(Request $request): Response
    {
        return Uploader::upload($request, 'sale_settlement', ['additional_photos'], $this);
    }

    #[PermissionAction(PermissionAction::APPROVE)]
    public function approve(Request $request, SaleSettlement $saleSettlement): Response
    {
        $validator = Validator::make(
            $request->all(),
            [
            ],
            [],
            trans_property(Payment::class)
        )
            ->after(function (\Illuminate\Validation\Validator $validator) use ($saleSettlement) {
                if (!$validator->failed()) {
                    if (SsReturnStatus::CONFIRMED === $saleSettlement->return_status) {
                        $validator->errors()->add('return_status', '不能重复审核');
                    }
                }
            })
        ;
        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        //        $input = $validator->validated();

        $saleOrder = $saleSettlement->SaleOrder;

        $unPayCount = Payment::query()
            ->where('so_id', '=', $saleOrder->so_id)
            ->where('is_valid', '=', RpIsValid::VALID)
            ->where('pay_status', '=', RpPayStatus::UNPAID)
            ->where('pt_id', '!=', RpPtId::VEHICLE_RETURN_SETTLEMENT_FEE)
            ->count()
        ;

        DB::transaction(function () use ($saleOrder, $unPayCount, $saleSettlement) {
            $saleOrder->update([
                'order_status'                                            => $unPayCount > 0 ? SoOrderStatus::EARLY_TERMINATION : SoOrderStatus::COMPLETED,
                $unPayCount > 0 ? 'early_termination_at' : 'completed_at' => now(),
            ]);

            $saleOrder->Vehicle->updateStatus(status_rental: VeStatusRental::PENDING);

            switch ($saleSettlement->delete_option) {
                case RsDeleteOption::DELETE:
                    Payment::query()
                        ->where('so_id', '=', $saleOrder->so_id)
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

            if ($saleSettlement->settlement_amount > 0 || $saleSettlement->deposit_return_amount > 0) {
                Payment::query()->updateOrCreate([
                    'so_id' => $saleOrder->so_id,
                    'pt_id' => $saleSettlement->deposit_return_amount > 0 ? RpPtId::REFUND_DEPOSIT : RpPtId::VEHICLE_RETURN_SETTLEMENT_FEE,
                ], [
                    'should_pay_date'   => $saleSettlement->deposit_return_date,
                    'should_pay_amount' => bccomp($saleSettlement->deposit_return_amount, '0', 2) > 0 ? '-'.$saleSettlement->deposit_return_amount : $saleSettlement->settlement_amount,
                    'ss_remark'         => (function () use ($saleSettlement): string {
                        $remark_array = array_combine(
                            array_intersect_key(trans_property(SaleSettlement::class), array_flip(array_keys(SaleSettlement::calcOpts))),
                            array_intersect_key($saleSettlement->toArray(), array_flip(array_keys(SaleSettlement::calcOpts)))
                        );
                        $remark_array = array_filter($remark_array, fn ($v) => 0.0 != floatval($v));

                        return implode(';', array_map(fn ($key, $value) => "{$key}:{$value}", array_keys($remark_array), $remark_array));
                    })(),
                ]);
            }
            //                Payment::query()->where([
            //                    'so_id' => $saleOrder->so_id,
            //                ])->delete();

            $saleSettlement->update([
                'return_status' => SsReturnStatus::CONFIRMED,
                'approved_by'   => Auth::id(),
                'approved_at'   => now(),
            ]);
        });

        return $this->response()->withData($saleSettlement)->withMessages(message_success(__METHOD__))->respond();
    }

    protected function options(?bool $with_group_count = false): void
    {
        $this->response()->withExtras(
            RsDeleteOption::options(),
            $with_group_count ? SsReturnStatus::options_with_count(SaleSettlement::class) : SsReturnStatus::options(),
        );
    }
}
