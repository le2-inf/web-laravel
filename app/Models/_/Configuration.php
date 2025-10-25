<?php

namespace App\Models\_;

use App\Attributes\ClassName;
use App\Enum\Config\CfgMasked;
use App\Enum\Config\CfgUsageCategory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

#[ClassName('设定值')]
/**
 * @property int    $cfg_id
 * @property string $cfg_key        设定名
 * @property string $cfg_value      设定值
 * @property string $cfg_remark     设定备注
 * @property int    $usage_category 使用区分
 * @property int    $masked         是否打码
 */
class Configuration extends Model
{
    use ModelTrait;

    public const perpage_num_default = 50;

    public const configurations_cache_name = 'configurations';

    public const configurations_cache_ttl = 600;

    protected $primaryKey = 'cfg_id';

    protected $guarded = ['cfg_id'];

    protected $appends = [
        'usage_category_label',
        'masked_label',
        'cfg_value_show',
    ];

    protected $attributes = [];

    protected $casts = [
        'usage_category' => CfgUsageCategory::class,
        'masked'         => CfgMasked::class,
    ];

    public static function configs(): array
    {
        return self::query()
            ->select('cfg_key', 'cfg_value')
            ->pluck('cfg_value', 'cfg_key')
            ?->toArray()
        ;
    }

    public static function indexQuery(array $search = []): Builder
    {
        return DB::query();
    }

    public static function fetch(array|string $keys, $cache = true): array|string|null
    {
        if (!$cache) {
            Cache::forget(static::configurations_cache_name);
        }

        $configurations = Cache::remember(static::configurations_cache_name, static::configurations_cache_ttl, function () {
            return static::configs();
        });

        if (is_array($keys)) {
            return Arr::only($configurations, $keys);
        }

        return $configurations[$keys] ?? null;
    }

    public static function forget()
    {
        $cacheKey = static::configurations_cache_name;

        Cache::forget($cacheKey);
    }

    public static function getPerPageNum($fullRouteName): int
    {
        // get current route group name of request
        $routeGroupName = $fullRouteName;
        if (($position = strpos($fullRouteName, '.')) && false !== $position) {
            $routeGroupName = substr($fullRouteName, 0, $position);
        }

        // current route prePage config key name
        $currentPerPageKey = sprintf('perpage.%s', $routeGroupName);

        // global default perPage config key name
        $globalPerPageKey = 'perpage';

        $configValueArr = static::fetch([
            $currentPerPageKey,
            $globalPerPageKey,
        ]);

        $configValueArr = array_filter($configValueArr, function ($v, $k) {
            return is_numeric($v) && $v > 0 && intval($v) == $v;
        }, ARRAY_FILTER_USE_BOTH);

        return $configValueArr[$currentPerPageKey] ?? $configValueArr[$globalPerPageKey] ?? static::perpage_num_default;
    }

    public static function options(?\Closure $where = null): array
    {
        return [];
    }

    protected function maskedLabel(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->getAttributeValue('masked')?->label ?? ''
        );
    }

    protected function cfgValueShow(): Attribute
    {
        return Attribute::make(
            get: function () {
                $configValueText = $this->getRawOriginal('cfg_value');
                $masked          = $this->getRawOriginal('masked');

                if (!is_null($masked) && CfgMasked::YES == $masked) {
                    $configValueText = '***********************';
                }

                return $configValueText;
            }
        );
    }

    protected function usageCategoryLabel(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->getAttributeValue('usage_category')?->label
        );
    }
}
