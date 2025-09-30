<?php

namespace App\Http\Controllers\Admin\Auth;

use App\Attributes\PermissionAction;
use App\Attributes\PermissionType;
use App\Http\Controllers\Controller;
use App\Http\Middleware\CheckAdminIsMock;
use App\Models\Admin\AdminPermission;
use App\Services\PaginateService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

#[PermissionType('员工权限管理')]
class PermissionController extends Controller
{
    public function __construct()
    {
        $this->middleware(CheckAdminIsMock::class);
    }

    #[PermissionAction(PermissionAction::INDEX)]
    public function index(Request $request)
    {
        $this->options(true);
        $this->response()->withExtras();

        $query = AdminPermission::query()
            ->orderBy('name')
            ->with('roles')
        ;

        $paginate = new PaginateService(
            [],
            [['name', 'asc']],
            ['kw'],
            []
        );

        $paginate->paginator($query, $request, [
            'kw__func' => function ($value, Builder $builder) {
                $builder->where(function (Builder $builder) use ($value) {
                    $builder->where('name', 'like', '%'.$value.'%')->orWhere('title', 'like', '%'.$value.'%');
                });
            },
        ]);

        return $this->response()->withData($paginate)->respond();
    }

    public function store(Request $request): Response
    {
        $validator = Validator::make(
            $request->all(),
            [
                'name'  => ['required', Rule::unique(AdminPermission::class, 'name')],
                'title' => ['nullable'],
            ],
            [],
            trans_property(AdminPermission::class)
        );

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $input = $validator->validated();

        DB::transaction(function () use (&$input, &$permission) {
            $permission = AdminPermission::query()->create($input);
        });

        $this->response()->withMessages(message_success(__METHOD__));

        return $this->response()->withRedirect(redirect()->route('permissions.index'))->respond();
    }

    public function update(Request $request, AdminPermission $permission): Response
    {
        $validator = Validator::make(
            $request->all(),
            [
                'name'  => ['required', Rule::unique(AdminPermission::class, 'name')->ignore($permission)],
                'title' => ['nullable'],
            ],
            [],
            trans_property(AdminPermission::class)
        );

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $input = $validator->validated();

        DB::transaction(function () use (&$input, &$permission) {
            $permission->fill($input);

            $permission->save();
        });

        $this->response()->withMessages(message_success(__METHOD__));

        return $this->response()->withRedirect(redirect()->route('permissions.index'))->respond();
    }

    public function destroy(AdminPermission $permission): Response
    {
        DB::transaction(function () use (&$permission) {
            DB::table('model_has_permissions')->where('permission_id', $permission->id)->delete();
            DB::table('role_has_permissions')->where('permission_id', $permission->id)->delete();
            $permission->delete();
        });

        $this->response()->withMessages(message_success(__METHOD__));

        return $this->response()->withRedirect(redirect()->back())->respond();
    }

    public static function labelOptions(Controller $controller): void
    {
        $controller->response()->withExtras(
        );
    }

    protected function options(?bool $with_group_count = false): void
    {
        $this->response()->withExtras(
        );
    }
}
