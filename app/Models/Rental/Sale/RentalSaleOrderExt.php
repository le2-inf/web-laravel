<?php

namespace App\Models\Rental\Sale;

use App\Models\ModelTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * @property int    $soe_id              扩展订单ID
 * @property int    $so_id               订单ID
 * @property string $soe_wecom_group_url 微信群机器人url
 *
 * -- relation
 * @property RentalSaleOrder $RentalSaleOrder
 */
class RentalSaleOrderExt extends Model
{
    use ModelTrait;

    protected $primaryKey = 'soe_id';

    protected $guarded = [];

    public function RentalSaleOrder(): BelongsTo
    {
        return $this->BelongsTo(RentalSaleOrder::class, 'so_id', 'so_id');
    }

    public static function indexQuery(array $search = []): Builder
    {
        return DB::query()
            ->from('rental_sale_order_exts', 'soe')
            ->leftJoin('rental_sale_orders as so', 'soe.so_id', '=', 'so.so_id')
            ->leftJoin('rental_vehicles as ve', 've.ve_id', '=', 'so.ve_id')
            ->leftJoin('rental_customers as cu', 'cu.cu_id', '=', 'so.cu_id')
            ->orderByDesc('soe.soe_id')
            ->select('soe.soe_id', 'soe.so_id', 'soe.soe_wecom_group_url')
        ;
    }

    public static function options(?\Closure $where = null): array
    {
        return [];
    }
}
