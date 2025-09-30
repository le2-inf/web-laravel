<?php

namespace App\Http\Controllers\Admin\Device;

use App\Attributes\PermissionAction;
use App\Attributes\PermissionType;
use App\Enum\Vehicle\VeStatusService;
use App\Http\Controllers\Controller;
use App\Models\Admin\Admin;
use App\Models\Iot\IotDevice;
use App\Models\Iot\IotDeviceBinding;
use App\Models\Rental\Vehicle\RentalVehicle;
use App\Services\PaginateService;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

#[PermissionType('设备绑定管理')]
class IotDeviceBindingController extends Controller
{
    public static function labelOptions(Controller $controller): void
    {
        $controller->response()->withExtras(
        );
    }

    #[PermissionAction(PermissionAction::INDEX)]
    public function index(Request $request): Response
    {
        $this->options(true);

        $query = IotDeviceBinding::indexQuery();

        $paginate = new PaginateService(
            [],
            [['db.db_id', 'desc']],
            [],
            []
        );

        $paginate->paginator($query, $request, []);

        return $this->response()->withData($paginate)->respond();
    }

    #[PermissionAction(PermissionAction::SHOW)]
    public function show(IotDeviceBinding $iotDeviceBinding): Response
    {
        $this->options();
        $this->response()->withExtras(
        );

        return $this->response()->withData($iotDeviceBinding)->respond();
    }

    #[PermissionAction(PermissionAction::ADD)]
    public function create(Request $request): Response
    {
        return $this->edit($request, null);
    }

    #[PermissionAction(PermissionAction::EDIT)]
    public function edit(Request $request, ?IotDeviceBinding $iotDeviceBinding): Response
    {
        $this->options();

        $this->response()->withExtras(
            Admin::optionsWithRoles(),
            RentalVehicle::options(),
        );

        if (null === $iotDeviceBinding) {
            $iotDeviceBinding = new IotDeviceBinding([
                'start_at'     => now(),
                'processed_by' => Auth::id(),
            ]);
        }

        return $this->response()->withData($iotDeviceBinding)->respond();
    }

    #[PermissionAction(PermissionAction::ADD)]
    public function store(Request $request): Response
    {
        return $this->update($request, null);
    }

    #[PermissionAction(PermissionAction::EDIT)]
    public function update(Request $request, ?IotDeviceBinding $iotDeviceBinding): Response
    {
        $validator = Validator::make(
            $request->all(),
            [
                'd_id'         => ['required', 'integer', Rule::exists(IotDevice::class)],
                've_id'        => ['required', 'integer', Rule::exists(RentalVehicle::class)->where('status_service', VeStatusService::YES)],
                'db_start_at'  => ['required', 'date'],
                'db_end_at'    => ['nullable', 'date', 'after:db_start_at'],
                'db_note'      => ['nullable', 'string', 'max:200'],
                'processed_by' => ['required', Rule::exists(Admin::class, 'id')],
            ],
            trans_property(IotDeviceBinding::class),
        )->after(function (\Illuminate\Validation\Validator $validator) use ($iotDeviceBinding, $request) {
            if (!$validator->failed()) {
                // 如果当前数据结束时间为空，则要判断，其他数据结束时间都不为空。
                if (!$request->input('db_end_at')) {
                    $count = IotDeviceBinding::query()
                        ->where('d_id', $request->input('d_id'))
                        ->whereNull('db_end_at')
                        ->when($iotDeviceBinding, function (Builder $query) use ($iotDeviceBinding) {$query->where($iotDeviceBinding->getKeyName(), '!=', $iotDeviceBinding->db_id); })
                        ->count()
                    ;
                    if ($count > 0) {
                        $validator->errors()->add('db_end_at', '存在结束时间为空的绑定');

                        return;
                    }
                }
            }
        });

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $input = $validator->validated();

        DB::transaction(function () use (&$input, &$iotDeviceBinding) {
            if (null === $iotDeviceBinding) {
                $iotDeviceBinding = IotDeviceBinding::query()->create($input);
            } else {
                $iotDeviceBinding->update($input);
            }
        });

        return $this->response()->withData($iotDeviceBinding)->respond();
    }

    #[PermissionAction(PermissionAction::DELETE)]
    public function destroy(IotDeviceBinding $iotDeviceBinding): Response
    {
        DB::transaction(function () use ($iotDeviceBinding) {
            $iotDeviceBinding->delete();
        });

        return $this->response()->withData($iotDeviceBinding)->respond();
    }

    protected function options(?bool $with_group_count = false): void
    {
        $this->response()->withExtras(
        );
    }
}
