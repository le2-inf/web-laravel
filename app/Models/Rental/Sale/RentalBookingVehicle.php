<?php

namespace App\Models\Rental\Sale;

use App\Attributes\ClassName;
use App\Enum\Booking\BvBType;
use App\Enum\Booking\BvIsListed;
use App\Models\ModelTrait;
use App\Models\Rental\Vehicle\RentalVehicle;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

#[ClassName('预定车辆')]
/**
 * @property int             $bv_id                预订车辆序号
 * @property BvBType|string  $b_type               租期类型
 * @property string          $plate_no             车牌号
 * @property null|Carbon     $pickup_date          提车日期
 * @property int             $rent_per_amount      每期租金;(元)
 * @property int             $deposit_amount       押金;(元)
 * @property int             $min_rental_periods   最短租期;(天、周、月)
 * @property null|Carbon     $registration_date    注册日期
 * @property int             $b_mileage            行驶里程;(公里)
 * @property int             $service_interval     保养周期;(公里)
 * @property null|array      $b_props              车辆信息
 * @property string          $b_note               备注信息
 * @property null|array      $bv_photo             车辆照片；存储照片路径的 JSON 数组
 * @property null|array      $bv_additional_photos 附加照片；存储照片路径的 JSON 数组
 * @property null|BvIsListed $is_listed            上架状态;
 * @property Carbon          $listed_at            上架时间
 *                                                 --
 * @property RentalVehicle   $RentalVehicle
 */
class RentalBookingVehicle extends Model
{
    use ModelTrait;

    protected $primaryKey = 'bv_id';

    protected $guarded = ['bv_id'];

    protected $casts = [
        'b_props'   => 'array',
        'b_type'    => BvBType::class,
        'is_listed' => BvIsListed::class,
    ];

    protected $appends = [
        'b_type_label',
        'is_listed_label',
    ];

    protected $attributes = [];

    public function RentalVehicle(): BelongsTo
    {
        return $this->belongsTo(RentalVehicle::class, 'plate_no', 'plate_no')->with('RentalVehicleModel');
    }

    public static function indexQuery(array $search = []): Builder
    {
        return DB::query()
            ->from('rental_booking_vehicles', 'bv')
            ->leftJoin('rental_vehicles as ve', 'bv.plate_no', '=', 've.plate_no')
            ->leftJoin('rental_vehicle_models as vm', 'vm.vm_id', '=', 've.vm_id')
            ->select('bv.*', 've.*', 'vm.*')
            ->addSelect(
                DB::raw(BvBType::toCaseSQL()),
                DB::raw('(NOW()::date - listed_at::date) AS listed_days_diff'),
            )
        ;
    }

    public static function options(?\Closure $where = null): array
    {
        $key = preg_replace('/^.*\\\/', '', get_called_class())
            .'Options';

        $value = DB::query()
            ->from('rental_booking_vehicles', 'bv')
            ->leftJoin('rental_vehicles as ve', 'bv.plate_no', '=', 've.plate_no')
            ->leftJoin('rental_vehicle_models as vm', 'vm.vm_id', '=', 've.vm_id')
            ->where('bv.is_listed', '=', BvIsListed::LISTED)
            ->select(DB::raw("CONCAT(ve.plate_no,'-',COALESCE(vm.brand_name,'未知品牌'),'-', COALESCE(vm.model_name,'未知车型')) as text,bv.bv_id as value"))
            ->get()
        ;

        return [$key => $value];
    }

    protected function bvPhoto(): Attribute
    {
        return $this->uploadFile();
    }

    protected function bvAdditionalPhotos(): Attribute
    {
        return $this->uploadFileArray();
    }

    protected function bTypeLabel(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->getAttribute('b_type')?->label
        );
    }

    protected function isListedLabel(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->getAttribute('is_listed')?->label
        );
    }

    protected function boNo(): Attribute
    {
        return Attribute::make(
            get: function ($value) {
                if (!$value) {
                    $datetime = \DateTime::createFromFormat('U.u', sprintf('%.6f', microtime(true)));
                    $datetime->setTimezone(new \DateTimeZone(date_default_timezone_get()));  // 转换为目标时区
                    $contract_number = $datetime->format('ymdHisv');

                    return 'BK'.$contract_number;
                }
            }
        );
    }
}
