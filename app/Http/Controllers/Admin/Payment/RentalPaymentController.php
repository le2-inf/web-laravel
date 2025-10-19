<?php

namespace App\Http\Controllers\Admin\Payment;

use App\Attributes\PermissionAction;
use App\Attributes\PermissionType;
use App\Enum\Rental\DtDtExportType;
use App\Enum\Rental\DtDtStatus;
use App\Enum\Rental\DtDtType;
use App\Enum\Rental\PaPaStatus;
use App\Enum\Rental\RpIsValid;
use App\Enum\Rental\RpPayStatus;
use App\Enum\Rental\RpPtId;
use App\Enum\Rental\SoOrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Rental\Payment\RentalPayment;
use App\Models\Rental\Payment\RentalPaymentAccount;
use App\Models\Rental\Payment\RentalPaymentType;
use App\Models\Rental\Sale\RentalDocTpl;
use App\Models\Rental\Sale\RentalSaleOrder;
use App\Services\DocTplService;
use App\Services\PaginateService;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

#[PermissionType('收付款管理')]
class RentalPaymentController extends Controller
{
    public static function labelOptions(Controller $controller): void
    {
        $controller->response()->withExtras(
            RpPayStatus::labelOptions(),
            RpIsValid::labelOptions(),
        );
    }

    #[PermissionAction(PermissionAction::INDEX)]
    public function index(Request $request): Response
    {
        $this->options(true);
        $this->response()->withExtras(
        );

        $query   = RentalPayment::indexQuery();
        $columns = RentalPayment::indexColumns();

        $paginate = new PaginateService(
            [],
            [],
            ['kw', 'rp_pt_id', 'rp_pay_status', 'rp_is_valid', 'rp_should_pay_date', 'rp_actual_pay_date'],
            []
        );

        $paginate->paginator(
            $query,
            $request,
            [
                'kw__func' => function ($value, Builder $builder) {
                    $builder->where(function (Builder $builder) use ($value) {
                        $builder->whereLike('so.contract_number', '%'.$value.'%')
                            ->orWhereLike('rp.rp_remark', '%'.$value.'%')
                            ->orWhereLike('ve.plate_no', '%'.$value.'%')
                            ->orWhereLike('cu.contact_name', '%'.$value.'%')
                            ->orWhereLike('cu.contact_phone', '%'.$value.'%')
                        ;
                    });
                },
            ],
            $columns
        );

        return $this->response()->withData($paginate)->respond();
    }

    #[PermissionAction(PermissionAction::ADD)]
    public function create(Request $request)
    {
        $this->options();
        $this->response()->withExtras(
            RentalSaleOrder::options(
                where: function (Builder $builder) {
                    $builder->whereIn('so.order_status', [SoOrderStatus::PENDING, SoOrderStatus::SIGNED]);
                }
            ),
        );

        return $this->edit(null);
    }

    #[PermissionAction(PermissionAction::ADD)]
    public function store(Request $request)
    {
        return $this->update($request, null);
    }

    #[PermissionAction(PermissionAction::SHOW)]
    public function show(RentalPayment $rentalPayment): Response
    {
        $rentalPayment->load(['RentalSaleOrder', 'RentalPaymentType', 'RentalSaleOrder.RentalCustomer']);

        return $this->response()->withData($rentalPayment)->respond();
    }

    #[PermissionAction(PermissionAction::SHOW)]
    public function shows(string $id): Response
    {
        $idsArray = explode(',', $id); // 将 $id 按照逗号分割成数组

        $rentalPayments = RentalPayment::query()->whereIn('rp_id', $idsArray)->with(['RentalSaleOrder', 'RentalPaymentType', 'RentalSaleOrder.RentalCustomer'])->get();

        return $this->response()->withData($rentalPayments)->respond();
    }

    #[PermissionAction(PermissionAction::EDIT)]
    public function edit(?RentalPayment $rentalPayment): Response
    {
        $this->options();
        $this->response()->withExtras(
            RentalPaymentAccount::options(),
            RentalDocTpl::options(function (Builder $query) {
                $query->where('dt.dt_type', '=', DtDtType::RENTAL_PAYMENT);
            })
        );

        if ($rentalPayment) {
            $rentalPayment->load(['RentalSaleOrder', 'RentalPaymentType', 'RentalSaleOrder.RentalCustomer']);
        }

        return $this->response()->withData($rentalPayment)->respond();
    }

    #[PermissionAction(PermissionAction::DOC)]
    public function doc(Request $request, RentalPayment $rentalPayment, DocTplService $docTplService)
    {
        $input = $request->validate([
            'mode'  => ['required', Rule::in(DtDtExportType::label_keys())],
            'dt_id' => ['required',
                Rule::exists(RentalDocTpl::class)->where('dt_type', DtDtType::RENTAL_PAYMENT)->where('dt_status', DtDtStatus::ENABLED)],
        ]);

        $rentalPayment->load(['RentalSaleOrder', 'RentalPaymentType', 'RentalSaleOrder.RentalCustomer']); // toto 名字有变化

        $rentalDocTpl = RentalDocTpl::query()->find($input['dt_id']);

        $url = $docTplService->GenerateDoc($rentalDocTpl, $input['mode'], $rentalPayment);

        return $this->response()->withData($url)->respond();
    }

    #[PermissionAction('undo')]
    public function undo(Request $request, RentalPayment $rentalPayment): Response
    {
        $validator = Validator::make(
            $request->all(),
            [
            ],
            [],
            trans_property(RentalPayment::class)
        )
            ->after(function (\Illuminate\Validation\Validator $validator) use ($rentalPayment) {
                if (!$validator->failed()) {
                    if (RpPayStatus::PAID !== $rentalPayment->pay_status->value) {
                        $validator->errors()->add('pay_status', '未支付，无需退还');
                    }
                }
            })
        ;
        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $input = $validator->validated();

        DB::transaction(function () use (&$input, $rentalPayment) {
            $rentalPayment->update([
                'pay_status'        => RpPayStatus::UNPAID,
                'actual_pay_date'   => null,
                'actual_pay_amount' => null,
                'pa_id'             => null,
            ]);
        });

        return $this->response()->withData($rentalPayment)->respond();
    }

    #[PermissionAction(PermissionAction::EDIT)]
    public function update(Request $request, ?RentalPayment $rentalPayment): Response
    {
        $validator = Validator::make(
            $request->all(),
            [
                'so_id'             => ['bail', 'required', 'integer', Rule::exists(RentalSaleOrder::class)],
                'pt_id'             => ['bail', 'required', Rule::in(RpPtId::label_keys())],
                'should_pay_date'   => ['bail', 'required', 'date'],
                'should_pay_amount' => ['bail', 'required', 'numeric'],
                'pay_status'        => ['bail', 'required', Rule::in(RpPayStatus::label_keys())],
                'actual_pay_date'   => [
                    'bail',
                    Rule::requiredIf(RpPayStatus::PAID === $request->input('pay_status')),
                    Rule::excludeIf(RpPayStatus::UNPAID === $request->input('pay_status')),
                    'date'],
                'actual_pay_amount' => [
                    'bail',
                    Rule::requiredIf(RpPayStatus::PAID === $request->input('pay_status')),
                    Rule::excludeIf(RpPayStatus::UNPAID === $request->input('pay_status')),
                    'numeric',
                ],
                'pa_id' => [
                    'bail',
                    Rule::requiredIf(RpPayStatus::PAID === $request->input('pay_status')),
                    Rule::excludeIf(RpPayStatus::UNPAID === $request->input('pay_status')),
                    Rule::exists(RentalPaymentAccount::class)->where('pa_status', PaPaStatus::ENABLED),
                ],
                'rp_remark' => ['bail', 'nullable', 'string'],
            ],
            [],
            trans_property(RentalPayment::class)
        )
            ->after(function (\Illuminate\Validation\Validator $validator) use ($rentalPayment, $request, &$rentalVehicle, &$rentalCustomer) {
                if (!$validator->failed()) {
                    if (RpPayStatus::UNPAID === $request->input('pay_status')) {
                        if ($request->input('actual_pay_date') || $request->input('actual_pay_amount') || $request->input('pa_id')) {
                            $validator->errors()->add('pay_status', '支付状态为「未支付」时，实际收付金额、日期、收支账户不允许填入。');
                        }
                    }

                    if ($rentalPayment && $rentalPayment->exists) {
                        if (RpPayStatus::PAID == $rentalPayment->pay_status->value) {
                            if (RpPayStatus::UNPAID === $request->input('pay_status')) {
                                $validator->errors()->add('pay_status', '「已支付」状态不能改为「未支付」状态，应该使用「退回」');
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

        DB::transaction(function () use (&$input, &$rentalPayment) {
            if ($rentalPayment && $rentalPayment->exists) {
                $rentalPayment->update($input);
            } else {
                $rentalPayment = RentalPayment::query()->create($input);
            }
        });

        return $this->response()->withData($rentalPayment)->respond();
    }

    public function destroy(RentalPayment $payment) {}

    protected function options(?bool $with_group_count = false): void
    {
        $this->response()->withExtras(
            RentalPaymentType::options_with_count(),
            $with_group_count ? RpPayStatus::options_with_count(RentalPayment::class) : RpPayStatus::options(),
            $with_group_count ? RpIsValid::options_with_count(RentalPayment::class) : RpIsValid::options(),
        );
    }
}
