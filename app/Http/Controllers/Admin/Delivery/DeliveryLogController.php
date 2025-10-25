<?php

namespace App\Http\Controllers\Admin\Delivery;

use App\Attributes\PermissionAction;
use App\Attributes\PermissionType;
use App\Enum\Delivery\DcDcKey;
use App\Enum\Delivery\DlDcKey;
use App\Enum\Delivery\DlSendStatus;
use App\Http\Controllers\Controller;
use App\Models\Delivery\DeliveryLog;
use App\Services\PaginateService;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

#[PermissionType('消息管理')]
class DeliveryLogController extends Controller
{
    public static function labelOptions(Controller $controller): void
    {
        $controller->response()->withExtras(
            DlDcKey::labelOptions(),
            DlSendStatus::labelOptions(),
        );
    }

    #[PermissionAction(PermissionAction::INDEX)]
    public function index(Request $request): Response
    {
        $this->options(true);

        $query = DeliveryLog::indexQuery();

        $paginate = new PaginateService(
            [],
            [['dl.dl_id', 'desc']],
            ['kw'],
            []
        );

        $paginate->paginator($query, $request, [
            'kw__func' => function ($value, Builder $builder) {
                $builder->where(function (Builder $builder) use ($value) {
                    $builder->where('dc.dc_title', 'like', '%'.$value.'%');
                });
            },
        ]);

        return $this->response()->withData($paginate)->respond();
    }

    #[PermissionAction(PermissionAction::ADD)]
    public function create(Request $request): Response
    {
        $this->options();

        $deliveryLog = new DeliveryLog([
        ]);

        $this->response()->withExtras(
            DcDcKey::configDic(),
        );

        return $this->response()->withData($deliveryLog)->respond();
    }

    #[PermissionAction(PermissionAction::ADD)]
    public function store(Request $request): Response
    {
        return $this->update($request, null);
    }

    #[PermissionAction(PermissionAction::SHOW)]
    public function show(DeliveryLog $deliveryLog): Response
    {
        $this->options();
        $this->response()->withExtras(
        );

        return $this->response()->withData($deliveryLog)->respond();
    }

    #[PermissionAction(PermissionAction::EDIT)]
    public function edit(DeliveryLog $deliveryLog): Response
    {
        $this->options();

        return $this->response()->withData($deliveryLog)->respond();
    }

    #[PermissionAction(PermissionAction::EDIT)]
    public function update(Request $request, ?DeliveryLog $deliveryLog): Response
    {
        return $this->response()->withData($deliveryLog)->respond();
    }

    #[PermissionAction(PermissionAction::DELETE)]
    public function destroy(DeliveryLog $deliveryLog): Response
    {
        return $this->response()->withData($deliveryLog)->respond();
    }

    protected function options(?bool $with_group_count = false): void
    {
        $this->response()->withExtras(
            DlDcKey::options(),
            DlSendStatus::options(),
        );
    }
}
