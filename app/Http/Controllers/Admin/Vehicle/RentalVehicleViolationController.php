<?php

namespace App\Http\Controllers\Admin\Vehicle;

use App\Attributes\PermissionAction;
use App\Attributes\PermissionType;
use App\Enum\Vehicle\VvPaymentStatus;
use App\Enum\Vehicle\VvProcessStatus;
use App\Http\Controllers\Controller;
use App\Models\Rental\Vehicle\RentalVehicle;
use App\Models\Rental\Vehicle\RentalVehicleViolation;
use App\Services\PaginateService;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

#[PermissionType('违章管理')]
class RentalVehicleViolationController extends Controller
{
    public static function labelOptions(Controller $controller): void
    {
        $controller->response()->withExtras(
            VvPaymentStatus::labelOptions(),
            VvProcessStatus::labelOptions(),
        );
    }

    #[PermissionAction(PermissionAction::INDEX)]
    public function index(Request $request): Response
    {
        $this->options(true);
        $this->response()->withExtras(
            RentalVehicle::options(),
        );

        $query  = RentalVehicleViolation::indexQuery();
        $column = RentalVehicleViolation::indexColumns();

        $paginate = new PaginateService(
            [],
            [['vv.violation_datetime', 'desc']],
            ['kw', 'vv_ve_id', 'vv_violation_datetime', 'vv_process_status', 'vv_payment_status'],
            []
        );

        $paginate->paginator(
            $query,
            $request,
            [
                'kw__func' => function ($value, Builder $builder) {
                    $builder->where(function (Builder $builder) use ($value) {
                        $builder->where('violation_content', 'like', '%'.$value.'%')
                            ->orWhere('vv_remark', 'like', "%{$value}%")
                        ;
                    });
                },
            ],
            $column,
        );

        return $this->response()->withData($paginate)->respond();
    }

    #[PermissionAction(PermissionAction::ADD)]
    public function create(): Response
    {
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
    public function show(RentalVehicleViolation $rentalVehicleViolation): Response
    {
        return $this->response()->withData($rentalVehicleViolation)->respond();
    }

    #[PermissionAction(PermissionAction::EDIT)]
    public function edit(RentalVehicleViolation $rentalVehicleViolation): Response
    {
        $rentalVehicleViolation->load('RentalVehicle');

        return $this->response()->withData($rentalVehicleViolation)->respond();
    }

    #[PermissionAction(PermissionAction::EDIT)]
    public function update(Request $request, ?RentalVehicleViolation $rentalVehicleViolation): Response
    {
        $validator = Validator::make(
            $request->all(),
            [
                'vv_remark' => ['required', 'string'],
            ],
            [],
            trans_property(RentalVehicleViolation::class)
        )
            ->after(function (\Illuminate\Validation\Validator $validator) use (&$rentalVehicle) {})
        ;

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $input = $validator->validated();

        DB::transaction(function () use (&$input, &$rentalVehicleViolation) {
            if (null === $rentalVehicleViolation) {
                $rentalVehicleViolation = $rentalVehicleViolation->create($input);
            } else {
                $rentalVehicleViolation->update($input);
            }
        });

        return $this->response()->withData($rentalVehicleViolation)->respond();
    }

    #[PermissionAction(PermissionAction::DELETE)]
    public function destroy(RentalVehicleViolation $rentalVehicleViolation): Response
    {
        $rentalVehicleViolation->delete();

        return $this->response()->withData($rentalVehicleViolation)->respond();
    }

    protected function options(?bool $with_group_count = false): void
    {
        $this->response()->withExtras(
            $with_group_count ? VvPaymentStatus::options_with_count(RentalVehicleViolation::class) : VvPaymentStatus::options(),
            $with_group_count ? VvProcessStatus::options_with_count(RentalVehicleViolation::class) : VvProcessStatus::options(),
        );
    }
}
