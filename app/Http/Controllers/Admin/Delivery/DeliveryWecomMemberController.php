<?php

namespace App\Http\Controllers\Admin\Delivery;

use App\Attributes\PermissionAction;
use App\Attributes\PermissionType;
use App\Http\Controllers\Controller;
use App\Models\Admin\Admin;
use App\Models\Admin\AdminExt;
use App\Models\Sale\SaleOrderExt;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

#[PermissionType('企业微信群机器人管理')]
class DeliveryWecomMemberController extends Controller
{
    public static function labelOptions(Controller $controller): void
    {
        $controller->response()->withExtras(
        );
    }

    #[PermissionAction(PermissionAction::SHOW)]
    public function show(Request $request): Response
    {
        $this->options();
        $this->response()->withExtras(
        );

        $items = Admin::indexQuery()
            ->leftJoin('admin_exts as ext', 'ext.adm_id', '=', 'adm.id')
            ->select('id as adm_id', 'name', 'wecom_name')
            ->orderByDesc('adm.id')
            ->get()
        ;

        return $this->response()->withData(compact('items'))->respond();
    }

    #[PermissionAction(PermissionAction::EDIT)]
    public function update(Request $request): Response
    {
        $validator = Validator::make(
            $request->all(),
            [
                'items'              => ['bail', 'nullable', 'array'],
                'items.*.adm_id'     => ['bail', 'required', 'integer', Rule::exists(Admin::class, 'id')],
                'items.*.wecom_name' => ['bail', 'nullable', 'max:255'],
            ],
            [],
            trans_property(SaleOrderExt::class)
        )
            ->after(function (\Illuminate\Validation\Validator $validator) {
                if (!$validator->failed()) {
                }
            })
        ;
        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $input = $validator->validated();

        $items = collect($input['items']);

        DB::transaction(function () use (&$items) {
            foreach ($items->chunk(50) as $chunks) {
                AdminExt::query()->upsert($chunks->all(), ['adm_id'], ['wecom_name']);
            }
            AdminExt::query()->whereNotIn('adm_id', $items->pluck('adm_id')->all())->delete();
        });

        return $this->response()->respond();
    }

    protected function options(?bool $with_group_count = false): void
    {
        $this->response()->withExtras(
        );
    }
}
