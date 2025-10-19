<?php

namespace App\Http\Controllers\Admin\Vehicle;

use App\Attributes\PermissionAction;
use App\Attributes\PermissionType;
use App\Enum\Vehicle\VmVmStatus;
use App\Http\Controllers\Controller;
use App\Models\Rental\Vehicle\RentalVehicleModel;
use App\Services\PaginateService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

#[PermissionType('车型管理')]
class RentalVehicleModelController extends Controller
{
    public static function labelOptions(Controller $controller): void
    {
        $controller->response()->withExtras(
            VmVmStatus::labelOptions()
        );
    }

    #[PermissionAction(PermissionAction::ADD)]
    public function create()
    {
        $this->options();
        $this->response()->withExtras();

        $rentalVehicleModel = new RentalVehicleModel([
            'vm_status' => VmVmStatus::ENABLED,
        ]);

        return $this->response()->withData($rentalVehicleModel)->respond();
    }

    #[PermissionAction(PermissionAction::INDEX)]
    public function index(Request $request): Response
    {
        $this->options(true);
        $this->response()->withExtras(
        );

        $query  = RentalVehicleModel::indexQuery();
        $column = RentalVehicleModel::indexColumns();

        $paginate = new PaginateService(
            [],
            [['vm.vm_id', 'asc']],
            [],
            []
        );

        $paginate->paginator($query, $request, [], $column);

        return $this->response()->withData($paginate)->respond();
    }

    #[PermissionAction(PermissionAction::ADD)]
    public function store(Request $request): Response
    {
        return $this->update($request, null);
    }

    #[PermissionAction(PermissionAction::SHOW)]
    public function show(RentalVehicleModel $rentalVehicleModel): Response
    {
        return $this->response()->withData($rentalVehicleModel)->respond();
    }

    #[PermissionAction(PermissionAction::EDIT)]
    public function edit(RentalVehicleModel $rentalVehicleModel): Response
    {
        $this->options();
        $this->response()->withExtras(
        );

        return $this->response()->withData($rentalVehicleModel)->respond();
    }

    #[PermissionAction(PermissionAction::EDIT)]
    public function update(Request $request, ?RentalVehicleModel $rentalVehicleModel): Response
    {
        $validator = Validator::make(
            $request->all(),
            [
                'vm_id'      => ['nullable', Rule::exists(RentalVehicleModel::class, 'vm_id')],
                'brand_name' => ['required', 'string', 'max:50'],
                'model_name' => ['required', 'string', 'max:50',
                    Rule::unique(RentalVehicleModel::class)->where('brand_name', $request->input('brand_name'))->ignore($rentalVehicleModel),
                ],
                'vm_status' => ['required', Rule::in(VmVmStatus::label_keys())],
            ],
            [],
            trans_property(RentalVehicleModel::class)
        )
            ->after(function (\Illuminate\Validation\Validator $validator) use (&$rentalVehicle) {
                if (!$validator->failed()) {
                }
            })
        ;

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $input = $validator->validated();

        DB::transaction(function () use (&$input, &$rentalVehicleModel) {
            if (null === $rentalVehicleModel) {
                $rentalVehicleModel = RentalVehicleModel::query()->create($input);
            } else {
                $rentalVehicleModel->update($input);
            }
        });

        return $this->response()->withData($rentalVehicleModel)->respond();
    }

    #[PermissionAction(PermissionAction::DELETE)]
    public function destroy(RentalVehicleModel $rentalVehicleModel): Response
    {
        $rentalVehicleModel->delete();

        return $this->response()->withData($rentalVehicleModel)->respond();
    }

    protected function options(?bool $with_group_count = false): void
    {
        $this->response()->withExtras(
            VmVmStatus::options()
        );
    }
}
