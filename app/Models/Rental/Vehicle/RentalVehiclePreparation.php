<?php

namespace App\Models\Rental\Vehicle;

use App\Attributes\ClassName;
use App\Enum\Vehicle\VeStatusDispatch;
use App\Enum\Vehicle\VeStatusRental;
use App\Enum\Vehicle\VeStatusService;
use App\Enum\YesNo;
use App\Models\ModelTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

#[ClassName('车辆整备')]
/**
 * @property int           $vp_id             整备序号
 * @property int           $ve_id             车辆序号，指向车辆表
 * @property int           $annual_check_is   年审是否完备;1表示是，0表示否
 * @property null|Carbon   $annual_check_dt
 * @property int           $insured_check_is  保险是否完备；1表示有，0表示无
 * @property null|Carbon   $insured_check_dt
 * @property int           $vehicle_check_is  车况是否完备；1表示是，0表示否
 * @property null|Carbon   $vehicle_check_dt
 * @property int           $document_check_is 证件是否完备；1表示是，0表示否
 * @property null|Carbon   $document_check_dt
 * @property RentalVehicle $RentalVehicle
 */
class RentalVehiclePreparation extends Model
{
    use ModelTrait;

    protected $primaryKey = 'vp_id';

    protected $guarded = ['vp_id'];

    protected $casts = [
        'annual_check_dt'   => 'datetime:Y-m-d H:i:s',
        'document_check_dt' => 'datetime:Y-m-d H:i:s',
        'insured_check_dt'  => 'datetime:Y-m-d H:i:s',
        'vehicle_check_dt'  => 'datetime:Y-m-d H:i:s',
        'annual_check_is'   => YesNo::class,
        'insured_check_is'  => YesNo::class,
        'vehicle_check_is'  => YesNo::class,
        'document_check_is' => YesNo::class,
    ];

    public function RentalVehicle(): BelongsTo
    {
        return $this->belongsTo(RentalVehicle::class, 've_id', 've_id');
    }

    public static function indexQuery(array $search = []): Builder
    {
        return DB::query()
            ->from('rental_vehicle_preparations', 'vp')
            ->leftJoin('rental_vehicles as ve', 've.ve_id', '=', 'vp.ve_id')
            ->leftJoin('rental_vehicle_models as vm', 've.vm_id', '=', 'vm.vm_id')
            ->select('vp.*', 've.*', 'vm.brand_name', 'vm.model_name')
            ->addSelect(
                DB::raw(YesNo::toCaseSQL(true, 'vp.annual_check_is')),
                DB::raw(YesNo::toCaseSQL(true, 'vp.insured_check_is')),
                DB::raw(YesNo::toCaseSQL(true, 'vp.vehicle_check_is')),
                DB::raw(YesNo::toCaseSQL(true, 'vp.document_check_is')),
                DB::raw(VeStatusService::toCaseSQL()),
                DB::raw(VeStatusRental::toCaseSQL()),
                DB::raw(VeStatusDispatch::toCaseSQL()),
                DB::raw("to_char(annual_check_dt, 'YYYY-MM-DD HH24:MI') as annual_check_dt_"),
                DB::raw("to_char(document_check_dt, 'YYYY-MM-DD HH24:MI') as document_check_dt_"),
                DB::raw("to_char(insured_check_dt, 'YYYY-MM-DD HH24:MI') as insured_check_dt_"),
                DB::raw("to_char(vehicle_check_dt, 'YYYY-MM-DD HH24:MI') as vehicle_check_dt_"),
            )
        ;
    }

    public static function options(?\Closure $where = null): array
    {
        return [];
    }
}
