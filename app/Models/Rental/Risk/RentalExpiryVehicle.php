<?php

namespace App\Models\Rental\Risk;

use App\Enum\Vehicle\VeStatusService;
use App\Models\ModelTrait;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class RentalExpiryVehicle extends Model
{
    use ModelTrait;

    public static function indexQuery(array $search = []): Builder
    {
        $days = 60;

        $targetDate = Carbon::today()->addDays($days)->toDateString();

        return DB::query()
            ->from('rental_vehicles', 've')
            ->leftJoin('rental_vehicle_models as vm', 've.vm_id', '=', 'vm.vm_id')
            ->select('ve.*', 'vm.brand_name', 'vm.model_name')
            ->addSelect(
                DB::raw('trunc(EXTRACT(EPOCH FROM now() - ve.ve_cert_valid_to) / 86400.0,0) as ve_cert_valid_interval'),
            )
            ->where('ve.status_service', '=', VeStatusService::YES)
            ->where('ve.ve_cert_valid_to', '<=', $targetDate)
        ;
    }

    public static function options(?\Closure $where = null): array
    {
        return [];
    }
}
