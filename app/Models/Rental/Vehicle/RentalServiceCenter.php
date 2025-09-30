<?php

namespace App\Models\Rental\Vehicle;

use App\Attributes\ColumnDesc;
use App\Enum\Vehicle\ScScStatus;
use App\Models\Admin\Admin;
use App\Models\ModelTrait;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

#[ColumnDesc('sc_name', required: true)]
/**
 * @property int                        $sc_id                     修理厂ID
 * @property string                     $sc_name                   修理厂名称
 * @property null|string                $sc_address                修理厂地址
 * @property null|string                $contact_name              联系人
 * @property null                       $contact_mobile
 * @property null|string                $contact_phone             联系电话
 * @property ScScStatus|string          $sc_status                 状态
 * @property null|string                $sc_note                   备注
 * @property null|array<int>            $permitted_admin_ids       用户权限
 *                                                                 -
 * @property RentalVehicleRepair[]      $RentalVehicleRepairs
 * @property RentalVehicleMaintenance[] $RentalVehicleMaintenances
 * @property RentalVehicleAccident[]    $RentalVehicleAccidents
 *                                                                 -
 * @property null|string                $sc_status_label           状态-中文
 */
class RentalServiceCenter extends Model
{
    use ModelTrait;

    protected $primaryKey = 'sc_id';

    protected $guarded = ['sc_id'];

    protected $appends = [
        'sc_status_label',
    ];

    protected $attributes = [
    ];

    protected $casts = [
        'status'              => ScScStatus::class,
        'permitted_admin_ids' => 'array',
    ];

    public function RentalVehicleRepairs(): HasMany
    {
        return $this->hasMany(RentalVehicleRepair::class, 'sc_id', 'sc_id');
    }

    public function RentalVehicleMaintenances(): HasMany
    {
        return $this->hasMany(RentalVehicleMaintenance::class, 'sc_id', 'sc_id');
    }

    public function RentalVehicleAccidents(): HasMany
    {
        return $this->hasMany(RentalVehicleAccident::class, 'sc_id', 'sc_id');
    }

    public static function options(?\Closure $where = null): array
    {
        $key = preg_replace('/^.*\\\/', '', get_called_class())
            .'Options';

        /** @var Admin $admin */
        $admin = Auth::user();

        $value = DB::query()
            ->from('rental_service_centers', 'sc')
            ->where('sc.status', ScScStatus::ENABLED)
            ->when(!$admin->hasRole(config('setting.super_role.name')), function (Builder $query) use ($admin) {
                $query->whereRaw('permitted_admin_ids @> ?', [json_encode([$admin->id])]);
            })
            ->select(DB::raw('name as text,sc_id as value'))
            ->get()
        ;

        return [$key => $value];
    }

    public static function indexQuery(array $search = []): Builder
    {
        return DB::query()
            ->from('rental_service_centers', 'sc')
            ->select('sc.*')
            ->addSelect(
                DB::raw(ScScStatus::toCaseSQL()),
            )
        ;
    }

    public static function nameKv(?string $name = null)
    {
        static $kv = null;

        if (null === $kv) {
            $kv = DB::query()
                ->from('rental_service_centers')
                ->select('sc_id', 'name')
                ->pluck('sc_id', 'name')
                ->toArray()
            ;
        }

        if ($name) {
            return $kv[$name] ?? null;
        }

        return $kv;
    }

    protected function scStatusLabel(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->getAttribute('status')?->label
        );
    }
}
