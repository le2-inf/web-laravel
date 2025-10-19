<?php

namespace App\Models\Rental\Vehicle;

use App\Attributes\ClassName;
use App\Enum\Vehicle\VeStatusService;
use App\Enum\Vehicle\VmVmStatus;
use App\Models\ModelTrait;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

#[ClassName('车型')]
/**
 * @property int               $vm_id             车型序号
 * @property string            $brand_name        品牌
 * @property string            $model_name        车型
 * @property string            $brand_model       品牌车型
 * @property string|VmVmStatus $vm_status         状态
 * @property null|array        $additional_photos 附加照片；存储照片路径的 JSON 数组
 */
class RentalVehicleModel extends Model
{
    use ModelTrait;

    protected $primaryKey = 'vm_id';

    protected $guarded = ['vm_id'];

    protected $appends = [
        'vm_status_label',
    ];

    protected $attributes = [];

    public function Vehicles(): HasMany
    {
        return $this->hasMany(RentalVehicle::class, 'vm_id', 'vm_id');
    }

    public static function options(?\Closure $where = null): array
    {
        $key   = preg_replace('/^.*\\\/', '', get_called_class()).'Options';
        $value = static::query()->toBase()
            ->select(DB::raw("(brand_name || '-' || model_name) as text,vm_id as value"))
            ->get()
        ;

        return [$key => $value];
    }

    public static function indexQuery(array $search = []): Builder
    {
        return DB::query()
            ->from('rental_vehicle_models', 'vm')
            ->select(
                'vm.*',
                DB::raw("COUNT(ve.ve_id) FILTER (WHERE ve.status_service = '".VeStatusService::YES."') AS vehicle_count_service"),
                DB::raw("COUNT(ve.ve_id) FILTER (WHERE ve.status_service = '".VeStatusService::NO."') AS vehicle_count_un_service"),
                DB::raw(VmVmStatus::toCaseSQL()),
            )
            ->leftJoin('rental_vehicles as ve', 'vm.vm_id', '=', 've.vm_id')
            ->groupBy('vm.vm_id')
        ;
    }

    public static function indexColumns(): array
    {
        return [
            'RentalVehicleModel.vm_id'      => fn ($item) => $item->vm_id,
            'RentalVehicleModel.brand_name' => fn ($item) => $item->brand_name,
            'RentalVehicleModel.model_name' => fn ($item) => $item->model_name,
        ];
    }

    protected function casts(): array
    {
        return [
            'vm_status' => VmVmStatus::class,
        ];
    }

    protected function vmStatusLabel(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->getAttribute('vm_status')?->label
        );
    }

    protected function additionalPhotos(): Attribute
    {
        return $this->uploadFileArray();
    }
}
