<?php

namespace App\Models\Rental\Sale;

use App\Attributes\ClassName;
use App\Enum\Rental\RsDeleteOption;
use App\Enum\Rental\SoOrderStatus;
use App\Enum\Rental\SoRentalPaymentType;
use App\Enum\Rental\SoRentalType;
use App\Enum\Rental\SsReturnStatus;
use App\Models\ModelTrait;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

#[ClassName('结算')]
/**
 * @property int                       $ss_id                      结算序号
 * @property int                       $so_id                      订单序号
 * @property null|float                $deposit_amount             合同押金
 * @property null|float                $received_deposit           实收押金
 * @property null|float                $early_return_penalty       提前退车违约金
 * @property null|float                $overdue_inspection_penalty 逾期年检违约金
 * @property null|float                $overdue_rent               逾期租金
 * @property null|float                $overdue_penalty            逾期违约金
 * @property null|float                $accident_depreciation_fee  出险加速折旧费
 * @property null|float                $insurance_surcharge        保险上浮费用
 * @property null|float                $violation_withholding_fee  违章代扣费用
 * @property null|float                $violation_penalty          违章违约金
 * @property null|float                $repair_fee                 还车定损/维修费
 * @property null|float                $insurance_deductible       保险绝对免赔
 * @property null|float                $forced_collection_fee      强制收车费
 * @property null|float                $other_deductions           其他扣款
 * @property null|string               $other_deductions_remark    其他扣款备注
 * @property null|float                $refund_amount              返还款；客户额外多付的费用
 * @property null|string               $refund_details             返还款明细
 * @property null|float                $settlement_amount          退车结算费
 * @property null|Carbon               $deposit_return_amount      应退押金金额
 * @property null|Carbon               $deposit_return_date        应退押金日期
 * @property null|int|SsReturnStatus   $return_status              退车结算状态
 * @property null|Carbon               $return_datetime            退车结算日时
 * @property null|mixed                $additional_photos          附加照片
 * @property null|mixed|RsDeleteOption $delete_option              是否删除应收款选项
 * @property null|string               $ss_remark                  结算备注
 * @property null|int                  $processed_by               处理人;id
 * @property null|int                  $approved_by                审核人
 * @property null|Carbon               $approved_at                审核时间
 *                                                                 -
 * @property RentalSaleOrder           $RentalSaleOrder
 */
class RentalSaleSettlement extends Model
{
    use ModelTrait;

    public const array calcOpts = [
        'received_deposit'           => '-', // 实收押金
        'early_return_penalty'       => '',
        'overdue_inspection_penalty' => '',
        'overdue_rent'               => '',
        'overdue_penalty'            => '',
        'accident_depreciation_fee'  => '',
        'insurance_surcharge'        => '',
        'violation_withholding_fee'  => '',
        'violation_penalty'          => '',
        'repair_fee'                 => '',
        'insurance_deductible'       => '',
        'forced_collection_fee'      => '',
        'other_deductions'           => '',
        'refund_amount'              => '-', // 返还款
    ];

    //    public $incrementing = false;

    protected $attributes = [];

    protected $appends = [
    ];

    protected $casts = [
        'return_datetime'     => 'datetime:Y-m-d H:i',
        'deposit_return_date' => 'datetime:Y-m-d',
        'delete_option'       => RsDeleteOption::class,
        'return_status'       => SsReturnStatus::class,
    ];

    protected $primaryKey = 'ss_id';

    protected $guarded = ['ss_id'];

    public function RentalSaleOrder(): BelongsTo
    {
        return $this->belongsTo(RentalSaleOrder::class, 'so_id', 'so_id');
    }

    public static function indexQuery(array $search = []): Builder
    {
        $cu_id = $search['cu_id'] ?? null;
        $so_id = $search['so_id'] ?? null;

        return DB::query()
            ->from('rental_sale_settlements', 'ss')
            ->leftJoin('rental_sale_orders as so', 'so.so_id', '=', 'ss.so_id')
            ->leftJoin('rental_vehicles as ve', 've.ve_id', '=', 'so.ve_id')
            ->leftJoin('rental_vehicle_models as _vm', '_vm.vm_id', '=', 've.vm_id')
            ->leftJoin('rental_customers as cu', 'cu.cu_id', '=', 'so.cu_id')
            ->when($cu_id, function (Builder $query) use ($cu_id) {
                $query->where('cu.cu_id', '=', $cu_id);
            })
            ->when($so_id, function (Builder $query) use ($so_id) {
                $query->where('ss.so_id', '=', $so_id);
            })
            ->when(
                null === $cu_id && null === $so_id,
                function (Builder $query) {
                    $query->orderByDesc('ss.ss_id');
                },
                function (Builder $query) {
                    $query->orderBy('ss.ss_id');
                }
            )
            ->select('ss.*', 'so.*', 'cu.*', 've.*', '_vm.brand_name', '_vm.model_name')
            ->addSelect(
                DB::raw(SsReturnStatus::toCaseSQL()),
                DB::raw(SoRentalType::toCaseSQL()),
                DB::raw(SoRentalPaymentType::toCaseSQL()),
                DB::raw(SoOrderStatus::toCaseSQL()),
                DB::raw("to_char(ss.return_datetime, 'YYYY-MM-DD HH24:MI') as return_datetime_"),
            )
        ;
    }

    public static function options(?\Closure $where = null): array
    {
        return [];
    }

    protected function additionalPhotos(): Attribute
    {
        return $this->uploadFileArray();
    }
}
