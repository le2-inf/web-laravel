<?php

namespace App\Http\Controllers\Admin\Payment;

use App\Attributes\PermissionAction;
use App\Attributes\PermissionType;
use App\Enum\Payment\RptIsActive;
use App\Http\Controllers\Controller;
use App\Models\Payment\PaymentType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

#[PermissionType('财务类型配置')]
class PaymentTypeController extends Controller
{
    public static function labelOptions(Controller $controller): void
    {
        $controller->response()->withExtras(
        );
    }

    #[PermissionAction(PermissionAction::SETTING)]
    public function show(Request $request): Response
    {
        $this->response()->withExtras(
            PaymentType::indexOptions(),
        );

        $ids = PaymentType::query()
            ->where('is_active', '=', RptIsActive::ENABLED)
            ->pluck('pt_id')
            ->toArray()
        ;

        return $this->response()->withData(['selected_types' => $ids])->respond();
    }

    #[PermissionAction(PermissionAction::SETTING)]
    public function update(Request $request): Response
    {
        $validator = Validator::make(
            $request->all(),
            [
                'selected_types' => ['required', 'array'],
            ],
            [],
            []
        );

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $input = $validator->validated();

        DB::transaction(function () use ($input) {
            PaymentType::query()->whereIn('pt_id', $input['selected_types'])->update(['is_active' => RptIsActive::ENABLED]);
            PaymentType::query()->whereNotIn('pt_id', $input['selected_types'])->update(['is_active' => RptIsActive::DISABLED]);
        });

        return $this->response()->withData($input)->respond();
    }

    protected function options(?bool $with_group_count = false): void
    {
        $this->response()->withExtras(
        );
    }
}
