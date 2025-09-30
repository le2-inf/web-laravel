<?php

namespace App\Http\Controllers\Admin\Auth;

use App\Attributes\PermissionAction;
use App\Attributes\PermissionType;
use App\Http\Controllers\Controller;
use App\Http\Middleware\CheckAdminIsMock;
use App\Models\Admin\AdminPermission;
use App\Models\Admin\AdminRole;
use App\Services\PaginateService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

#[PermissionType('员工角色管理')]
class RoleController extends Controller
{
    public function __construct()
    {
        $this->middleware(CheckAdminIsMock::class);
    }

    #[PermissionAction(PermissionAction::INDEX)]
    public function index(Request $request): Response
    {
        $this->options(true);
        $this->response()->withExtras();

        $query = AdminRole::query()
            ->where('name', '!=', config('setting.super_role.name'))
            ->with('permissions')
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
                    $builder->where('name', 'like', '%'.$value.'%');
                });
            },
        ]);

        return $this->response()->withData($paginate)->respond();
    }

    #[PermissionAction(PermissionAction::ADD)]
    public function create(Request $request): Response
    {
        $this->response()->withExtras(
            AdminPermission::options(),
        );

        return $this->response()->respond();
    }

    #[PermissionAction(PermissionAction::ADD)]
    public function store(Request $request): Response
    {
        $validator = Validator::make(
            $request->all(),
            [
                'name'         => ['required', Rule::unique(AdminRole::class, 'name')],
                '_permissions' => ['nullable'],
                'title'        => ['nullable'],
            ],
            [],
            trans_property(AdminRole::class)
        );

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $input = $validator->validated();

        DB::transaction(function () use (&$input) {
            $input['guard_name'] = 'web';

            $role = AdminRole::create($input);

            $permissions = $input['_permissions'] ?? [];
            if ($permissions) {
                foreach ($permissions as $item) {
                    $role->givePermissionTo($item);
                }
            }
        });

        $this->response()->withMessages(message_success(__METHOD__));

        return $this->response()->withRedirect(redirect()->route('roles.index'))->respond();
    }

    #[PermissionAction(PermissionAction::EDIT)]
    public function edit(Request $request, AdminRole $role): Response
    {
        abort_if(config('setting.super_role.name') == $role->name, 403, 'You Cannot Edit Super Admin Role!');

        $this->response()->withExtras(
            AdminPermission::options(),
        );

        $role->_permissions = $role->getPermissionNames();

        return $this->response()->withData($role)->respond();
    }

    #[PermissionAction(PermissionAction::EDIT)]
    public function update(Request $request, AdminRole $role): Response
    {
        $validator = Validator::make(
            $request->all(),
            [
                'name'         => ['required', Rule::unique(AdminRole::class, 'name')->ignore($role)],
                '_permissions' => ['nullable'],
                'title'        => ['nullable'],
            ],
            [],
            trans_property(AdminRole::class)
        );

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $input = $validator->validated();

        abort_if(config('setting.super_role.name') == $role->name, 403, 'You Cannot Edit Super Admin Role!');

        DB::transaction(function () use (&$input, &$role) {
            $permissions = $input['_permissions'] ?? [];

            $role->fill($input);
            $role->syncPermissions($permissions);
            $role->save();
        });

        $this->response()->withMessages(message_success(__METHOD__));

        return $this->response()->withRedirect(redirect()->route('roles.index'))->respond();
    }

    #[PermissionAction(PermissionAction::DELETE)]
    public function destroy(AdminRole $role): Response
    {
        abort_if(config('setting.super_role.name') == $role->name, 403, 'You Cannot delete Super Admin Role!');

        DB::transaction(function () use (&$role) {
            DB::table(config('permission.table_names.model_has_roles'))->where('role_id', $role->id)->delete();
            DB::table(config('permission.table_names.role_has_permissions'))->where('role_id', $role->id)->delete();
            $role->delete();
        });

        $this->response()->withMessages(message_success(__METHOD__));

        return $this->response()->withRedirect(redirect()->route('roles.index'))->respond();
    }

    #[PermissionAction(PermissionAction::SHOW)]
    public function show(Request $request, AdminRole $role): Response
    {
        abort_if(config('setting.super_role.name') == $role->name, 403, 'You Cannot Edit Super Admin Role!');

        $role->_permissionsNames = $role->getPermissionNames();

        return $this->response()->withData($role)->respond();
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
