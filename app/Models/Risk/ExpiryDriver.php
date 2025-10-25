<?php

namespace App\Models\Risk;

use App\Enum\Customer\CuCuType;
use App\Enum\Customer\CuiCuiGender;
use App\Models\_\ModelTrait;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;

class ExpiryDriver extends Model
{
    use ModelTrait;

    public static function indexQuery(array $search = []): Builder
    {
        $days = 30;

        $targetDate = Carbon::today()->addDays($days)->toDateString();

        $cu_id = $search['cu_id'] ?? null;

        return DB::query()
            ->from('customer_individuals', 'cui')
            ->leftJoin('customers as cu', function (JoinClause $join) {
                $join->on('cui.cu_id', '=', 'cu.cu_id')
                    ->where('cu.cu_type', '=', CuCuType::INDIVIDUAL)
                ;
            })
//            ->where('cu.cu_type', CuCustomerType::INDIVIDUAL)
            ->where(function (Builder $q) use ($targetDate) {
                $q->where('cui.cui_driver_license_expiry_date', '<=', $targetDate)
                    ->orWhere('cui_id_expiry_date', '<=', $targetDate)
                ;
            })
            ->when($cu_id, function (Builder $query) use ($cu_id) {
                $query->where('cu.cu_id', '=', $cu_id);
            })
            ->select('cu.*', 'cui.*')
            ->addSelect(
                DB::raw(CuCuType::toCaseSQL()),
                DB::raw(CuiCuiGender::toCaseSQL()),
            )
        ;
    }

    public static function options(?\Closure $where = null): array
    {
        return [];
    }
}
