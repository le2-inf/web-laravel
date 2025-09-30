<?php

namespace App\Http\Controllers\Admin\Vehicle;

use App\Attributes\PermissionAction;
use App\Attributes\PermissionType;
use App\Enum\Vehicle\VeStatusService;
use App\Enum\Vehicle\VmvStatus;
use App\Http\Controllers\Controller;
use App\Models\Rental\Vehicle\RentalVehicle;
use App\Models\Rental\Vehicle\RentalVehicleManualViolation;
use App\Services\PaginateService;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

#[PermissionType('手动违章管理')]
class RentalVehicleManualViolationController extends Controller
{
    public static function labelOptions(Controller $controller): void
    {
        $controller->response()->withExtras(
            VmvStatus::labelOptions(),
        );
    }

    #[PermissionAction(PermissionAction::INDEX)]
    public function index(Request $request): Response
    {
        $this->options(true);
        $this->response()->withExtras(
            RentalVehicle::options(),
        );

        $query = RentalVehicleManualViolation::indexQuery();

        $paginate = new PaginateService(
            [],
            [['vmv.violation_datetime', 'desc']],
            ['kw', 'vmv_violation_datetime'],
            []
        );

        $paginate->paginator($query, $request, [
            'kw__func' => function ($value, Builder $builder) {
                $builder->where(function (Builder $builder) use ($value) {
                    $builder->where('vmv.violation_content', 'like', '%'.$value.'%')
                        ->orWhere('vmv.vmv_remark', 'like', "%{$value}%")
                    ;
                });
            },
        ]);

        return $this->response()->withData($paginate)->respond();
    }

    #[PermissionAction(PermissionAction::ADD)]
    public function create(): Response
    {
        $this->options();
        $this->response()->withExtras(
            RentalVehicle::options(),
        );

        return $this->response()->respond();
    }

    #[PermissionAction(PermissionAction::ADD)]
    public function store(Request $request): Response
    {
        return $this->update($request, null);
    }

    #[PermissionAction(PermissionAction::SHOW)]
    public function show(RentalVehicleManualViolation $rentalVehicleManualViolation): Response
    {
        return $this->response()->withData($rentalVehicleManualViolation)->respond();
    }

    #[PermissionAction(PermissionAction::EDIT)]
    public function edit(RentalVehicleManualViolation $rentalVehicleManualViolation): Response
    {
        $this->options();

        $rentalVehicleManualViolation->load('RentalVehicle');

        return $this->response()->withData($rentalVehicleManualViolation)->respond();
    }

    #[PermissionAction(PermissionAction::EDIT)]
    public function update(Request $request, ?RentalVehicleManualViolation $rentalVehicleManualViolation): Response
    {
        // 创建验证器实例
        $validator = Validator::make(
            $request->all(),
            [
                've_id'              => ['required', 'integer', Rule::exists(RentalVehicle::class, 've_id')],
                'violation_datetime' => ['required', 'date'],
                'violation_content'  => ['nullable', 'string', 'max:200'],
                'location'           => ['nullable', 'string', 'max:255'],
                'fine_amount'        => ['nullable', 'numeric'],
                'penalty_points'     => ['nullable', 'integer'],
                'status'             => ['required', 'integer', Rule::in(VmvStatus::label_keys())],
                'vmv_remark'         => ['nullable', 'string'],
            ],
            [],
            trans_property(RentalVehicleManualViolation::class)
        )->after(function (\Illuminate\Validation\Validator $validator) use ($request, &$rentalVehicle, &$rentalCustomer) {
            if (!$validator->failed()) {
                if (null === $request->input('vi_id')) {
                    // ve_id
                    $ve_id = $request->input('ve_id');

                    /** @var RentalVehicle $rentalVehicle */
                    $rentalVehicle = RentalVehicle::query()->find($ve_id);
                    if (!$rentalVehicle) {
                        $validator->errors()->add('ve_id', 'The vehicle does not exist.');

                        return;
                    }

                    $pass = $rentalVehicle->check_status(VeStatusService::YES, [], [], $validator);
                    if (!$pass) {
                        return;
                    }
                }
            }
        });

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $input = $validator->validated();

        DB::transaction(function () use (&$input, &$rentalVehicleManualViolation) {
            if (null === $rentalVehicleManualViolation) {
                $rentalVehicleManualViolation = RentalVehicleManualViolation::query()->create($input);
            } else {
                $rentalVehicleManualViolation->update($input);
            }
        });

        return $this->response()->withData($rentalVehicleManualViolation)->respond();
    }

    #[PermissionAction(PermissionAction::DELETE)]
    public function destroy(RentalVehicleManualViolation $rentalVehicleManualViolation): Response
    {
        $rentalVehicleManualViolation->delete();

        return $this->response()->withData($rentalVehicleManualViolation)->respond();
    }

    protected function options(?bool $with_group_count = false): void
    {
        $this->response()->withExtras(
            $with_group_count ? VmvStatus::options_with_count(RentalVehicleManualViolation::class) : VmvStatus::options(),
        );
    }
}
