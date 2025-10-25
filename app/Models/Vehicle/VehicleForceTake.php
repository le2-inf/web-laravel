<?php

namespace App\Models\Vehicle;

use App\Attributes\ClassName;
use App\Enum\Vehicle\VftForceTakeStatus;
use App\Models\_\ModelTrait;
use App\Models\Customer\Customer;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

#[ClassName('强制收车')]
/**
 * @property int                            $vft_id            序号
 * @property int                            $ve_id             车牌号
 * @property int                            $cu_id             客户姓名
 * @property Carbon                         $force_take_time   强制收车日期
 * @property null|string|VftForceTakeStatus $force_take_status 收车状态
 * @property null|mixed                     $additional_photos 附加照片
 * @property null|string                    $reason            原因
 */
class VehicleForceTake extends Model
{
    use ModelTrait;

    protected $primaryKey = 'vft_id';

    protected $guarded = ['vft_id'];

    protected $attributes = [];

    protected $casts = [
        'force_take_time'   => 'datetime:Y-m-d',
        'force_take_status' => VftForceTakeStatus::class,
    ];

    protected $appends = [
        'force_take_status_label',
    ];

    public function Vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 've_id', 've_id')->with('VehicleModel');
    }

    public function Customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'cu_id', 'cu_id');
    }

    public static function indexQuery(array $search = []): Builder
    {
        $ve_id = $search['ve_id'] ?? null;

        return DB::query()
            ->from('vehicle_force_takes', 'vft')
            ->leftJoin('vehicles as ve', 've.ve_id', '=', 'vft.ve_id')
            ->leftJoin('vehicle_models as vm', 'vm.vm_id', '=', 've.vm_id')
            ->leftJoin('customers as cu', 'cu.cu_id', '=', 'vft.cu_id')
            ->when($ve_id, function (Builder $query) use ($ve_id) {
                $query->where('vft.ve_id', '=', $ve_id);
            })
            ->when(
                null === $ve_id,
                function (Builder $query) {
                    $query->orderByDesc('vft.vft_id');
                },
                function (Builder $query) {
                    $query->orderBy('vft.vft_id');
                }
            )
            ->select('vft.*', 'cu.contact_name', 've.plate_no', 'vm.brand_name', 'vm.model_name')
            ->addSelect(
                DB::raw(VftForceTakeStatus::toCaseSQL()),
            )
        ;
    }

    public static function options(?\Closure $where = null): array
    {
        return [];
    }

    protected function forceTakeStatusLabel(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->getAttribute('force_take_status')?->label
        );
    }

    protected function additionalPhotos(): Attribute
    {
        return $this->uploadFileArray();
    }
}
