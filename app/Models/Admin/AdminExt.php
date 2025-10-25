<?php

namespace App\Models\Admin;

use App\Attributes\ClassName;
use App\Models\_\ModelTrait;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

#[ClassName('员工扩展')]
/**
 * @property int    $adm_id     序号
 * @property string $wecom_name 企业微信账号
 * @property Admin  $Admin
 */
class AdminExt extends Authenticatable
{
    use HasApiTokens;
    use Notifiable;

    use HasRoles;

    use ModelTrait;

    protected $attributes = [];

    protected $appends = [];

    protected $guarded = [
    ];

    public function Admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'adm_id', 'id');
    }

    public static function indexQuery(array $search = []): Builder
    {
        return DB::query()->from('admins as adm');
    }

    public static function options(?\Closure $where = null): array
    {
        return [];
    }
}
