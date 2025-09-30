<?php

namespace App\Http\Controllers\Admin\Sale;

use App\Attributes\PermissionAction;
use App\Attributes\PermissionType;
use App\Enum\Rental\DtDtExportType;
use App\Enum\Rental\DtDtStatus;
use App\Enum\Rental\DtDtType;
use App\Enum\Rental\RpPtId;
use App\Enum\Rental\SoOrderStatus;
use App\Enum\Rental\SoPaymentDay_Month;
use App\Enum\Rental\SoPaymentDay_Week;
use App\Enum\Rental\SoRentalPaymentType;
use App\Enum\Rental\SoRentalType;
use App\Enum\Rental\SoRentalType_Short;
use App\Enum\Vehicle\VeStatusDispatch;
use App\Enum\Vehicle\VeStatusRental;
use App\Enum\Vehicle\VeStatusService;
use App\Http\Controllers\Controller;
use App\Models\Configuration;
use App\Models\Rental\Customer\RentalCustomer;
use App\Models\Rental\Payment\RentalPayment;
use App\Models\Rental\Payment\RentalPaymentType;
use App\Models\Rental\Sale\RentalDocTpl;
use App\Models\Rental\Sale\RentalSaleOrder;
use App\Models\Rental\Sale\RentalSaleOrderTpl;
use App\Models\Rental\Sale\RentalSaleSettlement;
use App\Models\Rental\Sale\RentalVehicleReplacement;
use App\Models\Rental\Vehicle\RentalVehicle;
use App\Models\Rental\Vehicle\RentalVehicleInspection;
use App\Models\Rental\Vehicle\RentalVehicleManualViolation;
use App\Models\Rental\Vehicle\RentalVehicleRepair;
use App\Models\Rental\Vehicle\RentalVehicleUsage;
use App\Models\Rental\Vehicle\RentalVehicleViolation;
use App\Rules\PaymentDayCheck;
use App\Services\DocTplService;
use App\Services\PaginateService;
use App\Services\Uploader;
use Carbon\Carbon;
use Carbon\CarbonInterval;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

#[PermissionType('订单管理')]
class RentalSaleOrderController extends Controller
{
    public static function labelOptions(Controller $controller): void
    {
        $controller->response()->withExtras(
            SoRentalType::labelOptions(),
            SoRentalType_Short::labelOptions(),
            SoRentalPaymentType::labelOptions(),
            SoPaymentDay_Month::labelOptions(),
            SoPaymentDay_Week::labelOptions(),
        );
    }

    #[PermissionAction(PermissionAction::INDEX)]
    public function index(Request $request): Response
    {
        $this->options(true);
        $this->response()->withExtras(
            RentalCustomer::options(),
            RentalVehicle::options(),
        );

        $query = RentalSaleOrder::indexQuery();

        // 如果是管理员或经理，则可以看到所有的用户；如果不是管理员或经理，则只能看到销售或驾管为自己的用户。
        $user = $request->user();

        $role_sales_manager = $user->hasRole(Configuration::fetch('role_sales_manager'));
        if ($role_sales_manager) {
            $query->whereNull('cu.sales_manager')->orWhere('cu.sales_manager', '=', $user->id);
        }

        $paginate = new PaginateService(
            [],
            [['so.so_id', 'desc']],
            ['kw', 'so_order_status', 'so_ve_id', 'so_cu_id', 'so_rental_start', 'so_rental_type'],
            []
        );

        $paginate->paginator($query, $request, [
            'kw__func' => function ($value, Builder $builder) {
                $builder->where(function (Builder $builder) use ($value) {
                    $builder->where('so.contract_number', 'like', '%'.$value.'%')
                        ->orWhere('ve.plate_no', 'like', '%'.$value.'%')
                        ->orWhere('cu.contact_name', 'like', '%'.$value.'%')
                        ->orWhere('so.so_remark', 'like', '%'.$value.'%')
                    ;
                });
            },
        ]);

        return $this->response()->withData($paginate)->respond();
    }

    #[PermissionAction(PermissionAction::ADD)]
    public function create(Request $request): Response
    {
        $rentalSaleOrder = new RentalSaleOrder([
            'rental_type'  => SoRentalType::LONG_TERM,
            'rental_start' => date('Y-m-d'),
        ]);

        $this->options();
        $this->response()->withExtras(
            RentalSaleOrderTpl::options(),
            RentalVehicle::options(
                where: function (Builder $builder) {
                    $builder->whereIn('status_rental', [VeStatusRental::LISTED])
                        ->whereIn('status_dispatch', [VeStatusDispatch::NOT_DISPATCHED])
                    ;
                }
            ),
            RentalCustomer::options(),
        );

        return $this->response()->withData($rentalSaleOrder)->respond();
    }

    /**
     * @throws ValidationException
     */
    #[PermissionAction(PermissionAction::ADD)]
    public function store(Request $request): Response
    {
        return $this->update($request, null);
    }

    #[PermissionAction(PermissionAction::SHOW)]
    public function show(RentalSaleOrder $rentalSaleOrder): Response
    {
        $rentalSaleOrder->load('RentalCustomer', 'RentalVehicle');

        $this->response()->withExtras(
            RentalVehicleReplacement::kvList(so_id: $rentalSaleOrder->so_id),
            RentalVehicleInspection::kvList(so_id: $rentalSaleOrder->so_id),
            RentalPayment::kvList(so_id: $rentalSaleOrder->so_id),
            RentalPayment::kvStat(),
            RentalSaleSettlement::kvList(so_id: $rentalSaleOrder->so_id),
            RentalVehicleUsage::kvList(so_id: $rentalSaleOrder->so_id),
            RentalVehicleRepair::kvList(so_id: $rentalSaleOrder->so_id),
            RentalVehicleRepair::kvStat(),
            RentalVehicleViolation::kvList(so_id: $rentalSaleOrder->so_id),
            RentalVehicleManualViolation::kvList(so_id: $rentalSaleOrder->so_id),
        );

        return $this->response()->withData($rentalSaleOrder)->respond();
    }

    public function edit(RentalSaleOrder $rentalSaleOrder): Response
    {
        $rentalSaleOrder->load('RentalCustomer', 'RentalVehicle', 'RentalPayments');

        $this->options();

        $this->response()->withExtras(
            RentalSaleOrderTpl::options(),
            RentalVehicle::options(
                where: function (Builder $builder) {
                    $builder->whereIn('status_rental', [VeStatusRental::LISTED])
                        ->whereIn('status_dispatch', [VeStatusDispatch::NOT_DISPATCHED])
                    ;
                }
            ),
            RentalCustomer::options(),
        );

        $this->response()->withExtras(
            RentalVehicleReplacement::kvList(so_id: $rentalSaleOrder->so_id),
            RentalVehicleInspection::kvList(so_id: $rentalSaleOrder->so_id),
            RentalPayment::kvList(so_id: $rentalSaleOrder->so_id),
            RentalPayment::kvStat(),
            RentalSaleSettlement::kvList(so_id: $rentalSaleOrder->so_id),
            RentalVehicleUsage::kvList(so_id: $rentalSaleOrder->so_id),
            RentalVehicleRepair::kvList(so_id: $rentalSaleOrder->so_id),
            RentalVehicleRepair::kvStat(),
            RentalVehicleViolation::kvList(so_id: $rentalSaleOrder->so_id),
            RentalVehicleViolation::kvStat(),
            RentalVehicleManualViolation::kvList(so_id: $rentalSaleOrder->so_id),
            RentalVehicleManualViolation::kvStat(),
            RentalDocTpl::options(function (Builder $query) {
                $query->where('dt.dt_type', '=', DtDtType::RENTAL_ORDER);
            }),
        );

        return $this->response()->withData($rentalSaleOrder)->respond();
    }

    #[PermissionAction(PermissionAction::DOC)]
    public function doc(Request $request, RentalSaleOrder $rentalSaleOrder, DocTplService $docTplService)
    {
        $input = $request->validate([
            'mode'  => ['required', Rule::in(DtDtExportType::label_keys())],
            'dt_id' => ['required', Rule::exists(RentalDocTpl::class)->where('dt_type', DtDtType::RENTAL_ORDER)->where('dt_status', DtDtStatus::ENABLED)],
        ]);

        $rentalSaleOrder->load('RentalCustomer', 'RentalVehicle', 'RentalVehicle.RentalVehicleInsurances', 'RentalCompany', 'RentalPayments'); // 'RentalCompany',

        $rentalDocTpl = RentalDocTpl::query()->find($input['dt_id']);

        $url = $docTplService->GenerateDoc($rentalDocTpl, $input['mode'], $rentalSaleOrder);

        return $this->response()->withData($url)->respond();
    }

    public function update(Request $request, ?RentalSaleOrder $rentalSaleOrder): Response
    {
        $input1 = $request->validate(
            [
                'rental_type' => ['bail', 'required', Rule::in(SoRentalType::label_keys())],
            ],
            [],
            trans_property(RentalSaleOrder::class)
        );
        $rental_type = $input1['rental_type'];

        $is_long_term  = SoRentalType::LONG_TERM === $rental_type;
        $is_short_term = SoRentalType::SHORT_TERM === $rental_type;

        $input2 = $request->validate(
            [
                'rental_payment_type' => ['bail', Rule::requiredIf($is_long_term), Rule::excludeIf($is_short_term), 'string', Rule::in(SoRentalPaymentType::label_keys())],
            ],
            [],
            trans_property(RentalSaleOrder::class)
        );

        $rental_payment_type = $input2['rental_payment_type'] ?? null;

        $validator = Validator::make(
            $request->all(),
            [
                'cu_id'           => ['bail', 'required', 'integer'],
                've_id'           => ['bail', 'required', 'integer'],
                'contract_number' => ['bail', 'required', 'string', 'max:50', Rule::unique(RentalSaleOrder::class, 'contract_number')->ignore($rentalSaleOrder)],
                'free_days'       => ['bail', 'nullable', 'int:4'],
                'rental_start'    => ['bail', 'required', 'date', 'before_or_equal:rental_end'],
                'installments'    => ['bail', Rule::requiredIf($is_long_term), Rule::excludeIf($is_short_term), 'integer', 'min:1'],
                'rental_days'     => ['bail', Rule::requiredIf($is_short_term), Rule::excludeIf($is_long_term), 'nullable', 'int:4'], // 短租的
                'rental_end'      => ['bail', 'required', 'date', 'after_or_equal:rental_start'],

                'deposit_amount'                  => ['bail', 'required', 'decimal:0,2', 'gte:0'],
                'management_fee_amount'           => ['bail', 'nullable', 'decimal:0,2', 'gte:0'],
                'rent_amount'                     => ['bail', 'required', 'numeric', 'min:0'],
                'payment_day'                     => ['bail', Rule::requiredIf($is_long_term), Rule::excludeIf($is_short_term), 'integer', new PaymentDayCheck($rental_payment_type)],
                'total_rent_amount'               => ['bail', Rule::requiredIf($is_short_term), Rule::excludeIf($is_long_term), 'numeric', 'min:0'],
                'insurance_base_fee_amount'       => ['bail', Rule::requiredIf($is_short_term), Rule::excludeIf($is_long_term), 'numeric', 'min:0'],
                'insurance_additional_fee_amount' => ['bail', Rule::requiredIf($is_short_term), Rule::excludeIf($is_long_term), 'numeric', 'min:0'],
                'other_fee_amount'                => ['bail', Rule::requiredIf($is_short_term), Rule::excludeIf($is_long_term), 'numeric', 'min:0'],
                'total_amount'                    => ['bail', Rule::requiredIf($is_short_term), Rule::excludeIf($is_long_term), 'numeric', 'min:0'],

                'cus_1'                               => ['bail', 'nullable', 'max:255'],
                'cus_2'                               => ['bail', 'nullable', 'max:255'],
                'cus_3'                               => ['bail', 'nullable', 'max:255'],
                'discount_plan'                       => ['bail', 'nullable', 'max:255'],
                'so_remark'                           => ['bail', 'nullable', 'max:255'],
                'rental_payments'                     => ['bail', Rule::requiredIf($is_long_term), Rule::excludeIf($is_short_term), 'array', 'min:1'],
                'rental_payments.*.pt_id'             => ['bail', 'required', 'integer', Rule::exists(RentalPaymentType::class)],
                'rental_payments.*.should_pay_date'   => ['bail', 'required', 'date'],
                'rental_payments.*.should_pay_amount' => ['bail', 'required', 'decimal:0,2', 'gte:0'],
                'rental_payments.*.rp_remark'         => ['nullable', 'string'],
            ]
            + Uploader::validator_rule_upload_array('additional_photos')
            + Uploader::validator_rule_upload_object('additional_file'),
            [],
            trans_property(RentalSaleOrder::class) + Arr::dot(['rental_payments' => ['*' => trans_property(RentalPayment::class)]])
        )
            ->after(function (\Illuminate\Validation\Validator $validator) use ($is_short_term, $rentalSaleOrder, $request) {
                if (!$validator->failed()) {
                    // ve_id
                    $ve_id         = $request->input('ve_id');
                    $rentalVehicle = RentalVehicle::query()->find($ve_id);
                    if (!$rentalVehicle) {
                        $validator->errors()->add('ve_id', 'The vehicle does not exist.');

                        return;
                    }

                    $pass = $rentalVehicle->check_status(VeStatusService::YES, [VeStatusRental::LISTED, VeStatusRental::RESERVED], [VeStatusDispatch::NOT_DISPATCHED], $validator);
                    if (!$pass) {
                        return;
                    }

                    $cu_id          = $request->input('cu_id');
                    $rentalCustomer = RentalCustomer::query()->find($cu_id);
                    if (!$rentalCustomer) {
                        $validator->errors()->add('cu_id', 'The customer does not exist.');

                        return;
                    }

                    if ($rentalSaleOrder) {
                        if (!$rentalSaleOrder->check_order_status([SoOrderStatus::PENDING], $validator)) {
                            return;
                        }
                    }

                    if ($is_short_term) {
                        if (0 !== bccomp(
                            $request->input('total_rent_amount'),
                            bcmul(
                                $request->input('rent_amount'),
                                bcsub($request->input('rental_days'), $request->input('free_days'), 0),
                                2
                            ),
                            2
                        )) {
                            $validator->errors()->add('total_rent_amount', '总租金计算错误');
                        }

                        if (0 !== bccomp(
                            math_array_bcadd(2, $request->input('deposit_amount'), $request->input('management_fee_amount'), $request->input('total_rent_amount'), $request->input('insurance_base_fee_amount'), $request->input('insurance_additional_fee_amount'), $request->input('other_fee_amount')),
                            $request->input('total_amount'),
                            2
                        )) {
                            $validator->errors()->add('total_amount', '总金额计算错误');
                        }
                    }
                }
            })
        ;
        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $input = $input1 + $input2 + $validator->validated();

        // input数据修正
        if (SoRentalType::LONG_TERM === $input['rental_type']) {
            // 总计租金金额
            $input['total_rent_amount'] = bcmul($input['installments'], $input['rent_amount'], 2);

            // 总计金额
            $input['total_amount'] = math_array_bcadd(2, $input['total_rent_amount'], $input['deposit_amount'], $input['management_fee_amount']);

            // 租期天数 rental_days
            $input['rental_days'] = Carbon::parse($input['rental_start'])->diffInDays(Carbon::parse($input['rental_end']), true) + 1;
        }

        DB::transaction(function () use (&$input, &$rentalSaleOrder) {
            /** @var RentalSaleOrder $rentalSaleOrder */
            if (null === $rentalSaleOrder) {
                $rentalSaleOrder = RentalSaleOrder::query()->create($input + ['order_at' => now(), 'order_status' => SoOrderStatus::PENDING]);
            } else {
                $rentalSaleOrder->update($input);
            }

            if (SoRentalType::LONG_TERM === $rentalSaleOrder->rental_type->value) {
                $rentalSaleOrder->RentalPayments()->delete();

                foreach ($input['rental_payments'] as $payment) {
                    $rentalSaleOrder->RentalPayments()->create($payment);
                }
            }

            $rentalSaleOrder->RentalVehicle->updateStatus(status_rental: VeStatusRental::RESERVED);
        });

        $rentalSaleOrder->refresh()->load('RentalPayments');

        return $this->response()->withData($rentalSaleOrder)->respond();
    }

    #[PermissionAction(PermissionAction::DELETE)]
    public function destroy(RentalSaleOrder $rentalSaleOrder): Response
    {
        $rentalSaleOrder->delete();

        return $this->response()->withData($rentalSaleOrder)->respond();
    }

    /**
     * 生成付款计划.
     */
    #[PermissionAction(PermissionAction::ADD)]
    public function rentalPaymentsOption(Request $request): Response
    {
        $validator = Validator::make(
            $request->all(),
            [
                'rental_type'           => ['bail', 'required', Rule::in([SoRentalType::LONG_TERM])],
                'rental_payment_type'   => ['bail', 'required', 'string', Rule::in(SoRentalPaymentType::label_keys())],
                'deposit_amount'        => ['bail', 'required', 'decimal:0,2', 'gte:0'],
                'management_fee_amount' => ['bail', 'nullable', 'decimal:0,2', 'gte:0'],
                'rental_start'          => ['bail', 'required', 'date'],
                'installments'          => ['bail', 'required', 'integer', 'min:1'],
                'rent_amount'           => ['bail', 'required', 'decimal:0,2', 'gte:0'],
                'payment_day'           => ['bail', 'required', 'integer', new PaymentDayCheck($request->input('rental_payment_type'))],
            ],
            [],
            trans_property(RentalSaleOrder::class)
        );

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $input = $validator->validated();

        // 创建数字到星期名称的映射
        $daysOfWeek = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'];

        $rentalPaymentType = $input['rental_payment_type'];

        list('interval' => $interval, 'interval_unit' => $interval_unit, 'prepaid' => $prepaid) = SoRentalPaymentType::interval[$rentalPaymentType];

        $paymentDayType = SoRentalPaymentType::payment_day_classes[$rentalPaymentType];

        $startDate = Carbon::parse($input['rental_start']);

        $free_days = $request->input('free_days');

        $schedule = new Collection();

        // 添加一次性押金
        $schedule->push(new RentalPayment([
            'pt_id'             => RpPtId::DEPOSIT,
            'should_pay_date'   => $startDate->toDateString(),
            'should_pay_amount' => $input['deposit_amount'],
            'rp_remark'         => new RpPtId(RpPtId::DEPOSIT)->label,
        ]));

        if (($management_fee_amount = ($input['management_fee_amount'] ?? null)) && $input['management_fee_amount'] > 0) {// 添加一次性管理费（如果有）
            $schedule->push(new RentalPayment([
                'pt_id'             => RpPtId::MANAGEMENT_FEE,
                'should_pay_date'   => $startDate->toDateString(),
                'should_pay_amount' => $management_fee_amount,
                'rp_remark'         => new RpPtId(RpPtId::MANAGEMENT_FEE)->label,
            ]));
        }

        $installmentNumber = 1;

        $currentDate = $startDate->copy();

        while (true) {
            // 计算账单周期
            $billingPeriodStart = $currentDate->copy();
            $billingPeriodEnd   = $currentDate->copy()->add($interval.' '.$interval_unit)->subDay();

            if (1 === $installmentNumber && $free_days > 0) {
                $billingPeriodEnd->add(CarbonInterval::days($free_days));
            }

            // 如果账单周期开始日期超过结束日期，或者付款日期超过租赁结束日期，跳出循环
            if ($billingPeriodStart->greaterThan($billingPeriodEnd)) {
                break;
            }

            if ($prepaid) {
                $paymentDate = $billingPeriodStart->copy(); // 预付的付款日期为账单周期开始日期
            } else {
                $paymentDate = $billingPeriodEnd->copy();
            }

            // 调整付款日期
            if (SoPaymentDay_Week::class == $paymentDayType) {
                if ($prepaid) {
                    if (1 !== $installmentNumber) {
                        // 获取指定的星期名称
                        $dayName = $daysOfWeek[$input['payment_day']];

                        $paymentDate->modify('this '.$dayName);

                        if ($paymentDate->greaterThan($billingPeriodStart)) {
                            $paymentDate->subWeek();
                        }
                    }
                } else {
                    // 获取指定的星期名称
                    $dayName = $daysOfWeek[$input['payment_day']];

                    $paymentDate->modify('this '.$dayName);

                    // 后付方式：付款日期应晚于账单周期结束日期
                    if ($paymentDate->lessThanOrEqualTo($billingPeriodEnd)) {
                        // 如果付款日期早于或等于账单周期结束日期，月份加一
                        $paymentDate->addWeek();
                    }
                }
            } else {
                // 根据付款方式调整付款日期
                if ($prepaid) {
                    if (1 !== $installmentNumber) {
                        // 将付款日转换为整数
                        $paymentDay = (int) $input['payment_day'];
                        // 设置付款日期的天数部分
                        $paymentDate->day($paymentDay);

                        // 预付方式：付款日期应不早于账单周期开始日期
                        if ($paymentDate->greaterThan($billingPeriodStart)) {
                            // 如果付款日期晚于账单周期开始日期，月份减一
                            $paymentDate->subMonthNoOverflow();
                        }
                    }
                } else {
                    // 将付款日转换为整数
                    $paymentDay = (int) $input['payment_day'];
                    // 设置付款日期的天数部分
                    $paymentDate->day($paymentDay);

                    // 后付方式：付款日期应晚于账单周期结束日期
                    if ($paymentDate->lessThanOrEqualTo($billingPeriodEnd)) {
                        // 如果付款日期早于或等于账单周期结束日期，月份加一
                        $paymentDate->addMonthNoOverflow();
                    }
                }
            }

            // 计算账单周期天数
            $days = $billingPeriodStart->diffInDays($billingPeriodEnd, true) + 1;

            // 创建备注信息
            $beginDateText = 1 === $installmentNumber ? '白天' : '0点';
            $endDateText   = $installmentNumber === (int) $input['installments'] ? '白天' : '24点';
            $remark        = sprintf('第%d期租金（%d天，账单周期：%s %s ~ %s %s）', $installmentNumber, $days, $billingPeriodStart->toDateString(), $beginDateText, $billingPeriodEnd->toDateString(), $endDateText);

            // 添加租金付款
            $rentalPayment = new RentalPayment([
                'pt_id' => RpPtId::RENT, 'should_pay_date' => $paymentDate->toDateString(), 'should_pay_amount' => $input['rent_amount'], 'rp_remark' => $remark,
            ]);
            $rentalPayment->period = ['start_d' => $billingPeriodStart->toDateString(), 'end_d' => $billingPeriodEnd->toDateString()];

            $schedule->push($rentalPayment);

            // 检查是否达到总期数
            ++$installmentNumber;
            if ($installmentNumber > (int) $input['installments']) {
                break;
            }

            // 准备下一期
            $currentDate = $billingPeriodEnd->clone()->addDay();
        }

        $schedule->load('RentalPaymentType');

        return $this->response($request)->withData($schedule->toArray())->respond();
    }

    public static function callRentalPaymentsOption($input)
    {
        $subRequest = Request::create(
            '',
            'GET',
            $input,
            server: [                        // server(含请求头)
                'CONTENT_TYPE' => 'application/json',   // 注意：没有 HTTP_ 前缀
                'HTTP_ACCEPT'  => 'application/json',   // 头信息要加 HTTP_ 前缀
            ]
        );

        static $RentalSaleOrderController = null;

        if (null === $RentalSaleOrderController) {
            $RentalSaleOrderController = App::make(RentalSaleOrderController::class);
        }

        $response = App::call(
            [$RentalSaleOrderController, 'rentalPaymentsOption'],
            ['request' => $subRequest]
        );

        $payments = $response->original['data'];

        return $payments;
    }

    /**
     * 通过签约模板生成.
     */
    #[PermissionAction(PermissionAction::ADD)]
    public function generate(Request $request, RentalSaleOrderTpl $rentalSaleOrderTpl): Response
    {
        $rentalSaleOrderTpl->append('contract_number');

        $result = array_filter($rentalSaleOrderTpl->toArray());

        return $this->response()->withData($result)->respond();
    }

    #[PermissionAction(PermissionAction::ADD)]
    #[PermissionAction(PermissionAction::EDIT)]
    public function upload(Request $request): Response
    {
        return Uploader::upload(
            $request,
            'rental_order',
            [
                'additional_photos',
                'additional_file',
            ],
            $this
        );
    }

    protected function options(?bool $with_group_count = false): void
    {
        $this->response()->withExtras(
            SoRentalType::options(),
            $with_group_count ? SoRentalType_Short::options_with_count(RentalSaleOrder::class) : SoRentalType_Short::options(),
            SoRentalPaymentType::options(),
            SoPaymentDay_Month::options(),
            SoPaymentDay_Week::options(),
            $with_group_count ? SoOrderStatus::options_with_count(RentalSaleOrder::class) : SoOrderStatus::options(),
        );
    }
}
