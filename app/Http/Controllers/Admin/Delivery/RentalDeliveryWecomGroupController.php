<?php

namespace App\Http\Controllers\Admin\Delivery;

use App\Attributes\PermissionAction;
use App\Attributes\PermissionType;
use App\Enum\Rental\SoOrderStatus;
use App\Enum\Rental\SoRentalPaymentType;
use App\Enum\Rental\SoRentalType_ShortOnlyShort;
use App\Http\Controllers\Controller;
use App\Models\Rental\Sale\RentalSaleOrder;
use App\Models\Rental\Sale\RentalSaleOrderExt;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

#[PermissionType('企业微信群机器人管理')]
class RentalDeliveryWecomGroupController extends Controller
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
            RentalSaleOrder::options(
                where: function (Builder $builder) {
                    $builder->whereIn('so.order_status', [SoOrderStatus::SIGNED]);
                }
            ),
        );

        $items = RentalSaleOrderExt::indexQuery()
            ->orderByDesc('soe.soe_id')
            ->whereIn('so.order_status', [SoOrderStatus::SIGNED])
            ->addSelect(
                DB::raw(sprintf(
                    "CONCAT(cu.contact_name,'|',%s,'|', ve.plate_no ,'|',  %s, %s ,'|', %s ) as text,so.so_id as value",
                    "(CONCAT(SUBSTRING(cu.contact_phone, 1, 0), '', SUBSTRING(cu.contact_phone, 8, 4)) )",
                    SoRentalPaymentType::toCaseSQL(false),
                    SoRentalType_ShortOnlyShort::toCaseSQL(false),
                    SoOrderStatus::toCaseSQL(false)
                ))
            )
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
                'items'                       => ['bail', 'nullable', 'array'],
                'items.*.so_id'               => ['bail', 'required', 'integer', Rule::exists(RentalSaleOrder::class)],
                'items.*.soe_wecom_group_url' => ['bail', 'required', 'max:255'],
            ],
            [],
            trans_property(RentalSaleOrderExt::class)
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
                RentalSaleOrderExt::query()->upsert($chunks->all(), ['so_id'], ['soe_wecom_group_url']);
            }

            RentalSaleOrderExt::query()->whereNotIn('so_id', $items->pluck('so_id')->all())->delete();
        });

        return $this->response()->respond();
    }

    protected function options(?bool $with_group_count = false): void
    {
        $this->response()->withExtras(
        );
    }
}
