<?php

namespace App\Models\Rental\Vehicle;

use App\Attributes\ClassName;
use App\Enum\Rental\SoOrderStatus;
use App\Enum\Rental\SoRentalPaymentType;
use App\Enum\Rental\SoRentalType;
use App\Models\ModelTrait;
use App\Models\Rental\Sale\RentalSaleOrder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

#[ClassName('行程')]
/**
 * @property int         $vu_id       行程记录序号
 * @property int         $so_id       订单序号；指向订单表
 * @property int         $ve_id       车辆序号
 * @property int         $start_vi_id 行程开始的验车序号
 * @property null|int    $end_vi_id   行程结束的验车序号
 * @property null|string $vu_remark   行程备注
 */
class RentalVehicleUsage extends Model
{
    use ModelTrait;

    protected $primaryKey = 'vu_id';

    protected $guarded = ['vu_id'];

    public function RentalSaleOrder(): BelongsTo
    {
        return $this->belongsTo(RentalSaleOrder::class, 'so_id', 'so_id');
    }

    // 定义与原始车辆的关系
    public function RentalVehicle(): BelongsTo
    {
        return $this->belongsTo(RentalVehicle::class, 've_id', 've_id');
    }

    public function RentalVehicleInspectionStart(): BelongsTo
    {
        return $this->belongsTo(RentalVehicleInspection::class, 'start_vi_id', 'vi_id');
    }

    public function RentalVehicleInspectionEnd(): BelongsTo
    {
        return $this->belongsTo(RentalVehicleInspection::class, 'end_vi_id', 'vi_id');
    }

    public static function indexQuery(array $search = []): Builder
    {
        $ve_id = $search['ve_id'] ?? null;
        $so_id = $search['so_id'] ?? null;
        $cu_id = $search['cu_id'] ?? null;

        return DB::query()
            ->from('rental_vehicle_usages', 'vu')
            ->leftJoin('rental_sale_orders as so', 'so.so_id', '=', 'vu.so_id')
            ->leftJoin('rental_vehicles as ve', 've.ve_id', '=', 'vu.ve_id')
            ->leftJoin('rental_customers as cu', 'cu.cu_id', '=', 'so.cu_id')
            ->leftJoin('rental_vehicle_inspections as vi1', 'vi1.vi_id', '=', 'vu.start_vi_id')
            ->leftJoin('rental_vehicle_inspections as vi2', 'vi2.vi_id', '=', 'vu.end_vi_id')
            ->when($ve_id, function (Builder $query) use ($ve_id) {
                $query->where('vu.ve_id', '=', $ve_id);
            })
            ->when($so_id, function (Builder $query) use ($so_id) {
                $query->where('vu.so_id', '=', $so_id);
            })
            ->when($cu_id, function (Builder $query) use ($cu_id) {
                $query->where('so.cu_id', '=', $cu_id);
            })
            ->when(
                null === $ve_id && null === $so_id && null === $cu_id,
                function (Builder $query) {
                    $query->orderByDesc('vu.vu_id');
                },
                function (Builder $query) {
                    $query->orderBy('vu.vu_id');
                }
            )
            ->select('vu.*', 'so.*', 've.*', 'cu.*')
            ->addSelect(
                DB::raw(SoRentalType::toCaseSQL()),
                DB::raw(SoRentalPaymentType::toCaseSQL()),
                DB::raw(SoOrderStatus::toCaseSQL()),
                DB::raw('EXTRACT(EPOCH FROM vi1.inspection_datetime - vi2.inspection_datetime) / 86400.0 as vu_interval_day'),
                DB::raw("to_char(vi1.inspection_datetime, 'YYYY-MM-DD HH24:MI:SS') as start_inspection_datetime_"),
                DB::raw("to_char(vi2.inspection_datetime, 'YYYY-MM-DD HH24:MI:SS') as end_inspection_datetime_"),
            )
        ;
    }

    public static function options(?\Closure $where = null): array
    {
        return [];
    }
}
