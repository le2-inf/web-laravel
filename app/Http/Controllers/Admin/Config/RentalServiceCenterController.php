<?php

namespace App\Http\Controllers\Admin\Config;

use App\Attributes\PermissionAction;
use App\Attributes\PermissionType;
use App\Enum\Vehicle\ScScStatus;
use App\Http\Controllers\Controller;
use App\Models\Admin\Admin;
use App\Models\Rental\Vehicle\RentalServiceCenter;
use App\Models\Rental\Vehicle\RentalVehicleAccident;
use App\Models\Rental\Vehicle\RentalVehicleMaintenance;
use App\Models\Rental\Vehicle\RentalVehicleRepair;
use App\Services\PaginateService;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

#[PermissionType('修理厂管理')]
class RentalServiceCenterController extends Controller
{
    public static function labelOptions(Controller $controller): void
    {
        $controller->response()->withExtras(
            ScScStatus::labelOptions(),
        );
    }

    #[PermissionAction(PermissionAction::INDEX)]
    public function index(Request $request): Response
    {
        $this->options(true);

        $query = RentalServiceCenter::indexQuery();

        $paginate = new PaginateService(
            [],
            [['sc.sc_id', 'desc']],
            ['kw'],
            []
        );

        $paginate->paginator($query, $request, [
            'kw__func' => function ($value, Builder $builder) {
                $builder->where(function (Builder $builder) use ($value) {
                    $builder->where('sc.sc_name', 'like', '%'.$value.'%')
                        ->orWhere('sc.sc_address', 'like', '%'.$value.'%')
                        ->orWhere('sc.contact_name', 'like', '%'.$value.'%')
                        ->orWhere('sc.contact_phone', 'like', '%'.$value.'%')
                        ->orWhere('sc.sc_note', 'like', '%'.$value.'%')
                    ;
                });
            },
        ]);

        return $this->response()->withData($paginate)->respond();
    }

    #[PermissionAction(PermissionAction::ADD)]
    public function create(Request $request): Response
    {
        $this->options();

        $rentalServiceCenter = new RentalServiceCenter([
            'sc_status' => ScScStatus::ENABLED,
        ]);

        $this->response()->withExtras(
            Admin::optionsWithRoles(),
        );

        $arr = (string) $rentalServiceCenter;

        return $this->response()->withData($rentalServiceCenter)->respond();
    }

    #[PermissionAction(PermissionAction::ADD)]
    public function store(Request $request): Response
    {
        return $this->update($request, null);
    }

    #[PermissionAction(PermissionAction::SHOW)]
    public function show(RentalServiceCenter $rentalServiceCenter): Response
    {
        $this->options();
        $this->response()->withExtras(
        );

        return $this->response()->withData($rentalServiceCenter)->respond();
    }

    #[PermissionAction(PermissionAction::EDIT)]
    public function edit(RentalServiceCenter $rentalServiceCenter): Response
    {
        $this->options();
        $this->response()->withExtras(
            Admin::optionsWithRoles(),
            RentalVehicleRepair::kvList(sc_id: $rentalServiceCenter->sc_id),
            RentalVehicleMaintenance::kvList(sc_id: $rentalServiceCenter->sc_id),
            RentalVehicleAccident::kvList(sc_id: $rentalServiceCenter->sc_id),
        );

        return $this->response()->withData($rentalServiceCenter)->respond();
    }

    #[PermissionAction(PermissionAction::EDIT)]
    public function update(Request $request, ?RentalServiceCenter $rentalServiceCenter): Response
    {
        $validator = Validator::make(
            $request->all(),
            [
                'sc_name'               => ['bail', 'required', 'string', 'max:255'],
                'sc_address'            => ['bail', 'required', 'string'],
                'contact_name'          => ['bail', 'required'],
                'contact_phone'         => ['bail', 'nullable', 'string', 'max:32'],
                'sc_status'             => ['bail', 'required', Rule::in(ScScStatus::label_keys())],
                'sc_note'               => ['bail', 'nullable', 'string', 'max:255'],
                'permitted_admin_ids'   => ['bail', 'nullable', 'array'],
                'permitted_admin_ids.*' => ['bail', 'integer'],
                //                'contact_mobile'        => ['bail', 'nullable', 'string', 'max:32'],
            ],
            [],
            trans_property(RentalServiceCenter::class)
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

        DB::transaction(function () use (&$input, &$rentalServiceCenter) {
            if (null === $rentalServiceCenter) {
                /** @var RentalServiceCenter $rentalServiceCenter */
                $rentalServiceCenter = RentalServiceCenter::query()->create($input);
            } else {
                $rentalServiceCenter->update($input);
            }
        });

        $rentalServiceCenter->refresh();

        return $this->response()->withData($rentalServiceCenter)->respond();
    }

    #[PermissionAction(PermissionAction::DELETE)]
    public function destroy(RentalServiceCenter $rentalServiceCenter): Response
    {
        $rentalServiceCenter->delete();

        return $this->response()->withData($rentalServiceCenter)->respond();
    }

    protected function options(?bool $with_group_count = false): void
    {
        $this->response()->withExtras(
            ScScStatus::options(),
        );
    }
}
