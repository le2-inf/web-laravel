<?php

namespace App\Models\Rental\Payment;

use App\Attributes\ClassName;
use App\Enum\Rental\IoIoType;
use App\Enum\Rental\RpPayStatus;
use App\Models\ModelTrait;
use App\Models\Rental\Customer\RentalCustomer;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

#[ClassName('账户流水')]
/**
 * @property int                  $io_id                流水序号
 * @property mixed                $io_type              流水类型
 * @property int                  $cu_id                客户序号
 * @property int                  $pa_id                收付款账号序号
 * @property Carbon               $occur_datetime       发生时间
 * @property float                $occur_amount         发生金额
 * @property float                $account_balance      收款账户当前余额
 * @property null|int             $rp_id                租车收支序号
 * @property RentalCustomer       $RentalCustomer
 * @property RentalPaymentAccount $RentalPaymentAccount
 * @property RentalPayment        $RentalPayment
 */
class RentalPaymentInout extends Model
{
    use ModelTrait;

    protected $primaryKey = 'io_id';

    protected $guarded = ['io_id'];

    protected $attributes = [];

    protected $casts = [
        'occur_datetime' => 'datetime:Y-m-d H:i:s',
        'io_type'        => IoIoType::class,
    ];

    protected $appends = [
        'io_type_label',
    ];

    public function RentalCustomer(): BelongsTo
    {
        return $this->belongsTo(RentalCustomer::class, 'cu_id', 'cu_id');
    }

    public function RentalPaymentAccount(): BelongsTo
    {
        return $this->belongsTo(RentalPaymentAccount::class, 'pa_id', 'pa_id');
    }

    public function RentalPayment(): BelongsTo
    {
        return $this->belongsTo(RentalPayment::class, 'rp_id', 'rp_id');
    }

    public static function indexQuery(array $search = []): Builder
    {
        return DB::query()
            ->from('rental_payment_inouts', 'io')
            ->leftJoin('rental_customers as cu', 'cu.cu_id', '=', 'io.cu_id')
            ->leftJoin('rental_payment_accounts as pa', 'pa.pa_id', '=', 'io.pa_id')
            ->leftJoin('rental_payments as rp', 'rp.rp_id', '=', 'io.rp_id')
            ->leftJoin('rental_payment_types as pt', 'pt.pt_id', '=', 'rp.pt_id')
            ->leftJoin('rental_sale_orders as so', 'so.so_id', '=', 'rp.so_id')
            ->leftJoin('rental_vehicles as ve', 've.ve_id', '=', 'so.ve_id')
            ->leftJoin('rental_vehicle_models as vm', 'vm.vm_id', '=', 've.vm_id')
            ->orderByDesc('io.io_id')
            ->select('pa.pa_name', 'pt.pt_name', 'io.occur_amount', 'io.account_balance', 'rp.should_pay_date', 'rp.should_pay_amount', 'cu.contact_name', 'so.contract_number', 've.plate_no', 'rp.rp_remark')
            ->addSelect(
                DB::raw(IoIoType::toCaseSQL()),
                DB::raw(RpPayStatus::toCaseSQL()),
                DB::raw(RpPayStatus::toColorSQL()),
                DB::raw(" CONCAT(COALESCE(vm.brand_name,'未知品牌'),'-',COALESCE(vm.model_name,'未知车型')) AS brand_full_name"),
                DB::raw("to_char(io.occur_datetime, 'YYYY-MM-DD HH24:MI:SS') as occur_datetime_"),
            )
        ;
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

    public static function options(?\Closure $where = null): array
    {
        return [];
    }

    protected function ioTypeLabel(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->getAttribute('io_type')?->label,
        );
    }
}
