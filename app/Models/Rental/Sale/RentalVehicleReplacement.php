<?php

namespace App\Models\Rental\Sale;

use App\Attributes\ClassName;
use App\Enum\Rental\VrReplacementStatus;
use App\Enum\Rental\VrReplacementType;
use App\Models\ModelTrait;
use App\Models\Rental\Vehicle\RentalVehicle;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

#[ClassName('换车')]
/**
 * @property int             $vr_id                  换车记录序号
 * @property int             $so_id                  订单序号；指向租赁订单表
 * @property mixed           $replacement_type       换车类型；临时或永久
 * @property int             $current_ve_id          需换车车辆序号
 * @property int             $new_ve_id              新车车辆序号
 * @property null|Carbon     $replacement_date       换车日期
 * @property null|Carbon     $replacement_start_date 换车开始日期
 * @property null|Carbon     $replacement_end_date   换车结束日期
 * @property null|mixed      $replacement_status     换车状态
 * @property null|mixed      $additional_photos      附加照片；存储照片路径的 JSON 数组
 * @property null|string     $vr_remark              换车备注
 * @property RentalSaleOrder $RentalSaleOrder
 * @property RentalVehicle   $CurrentVehicle
 * @property RentalVehicle   $NewVehicle
 * @property string          $current_ve_plate_no    旧车车牌号
 * @property string          $new_ve_plate_no        新车车牌号
 */
class RentalVehicleReplacement extends Model
{
    use ModelTrait;

    protected $primaryKey = 'vr_id';

    protected $guarded = ['vr_id'];

    protected $attributes = [];

    protected $appends = [
    ];

    protected $casts = [
        'replacement_type' => VrReplacementType::class,
    ];

    public function RentalSaleOrder(): BelongsTo
    {
        return $this->belongsTo(RentalSaleOrder::class, 'so_id', 'so_id');
    }

    public function CurrentVehicle(): BelongsTo
    {
        return $this->belongsTo(RentalVehicle::class, 'current_ve_id', 've_id');
    }

    public function NewVehicle(): BelongsTo
    {
        return $this->belongsTo(RentalVehicle::class, 'new_ve_id', 've_id');
    }

    public static function indexQuery(array $search = []): Builder
    {
        $so_id = $search['so_id'] ?? null;
        $cu_id = $search['cu_id'] ?? null;

        return DB::query()
            ->from('rental_vehicle_replacements', 'vr')
            ->leftJoin('rental_sale_orders as so', 'so.so_id', '=', 'vr.so_id')
            ->leftJoin('rental_vehicles as ve1', 've1.ve_id', '=', 'vr.current_ve_id')
            ->leftJoin('rental_vehicles as ve2', 've2.ve_id', '=', 'vr.new_ve_id')
            ->leftJoin('rental_customers as cu', 'cu.cu_id', '=', 'so.cu_id')
            ->when($so_id, function (Builder $query) use ($so_id) {
                $query->where('so.so_id', '=', $so_id);
            })
            ->when($cu_id, function (Builder $query) use ($cu_id) {
                $query->where('so.cu_id', '=', $cu_id);
            })
            ->when(
                null === $so_id && null === $cu_id,
                function (Builder $query) {
                    $query->orderByDesc('vr.vr_id');
                },
                function (Builder $query) {
                    $query->orderBy('vr.vr_id');
                }
            )
            ->select('vr.*', 'so.*', 'cu.*', 've1.plate_no as current_ve_plate_no', 've2.plate_no as new_ve_plate_no')
            ->addSelect(
                DB::raw(VrReplacementType::toCaseSQL()),
                DB::raw(VrReplacementStatus::toCaseSQL()),
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
