<?php

namespace App\Http\Controllers\Admin\Sale;

use App\Attributes\PermissionAction;
use App\Attributes\PermissionType;
use App\Enum\Vehicle\VeStatusDispatch;
use App\Enum\Vehicle\VeStatusRental;
use App\Enum\Vehicle\VeStatusService;
use App\Enum\YesNo;
use App\Http\Controllers\Controller;
use App\Models\Admin\Admin;
use App\Models\Configuration;
use App\Models\Rental\Vehicle\RentalVehicle;
use App\Models\Rental\Vehicle\RentalVehiclePreparation;
use App\Services\PaginateService;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

#[PermissionType('整备管理')]
class RentalVehiclePreparationController extends Controller
{
    public static function labelOptions(Controller $controller): void
    {
        $controller->response()->withExtras(
        );
    }

    #[PermissionAction(PermissionAction::INDEX)]
    public function index(Request $request): Response
    {
        $this->response()->withExtras(
        );

        $query = RentalVehiclePreparation::indexQuery();

        $paginate = new PaginateService(
            [],
            [['vp.vp_id', 'desc']],
            [],
            []
        );

        $paginate->paginator($query, $request, []);

        return $this->response()->withData($paginate)->respond();
    }

    #[PermissionAction(PermissionAction::ADD)]
    public function create(Request $request)
    {
        $this->response()->withExtras(
            YesNo::options(),
            RentalVehicle::options(
                where: function (Builder $builder) {
                    $builder->whereIn('status_rental', [VeStatusRental::PENDING]);
                }
            )
        );

        /** @var Admin $user */
        $user = $request->user();

        $role_prep_vehicle  = $user->hasRole(Configuration::fetch('role_prep_vehicle')) || $user->hasRole(config('setting.super_role.name'));
        $role_prep_document = $user->hasRole(Configuration::fetch('role_prep_document')) || $user->hasRole(config('setting.super_role.name'));

        return $this->response()->withData([
            'role_prep_vehicle'  => $role_prep_vehicle,
            'role_prep_document' => $role_prep_document,
        ])->respond();
    }

    #[PermissionAction(PermissionAction::ADD)]
    public function store(Request $request)
    {
        $user = $request->user();

        $role_prep_vehicle  = $user->hasRole(Configuration::fetch('role_prep_vehicle')) || $user->hasRole(config('setting.super_role.name'));
        $role_prep_document = $user->hasRole(Configuration::fetch('role_prep_document')) || $user->hasRole(config('setting.super_role.name'));

        $validator = Validator::make(
            $request->all(),
            [
                've_id' => ['bail', 'required', 'integer'],
            ]
           + ($role_prep_vehicle ? [
               'vehicle_check_is' => ['required', Rule::in(YesNo::YES)],
           ] : [])
            + ($role_prep_document ? [
                'annual_check_is'   => ['required', Rule::in(YesNo::YES)],
                'insured_check_is'  => ['required', Rule::in(YesNo::YES)],
                'document_check_is' => ['required', Rule::in(YesNo::YES)],
            ] : []),
            [],
            trans_property(RentalVehiclePreparation::class)
        )
            ->after(function (\Illuminate\Validation\Validator $validator) use ($request, &$rentalVehicle, &$rentalCustomer) {
                if (!$validator->failed()) {
                    // ve_id
                    $ve_id         = $request->input('ve_id');
                    $rentalVehicle = RentalVehicle::query()->find($ve_id);
                    if (!$rentalVehicle) {
                        $validator->errors()->add('ve_id', 'The vehicle does not exist.');

                        return;
                    }

                    $pass = $rentalVehicle->check_status(VeStatusService::YES, [VeStatusRental::PENDING], [VeStatusDispatch::NOT_DISPATCHED], $validator);
                    if (!$pass) {
                        return;
                    }
                }
            })
        ;

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $input = $validator->validated();

        if ($input['annual_check_is']) {
            $input['annual_check_dt'] = now();
        }
        if ($input['insured_check_is']) {
            $input['insured_check_dt'] = now();
        }
        if ($input['document_check_is']) {
            $input['document_check_dt'] = now();
        }
        if ($input['vehicle_check_is']) {
            $input['vehicle_check_dt'] = now();
        }

        DB::transaction(function () use (&$input, &$rentalVehiclePreparation) {
            /** @var RentalVehiclePreparation $rentalVehiclePreparation */
            $rentalVehiclePreparation = RentalVehiclePreparation::query()
                ->where('ve_id', '=', $input['ve_id'])
                ->where(
                    function (Builder $query) {
                        $query->where('annual_check_is', '=', YesNo::NO)
                            ->orWhere('insured_check_is', '=', YesNo::NO)
                            ->orWhere('vehicle_check_is', '=', YesNo::NO)
                            ->orWhere('document_check_is', '=', YesNo::NO)
                        ;
                    }
                )->first()
            ;
            if (null === $rentalVehiclePreparation) {
                $rentalVehiclePreparation = RentalVehiclePreparation::query()->create($input);
            } else {
                $rentalVehiclePreparation->update($input);
            }

            if (YesNo::YES == $rentalVehiclePreparation->annual_check_is
                && YesNo::YES == $rentalVehiclePreparation->insured_check_is
                && YesNo::YES == $rentalVehiclePreparation->vehicle_check_is
                && YesNo::YES == $rentalVehiclePreparation->document_check_is) {
                $rentalVehiclePreparation->RentalVehicle->updateStatus(status_rental: VeStatusRental::LISTED);
            }
        });

        return $this->response()->withData($rentalVehiclePreparation)->respond();
    }

    public function show(RentalVehiclePreparation $rentalVehiclePreparation) {}

    public function edit(RentalVehiclePreparation $rentalVehiclePreparation) {}

    public function update(Request $request, RentalVehiclePreparation $rentalVehiclePreparation) {}

    public function destroy(RentalVehiclePreparation $rentalVehiclePreparation) {}

    protected function options(?bool $with_group_count = false): void
    {
        $this->response()->withExtras(
        );
    }
}
