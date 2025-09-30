<?php

namespace App\Models\Rental\Payment;

use App\Attributes\ClassName;
use App\Attributes\ColumnDesc;
use App\Attributes\ColumnType;
use App\Enum\Rental\RpIsValid;
use App\Enum\Rental\RpPayStatus;
use App\Enum\Rental\RpPtId;
use App\Enum\Rental\RpShouldPayDate_DDD;
use App\Enum\Rental\SoOrderStatus;
use App\Exceptions\ClientException;
use App\Models\ModelTrait;
use App\Models\Rental\ImportTrait;
use App\Models\Rental\Sale\RentalSaleOrder;
use App\Models\Rental\Vehicle\RentalVehicleInspection;
use App\Models\Rental\Vehicle\RentalVehicleMaintenance;
use App\Models\Rental\Vehicle\RentalVehicleRepair;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

#[ClassName('财务', '记录')]
#[ColumnDesc('rp_id', required: true, )]
#[ColumnDesc('so_id', required: true, )]
#[ColumnDesc('pt_id', required: true, enum_class: RpPtId::class)]
#[ColumnDesc('should_pay_date', required: true, type: ColumnType::DATE)]
#[ColumnDesc('should_pay_amount', required: true, )]
#[ColumnDesc('pay_status', required: true, enum_class: RpPayStatus::class)]
#[ColumnDesc('actual_pay_date', type: ColumnType::DATE)]
#[ColumnDesc('actual_pay_amount')]
#[ColumnDesc('pa_id')]
#[ColumnDesc('rp_remark')]
#[ColumnDesc('is_valid', required: true, enum_class: RpIsValid::class)]
#[ColumnDesc('vr_id')]
#[ColumnDesc('vm_id')]
#[ColumnDesc('vi_id')]
/**
 * @property int                  $rp_id                财务记录序号
 * @property int                  $so_id                租车订单序号；指向订单表
 * @property int                  $pt_id                付款类型序号；引用payment_types表
 * @property Carbon|string        $should_pay_date      应收/应付日期
 * @property string               $day_of_week_name     应收/应付星期
 * @property float                $should_pay_amount    应收/付金额
 * @property RpPayStatus|string   $pay_status           付款状态；例如已支付、未支付、失败等
 * @property null|Carbon          $actual_pay_date      实际收付日期
 * @property null|float           $actual_pay_amount    实际收付金额
 * @property null|int             $pa_id                支付账户序号
 * @property null|string          $rp_remark            财务备注
 * @property int|RpIsValid        $is_valid             是否有效
 * @property null                 $vr_id                车辆维修序号
 * @property null                 $vm_id                车辆维修序号
 * @property null                 $vi_id                车辆检查序号
 * @property array|mixed          $period               ;start_d 、end_d
 * @property RentalSaleOrder      $RentalSaleOrder
 * @property RentalPaymentAccount $RentalPaymentAccount
 * @property RentalPaymentType    $RentalPaymentType
 * @property null|string          $pay_status_label     支付状态-中文
 * @property null|string          $is_valid_label       有效状态-中文
 */
class RentalPayment extends Model
{
    use ModelTrait;

    use ImportTrait;

    protected $primaryKey = 'rp_id';

    protected $guarded = ['rp_id'];

    protected $attributes = [
        'pay_status' => RpPayStatus::UNPAID,
        'is_valid'   => RpIsValid::VALID,
    ];

    protected $casts = [
        'pay_status'        => RpPayStatus::class,
        'should_pay_amount' => 'decimal:2',
        'actual_pay_amount' => 'decimal:2',
        'is_valid'          => RpIsValid::class,
        //        'pt_id'             => RpPtId::class, // !!! 因为是外键，所以不能做转换。
    ];

    protected $appends = [
        'day_of_week_name',
        'pay_status_label',
        'is_valid_label',
    ];

    public function RentalSaleOrder(): BelongsTo
    {
        return $this->belongsTo(RentalSaleOrder::class, 'so_id', 'so_id');
    }

    public function RentalPaymentType(): BelongsTo
    {
        return $this->belongsTo(RentalPaymentType::class, 'pt_id', 'pt_id');
    }

    public function RentalPaymentAccount(): BelongsTo
    {
        return $this->belongsTo(RentalPaymentAccount::class, 'pa_id', 'pa_id');
    }

    public function RentalVehicleRepair(): BelongsTo
    {
        return $this->belongsTo(RentalVehicleRepair::class, 'vr_id', 'vr_id');
    }

    public function RentalVehicleMaintenance(): BelongsTo
    {
        return $this->belongsTo(RentalVehicleMaintenance::class, 'vm_id', 'vm_id');
    }

    public function RentalVehicleInspection(): BelongsTo
    {
        return $this->belongsTo(RentalVehicleInspection::class, 'vi_id', 'vi_id');
    }

    public static function indexQuery(array $search = []): Builder
    {
        $so_id = $search['so_id'] ?? null;
        $cu_id = $search['cu_id'] ?? null;

        return DB::query()
            ->from('rental_payments', 'rp')
            ->leftJoin('rental_payment_accounts as pa', 'pa.pa_id', '=', 'rp.pa_id')
            ->leftJoin('rental_payment_types as pt', 'pt.pt_id', '=', 'rp.pt_id')
            ->leftJoin('rental_sale_orders as so', 'so.so_id', '=', 'rp.so_id')
            ->leftJoin('rental_customers as cu', 'cu.cu_id', '=', 'so.cu_id')
            ->leftJoin('rental_vehicles as ve', 've.ve_id', '=', 'so.ve_id')
            ->leftJoin('rental_vehicle_models as vm', 'vm.vm_id', '=', 've.vm_id')
            ->when($so_id, function (Builder $query) use ($so_id) {
                $query->where('rp.so_id', '=', $so_id);
            })
            ->when($cu_id, function (Builder $query) use ($cu_id) {
                $query->where('so.cu_id', '=', $cu_id);
            })
            ->when(
                null === $so_id && null === $cu_id,
                function (Builder $query) {
                    $query->orderByDesc('rp.so_id')->orderby('rp.rp_id');
                },
                function (Builder $query) {
                    $query->orderBy('rp.so_id')->orderby('rp.should_pay_date')->orderby('rp.rp_id');
                }
            )
            ->select('*')
            ->addSelect(
                DB::raw(SoOrderStatus::toCaseSQL()),
                DB::raw(RpPayStatus::toCaseSQL()),
                DB::raw(RpPayStatus::toColorSQL()),
                DB::raw(RpIsValid::toCaseSQL()),
                DB::raw(RpShouldPayDate_DDD::toCaseSQL(true, 'rp.should_pay_date')),
                DB::raw((function () {
                    return sprintf(
                        "(rp.is_valid in ('%s') and  so.order_status in ('%s')) as can_pay",
                        join("','", [RpIsValid::VALID]),
                        join("','", [SoOrderStatus::PENDING, SoOrderStatus::SIGNED, SoOrderStatus::COMPLETED, SoOrderStatus::EARLY_TERMINATION])
                    );
                })())
            )
        ;
    }

    public static function indexStat($list): array
    {
        $accounts_receivable_amount = $actual_received_amount = $pending_receivable_amount = $pending_receivable_size = $less_receivable_amount = '0';
        foreach ($list as $key => $value) {
            if (RpIsValid::VALID == $value->is_valid) {
                $accounts_receivable_amount = bcadd($accounts_receivable_amount, $value->should_pay_amount, 2); // 应收
            }
            if (RpPayStatus::PAID == $value->pay_status) {
                $actual_received_amount = bcadd($actual_received_amount, $value->actual_pay_amount, 2); // 实收

                $less_receivable_amount = bcadd($less_receivable_amount, bcsub($value->should_pay_amount, $value->actual_pay_amount, 2), 2); // 减免
            }

            if (RpIsValid::VALID == $value->is_valid && RpPayStatus::UNPAID == $value->pay_status) {
                $pending_receivable_amount = bcadd($pending_receivable_amount, $value->should_pay_amount, 2); // 待收
                ++$pending_receivable_size; // 待收笔数
            }
        }

        return compact('accounts_receivable_amount', 'actual_received_amount', 'less_receivable_amount', 'pending_receivable_amount', 'pending_receivable_size');
    }

    public static function option(Collection $RentalPayments): array
    {
        return [
            preg_replace('/^.*\\\/', '', get_called_class()).'Options' => (function () use ($RentalPayments) {
                $value = [];
                foreach ($RentalPayments as $key => $rp) {
                    $value[] = ['text' => $rp->rp_remark, 'value' => $key];
                }

                return $value;
            })(),
        ];
    }

    //    private static function rawCanPay(): string {}
    public static function importColumns(): array
    {
        return [
            'contract_number'   => [RentalSaleOrder::class, 'contract_number'],
            'pt_id'             => [RentalPayment::class, 'pt_id'],
            'should_pay_date'   => [RentalPayment::class, 'should_pay_date'],
            'should_pay_amount' => [RentalPayment::class, 'should_pay_amount'],
            'pay_status'        => [RentalPayment::class, 'pay_status'],
            'actual_pay_date'   => [RentalPayment::class, 'actual_pay_date'],
            'actual_pay_amount' => [RentalPayment::class, 'actual_pay_amount'],
            'pa_id'             => [RentalPayment::class, 'pa_id'],
            'rp_remark'         => [RentalPayment::class, 'rp_remark'],
            'vr_id'             => [RentalPayment::class, 'vr_id'],
            'vm_id'             => [RentalPayment::class, 'vm_id'],
            'vi_id'             => [RentalPayment::class, 'vi_id'],
        ];
    }

    public static function importBeforeValidateDo(): \Closure
    {
        return function (&$item) {
            $item['so_id']      = RentalSaleOrder::contractNumberKv($item['contract_number'] ?? null);
            $item['pay_status'] = RpPayStatus::searchValue($item['pay_status'] ?? null);
            $item['pt_id']      = RpPtId::searchValue($item['pt_id'] ?? null);

            static::$fields['contract_number'][] = $item['contract_number'] ?? null;
            static::$fields['pa_id'][]           = $item['pa_id'] ?? null;
        };
    }

    public static function importValidatorRule(array $item, array $fieldAttributes): void
    {
        $rules = [
            'so_id'             => ['bail', 'required', 'integer'],
            'pt_id'             => ['bail', 'required', Rule::in(RpPtId::label_keys())],
            'should_pay_date'   => ['bail', 'required', 'date'],
            'should_pay_amount' => ['bail', 'required', 'numeric'],
            'pay_status'        => ['bail', 'required', Rule::in(RpPayStatus::label_keys())],
            'actual_pay_date'   => ['bail', Rule::requiredIf(RpPayStatus::PAID === $item['pay_status']), 'nullable', 'date'],
            'actual_pay_amount' => ['bail', Rule::requiredIf(RpPayStatus::PAID === $item['pay_status']), 'nullable', 'numeric'],
            'pa_id'             => ['bail', 'required'],
            'rp_remark'         => ['bail', 'nullable', 'string'],
        ];

        $validator = Validator::make($item, $rules, [], $fieldAttributes);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
    }

    public static function importAfterValidatorDo(): \Closure
    {
        return function () {
            // contract_number
            $missing = array_diff(static::$fields['contract_number'], RentalSaleOrder::query()->pluck('contract_number')->toArray());
            if (count($missing) > 0) {
                throw new ClientException('以下合同编号不存在：'.join(',', $missing));
            }

            // pa_id
            $missing = array_diff(static::$fields['pa_id'], RentalPaymentAccount::query()->pluck('pa_id')->toArray());
            if (count($missing) > 0) {
                throw new ClientException('以下支付账户序号不存在：'.join(',', $missing));
            }
        };
    }

    public static function importCreateDo(): \Closure
    {
        return function ($input) {
            $rentalPayment = RentalPayment::query()->create($input);
        };
    }

    public static function options(?\Closure $where = null): array
    {
        return [];
    }

    protected function dayOfWeekName(): Attribute
    {
        return Attribute::make(
            get: fn () => Carbon::parse($this->getAttribute('should_pay_date'))->isoFormat('dddd')
        );
    }

    protected function payStatusLabel(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->getAttribute('pay_status')?->label,
        );
    }

    protected function isValidLabel(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->getAttribute('is_valid')?->label,
        );
    }
}
