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
        $days = 3000;

        $targetDate = Carbon::today()->addDays($days)->toDateString();

        return DB::query()
            ->from('rental_vehicles', 've')
            ->leftJoin('rental_vehicle_models as vm', 've.vm_id', '=', 'vm.vm_id')
            ->select('ve.*', 'vm.brand_name', 'vm.model_name')
            ->addSelect(
                DB::raw('EXTRACT(EPOCH FROM now() - ve.ve_license_valid_until_date) / 86400.0 as valid_until_date_interval'),
            )
            ->where('ve.status_service', VeStatusService::YES)
            ->where(function ($q) use ($targetDate) {
                $q->where('ve.ve_license_valid_until_date', '<=', $targetDate);
            })
        ;
    }

    public static function options(?\Closure $where = null): array
    {
        return [];
    }
}
