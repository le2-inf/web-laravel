<?php

namespace App\Http\Controllers\Admin\Vehicle;

use App\Attributes\PermissionAction;
use App\Attributes\PermissionType;
use App\Enum\Vehicle\VeStatusDispatch;
use App\Enum\Vehicle\VeStatusRental;
use App\Enum\Vehicle\VeStatusService;
use App\Enum\Vehicle\VeVeType;
use App\Http\Controllers\Controller;
use App\Models\Admin\Admin;
use App\Models\Configuration;
use App\Models\Rental\Vehicle\RentalVehicle;
use App\Models\Rental\Vehicle\RentalVehicleInspection;
use App\Models\Rental\Vehicle\RentalVehicleManualViolation;
use App\Models\Rental\Vehicle\RentalVehicleModel;
use App\Models\Rental\Vehicle\RentalVehicleRepair;
use App\Models\Rental\Vehicle\RentalVehicleSchedule;
use App\Models\Rental\Vehicle\RentalVehicleUsage;
use App\Models\Rental\Vehicle\RentalVehicleViolation;
use App\Services\PaginateService;
use App\Services\Uploader;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

#[PermissionType('车辆管理')]
class RentalVehicleController extends Controller
{
    public static function labelOptions(Controller $controller): void
    {
        $controller->response()->withExtras(
            VeVeType::labelOptions(),
            VeStatusService::labelOptions(),
            VeStatusRental::labelOptions(),
            VeStatusDispatch::labelOptions(),
        );
    }

    #[PermissionAction(PermissionAction::INDEX)]
    public function index(Request $request): Response
    {
        $this->options(true);
        $this->response()->withExtras(
            RentalVehicleModel::options(),
        );

        $query   = RentalVehicle::indexQuery();
        $columns = RentalVehicle::indexColumns();

        // 如果是管理员和经理，则可以看到所有的车辆；如果不是管理员和经理，则只能看到车管为自己的车辆。
        $user = $request->user();

        $role_vehicle_manager = $user->hasRole(Configuration::fetch('role_vehicle_manager'));

        if ($role_vehicle_manager) {
            $query->whereNull('ve.vehicle_manager')->orWhere('ve.vehicle_manager', '=', $user->id);
        }

        $paginate = new PaginateService(
            [],
            [['ve.ve_id', 'desc']],
            ['kw', 've_status_service', 've_status_repair', 've_status_rental', 've_status_dispatch'],
            []
        );

        $paginate->paginator(
            $query,
            $request,
            [
                'kw__func' => function ($value, Builder $builder) {
                    $builder->where(function (Builder $builder) use ($value) {
                        $builder->where('ve.plate_no', 'like', '%'.$value.'%')
                            ->orWhere('ve.ve_license_owner', 'like', '%'.$value.'%')
                            ->orWhere('ve.ve_license_address', 'like', '%'.$value.'%')
                        ;
                    });
                },
            ],
            $columns
        );

        return $this->response()->withData($paginate)->respond();
    }

    #[PermissionAction(PermissionAction::ADD)]
    public function store(Request $request): Response
    {
        return $this->update($request, null);
    }

    #[PermissionAction(PermissionAction::SHOW)]
    public function show(RentalVehicle $rentalVehicle): Response
    {
        $this->response()->withExtras(
            RentalVehicleInspection::kvList(ve_id: $rentalVehicle->ve_id),
            RentalVehicleUsage::kvList(ve_id: $rentalVehicle->ve_id),
            RentalVehicleRepair::kvList(ve_id: $rentalVehicle->ve_id),
            RentalVehicleViolation::kvList(ve_id: $rentalVehicle->ve_id),
            RentalVehicleManualViolation::kvList(ve_id: $rentalVehicle->ve_id),
            RentalVehicleSchedule::kvList(ve_id: $rentalVehicle->ve_id),
        );

        return $this->response()->withData($rentalVehicle)->respond();
    }

    #[PermissionAction(PermissionAction::EDIT)]
    public function update(Request $request, ?RentalVehicle $rentalVehicle): Response
    {
        $validator = Validator::make(
            $request->all(),
            [
                'plate_no'                    => ['required', 'string', 'max:64', Rule::unique(RentalVehicle::class, 'plate_no')->ignore($rentalVehicle)],
                've_type'                     => ['nullable', Rule::in(VeVeType::label_keys())],
                'vm_id'                       => ['nullable', 'integer', Rule::exists(RentalVehicleModel::class)],
                'status_service'              => ['required', Rule::in(VeStatusService::label_keys())],
                'status_rental'               => ['required', Rule::in(VeStatusRental::label_keys())],
                'status_dispatch'             => ['required', Rule::in(VeStatusDispatch::label_keys())],
                'vehicle_manager'             => ['nullable', Rule::exists(Admin::class, 'id')],
                've_license_owner'            => ['nullable', 'string', 'max:100'],
                've_license_address'          => ['nullable', 'string', 'max:255'],
                've_license_usage'            => ['nullable', 'string', 'max:50'],
                've_license_type'             => ['nullable', 'string', 'max:50'],
                've_license_company'          => ['nullable', 'string', 'max:100'],
                've_license_vin_code'         => ['nullable', 'string', 'max:50'],
                've_license_engine_no'        => ['nullable', 'string', 'max:50'],
                've_license_purchase_date'    => ['nullable', 'date'],
                've_license_valid_until_date' => ['nullable', 'date', 'after:ve_license_purchase_date'],
                've_mileage'                  => ['nullable', 'integer'],
                've_color'                    => ['nullable', 'string', 'max:30'],
            ]
            + Uploader::validator_rule_upload_object('ve_license_face_photo')
            + Uploader::validator_rule_upload_object('ve_license_back_photo'),
            [],
            trans_property(RentalVehicle::class)
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

        DB::transaction(function () use (&$input, &$rentalVehicle) {
            if (null === $rentalVehicle) {
                $rentalVehicle = RentalVehicle::query()->create($input);
            } else {
                $rentalVehicle->update($input);
            }
        });

        return $this->response()->withData($rentalVehicle)->respond();
    }

    #[PermissionAction(PermissionAction::DELETE)]
    public function destroy(RentalVehicle $rentalVehicle): Response
    {
        DB::transaction(function () use (&$rentalVehicle) {
            $rentalVehicle->delete();
        });

        return $this->response()->withData($rentalVehicle)->respond();
    }

    #[PermissionAction(PermissionAction::ADD)]
    public function create(Request $request): Response
    {
        $this->options();

        $this->response()->withExtras(
            RentalVehicleModel::options(),
            Admin::optionsWithRoles(),
        );

        $rentalVehicle = new RentalVehicle();

        return $this->response()->withData($rentalVehicle)->respond();
    }

    #[PermissionAction(PermissionAction::EDIT)]
    public function edit(RentalVehicle $rentalVehicle): Response
    {
        $this->options();

        $this->response()->withExtras(
            RentalVehicleModel::options(),
            Admin::optionsWithRoles(),
        );

        $this->response()->withExtras(
            RentalVehicleInspection::kvList(ve_id: $rentalVehicle->ve_id),
            RentalVehicleUsage::kvList(ve_id: $rentalVehicle->ve_id),
            RentalVehicleRepair::kvList(ve_id: $rentalVehicle->ve_id),
            RentalVehicleViolation::kvList(ve_id: $rentalVehicle->ve_id),
            RentalVehicleManualViolation::kvList(ve_id: $rentalVehicle->ve_id),
            RentalVehicleSchedule::kvList(ve_id: $rentalVehicle->ve_id),
        );

        return $this->response()->withData($rentalVehicle)->respond();
    }

    #[PermissionAction(PermissionAction::ADD)]
    #[PermissionAction(PermissionAction::EDIT)]
    public function upload(Request $request): Response
    {
        return Uploader::upload($request, 'vehicle', ['ve_license_face_photo', 've_license_back_photo'], $this);
    }

    protected function options(?bool $with_group_count = false): void
    {
        $this->response()->withExtras(
            VeVeType::options(),
            $with_group_count ? VeStatusService::options_with_count(RentalVehicle::class) : VeStatusService::options(),
            $with_group_count ? VeStatusRental::options_with_count(RentalVehicle::class) : VeStatusRental::options(),
            $with_group_count ? VeStatusDispatch::options_with_count(RentalVehicle::class) : VeStatusDispatch::options(),
        );
    }
}
