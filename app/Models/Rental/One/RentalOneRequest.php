<?php

namespace App\Models\Rental\One;

use App\Models\ModelTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * @property int    $or_id
 * @property string $turn
 * @property string $url
 * @property string $key
 * @property string $headers
 * @property string $form_data
 * @property string $status_code
 * @property string $response
 */
class RentalOneRequest extends Model
{
    use ModelTrait;

    protected $primaryKey = 'or_id';

    protected $guarded = ['or_id'];

    public static function indexQuery(array $search = []): Builder
    {
        return DB::query();
    }

    public static function options(?\Closure $where = null): array
    {
        return [];
    }
}
