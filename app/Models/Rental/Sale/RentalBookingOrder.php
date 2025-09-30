<?php

namespace App\Models\Rental\Sale;

use App\Attributes\ClassName;
use App\Enum\Booking\BoBoSource;
use App\Enum\Booking\BoBType;
use App\Enum\Booking\BoOrderStatus;
use App\Enum\Booking\BoPaymentStatus;
use App\Enum\Booking\BoRefundStatus;
use App\Models\ModelTrait;
use App\Models\Rental\Customer\RentalCustomer;
use App\Models\Rental\Vehicle\RentalVehicle;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

#[ClassName('预定订单')]
/**
 * @property int                    $bo_id                订单序号
 * @property string                 $bo_no                订单编号
 * @property BoBoSource|string      $bo_source            订单来源
 * @property int                    $cu_id                客户ID
 * @property string                 $plate_no             车牌号
 * @property BoBType|string         $b_type               租期类型
 * @property Carbon                 $pickup_date          提车日期
 * @property int                    $rent_per_amount      租金;(元)
 * @property int                    $deposit_amount       押金;(元)
 * @property array                  $b_props              车辆信息
 * @property Carbon                 $registration_date    注册日期
 * @property int                    $b_mileage            行驶里程;(公里)
 * @property int                    $service_interval     保养周期;(公里)
 * @property int                    $min_rental_periods   最短租期;(天、周、月)
 * @property BoPaymentStatus|string $payment_status       支付状态
 * @property BoOrderStatus|string   $order_status         订单状态
 * @property BoRefundStatus|string  $refund_status        退款状态
 * @property string                 $b_note               其他信息
 * @property string                 $earnest_amount       定金;(元)
 * @property Carbon                 $order_at             上架时间
 *                                                        -
 * @property RentalCustomer         $RentalCustomer
 * @property RentalVehicle          $RentalVehicle
 *                                                        -
 * @property string                 $bo_source_label
 * @property string                 $payment_status_label
 * @property string                 $order_status_label
 * @property string                 $refund_status_label
 */
class RentalBookingOrder extends Model
{
    use ModelTrait;

    protected $primaryKey = 'bo_id';

    protected $guarded = ['bo_id'];

    protected $casts = [
        'b_props'           => 'array',
        'b_type'            => BoBType::class,
        'bo_source'         => BoBoSource::class,
        'pickup_date'       => 'datetime:Y-m-d',
        'registration_date' => 'datetime:Y-m-d',
        'payment_status'    => BoPaymentStatus::class,
        'order_status'      => BoOrderStatus::class,
        'refund_status'     => BoRefundStatus::class,
    ];

    protected $appends = [
        'b_type_label',
        'bo_source_label',
        'payment_status_label',
        'order_status_label',
        'refund_status_label',
    ];

    public function RentalVehicle(): BelongsTo
    {
        return $this->belongsTo(RentalVehicle::class, 'plate_no', 'plate_no')->with('RentalVehicleModel');
    }

    public function RentalCustomer(): BelongsTo
    {
        return $this->belongsTo(RentalCustomer::class, 'cu_id', 'cu_id');
    }

    public static function indexQuery(array $search = []): Builder
    {
        return DB::query()->from('rental_booking_orders', 'bo')
            ->leftJoin('rental_vehicles as ve', 'bo.plate_no', '=', 've.plate_no')
            ->leftJoin('rental_vehicle_models as vm', 'vm.vm_id', '=', 've.vm_id')
            ->leftJoin('rental_customers as cu', 'cu.cu_id', '=', 'bo.cu_id')
            ->select('bo.*', 've.*', 'vm.*', 'cu.*')
            ->addSelect(
                DB::raw(BoBType::toCaseSQL()),
                DB::raw(BoBoSource::toCaseSQL()),
                DB::raw(BoPaymentStatus::toCaseSQL()),
                DB::raw(BoOrderStatus::toCaseSQL()),
                DB::raw(BoRefundStatus::toCaseSQL()),
                DB::raw("to_char(bo.order_at, 'YYYY-MM-DD HH24:MI:SS') as order_at"),
            )
        ;
    }

    public static function options(?\Closure $where = null): array
    {
        return [];
    }

    protected function bTypeLabel(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->getAttribute('b_type')?->label
        );
    }

    protected function boSourceLabel(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->getAttribute('bo_source')?->label
        );
    }

    protected function paymentStatusLabel(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->getAttribute('payment_status')?->label
        );
    }

    protected function orderStatusLabel(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->getAttribute('order_status')?->label
        );
    }

    protected function refundStatusLabel(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->getAttribute('refund_status')?->label
        );
    }
}
