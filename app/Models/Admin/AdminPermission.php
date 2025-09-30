<?php

namespace App\Models\Admin;

use App\Attributes\ClassName;
use Spatie\Permission\Models\Permission;

#[ClassName('员工权限')]
/**
 * @property int    $id
 * @property string $name       权限英文名
 * @property string $title      权限标题
 * @property string $guard_name
 */
class AdminPermission extends Permission
{
    public static function options(?\Closure $where = null): array
    {
        $key   = preg_replace('/^.*\\\/', '', get_called_class()).'Options';
        $value = static::query()->toBase()
            ->orderBy('group_name')->orderBy('name')
            ->get()
        ;

        return [$key => $value];
    }
}
