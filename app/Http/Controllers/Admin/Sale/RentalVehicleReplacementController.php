<?php

namespace App\Http\Controllers\Admin\Sale;

use App\Attributes\PermissionAction;
use App\Attributes\PermissionType;
use App\Enum\Rental\SoOrderStatus;
use App\Enum\Rental\VrReplacementStatus;
use App\Enum\Rental\VrReplacementType;
use App\Enum\Vehicle\VeStatusDispatch;
use App\Enum\Vehicle\VeStatusRental;
use App\Enum\Vehicle\VeStatusService;
use App\Http\Controllers\Controller;
use App\Models\Rental\Sale\RentalSaleOrder;
use App\Models\Rental\Sale\RentalVehicleReplacement;
use App\Models\Rental\Vehicle\RentalVehicle;
use App\Services\PaginateService;
use App\Services\Uploader;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

#[PermissionType('换车管理')]
class RentalVehicleReplacementController extends Controller
{
    public static function labelOptions(Controller $controller): void
    {
        $controller->response()->withExtras(
            VrReplacementType::labelOptions(),
            VrReplacementStatus::labelOptions(),
        );
    }

    #[PermissionAction(PermissionAction::INDEX)]
    public function index(Request $request): Response
    {
        $this->options(true);
        $this->response()->withExtras(
        );

        $query = RentalVehicleReplacement::indexQuery();

        $paginate = new PaginateService(
            [],
            [['vr.vr_id', 'desc']],
            [],
            []
        );

        $paginate->paginator($query, $request, []);

        return $this->response()->withData($paginate)->respond();
    }

    #[PermissionAction(PermissionAction::ADD)]
    public function create(Request $request): Response
    {
        /** @var RentalSaleOrder $rentalSaleOrder */
        $validator = Validator::make(
            $request->all(),
            [
                'so_id' => ['nullable', 'integer'],
            ],
            [],
            []
        )
            ->after(function (\Illuminate\Validation\Validator $validator) use ($request, &$rentalSaleOrder, &$rentalVehicle0) {
                if (!$validator->failed()) {
                    if ($so_id = $request->get('so_id')) {
                        $rentalSaleOrder = RentalSaleOrder::query()->findOrFail($so_id);

                        $rentalSaleOrder->load('RentalVehicle');

                        $rentalVehicle0 = $rentalSaleOrder->RentalVehicle;

                        $pass = $rentalVehicle0->check_status(VeStatusService::YES, [VeStatusRental::RESERVED], [VeStatusDispatch::NOT_DISPATCHED], $validator);
                        if (!$pass) {
                            return;
                        }
                    }
                }
            })
        ;

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $this->options();
        $this->response()->withExtras(
            RentalVehicle::options(
                where: function (Builder $builder) {
                    $builder->whereIn('status_rental', [VeStatusRental::LISTED])
                        ->whereIn('status_dispatch', [VeStatusDispatch::NOT_DISPATCHED])
                    ;
                }
            ),
            RentalSaleOrder::options(
                where: function (Builder $builder) {
                    $builder->whereIn('so.order_status', [SoOrderStatus::PENDING]);
                }
            ),
        );

        $rentalVehicleReplacement = new RentalVehicleReplacement([
            'so_id' => $rentalSaleOrder?->so_id,
            //            'current_ve_id' => $rentalSaleOrder?->ve_id,
            //            'photos'        => [],
        ]);

        $rentalVehicleReplacement->RentalSaleOrder = $rentalSaleOrder;

        return $this->response()->withData($rentalVehicleReplacement)->respond();
    }

    #[PermissionAction(PermissionAction::ADD)]
    public function store(Request $request): Response
    {
        /** @var RentalVehicle $rentalVehicle0 */
        $rentalVehicle0 = null;

        /** @var RentalVehicle $rentalVehicle */
        $rentalVehicle = null;

        $validator = Validator::make(
            $request->all(),
            [
                'so_id'                  => ['bail', 'required', 'integer'],
                'replacement_type'       => ['bail', 'required', Rule::in(VrReplacementType::label_keys())],
                'new_ve_id'              => ['bail', 'required'],
                'replacement_date'       => ['bail', 'nullable', 'exclude_if:replacement_type,'.VrReplacementType::TEMPORARY, 'required_if:replacement_type,'.VrReplacementType::PERMANENT, 'date'],
                'replacement_start_date' => ['bail', 'nullable', 'exclude_if:replacement_type,'.VrReplacementType::PERMANENT, 'required_if:replacement_type,'.VrReplacementType::TEMPORARY, 'date'],
                'replacement_end_date'   => ['bail', 'nullable', 'exclude_if:replacement_type,'.VrReplacementType::PERMANENT, 'required_if:replacement_type,'.VrReplacementType::TEMPORARY, 'date', 'afterOrEqual:replacement_start_date'],
                'replacement_status'     => ['bail', 'nullable', 'exclude_if:replacement_type,'.VrReplacementType::PERMANENT, 'required_if:replacement_type,'.VrReplacementType::TEMPORARY, Rule::in(VrReplacementStatus::label_keys())],
                'vr_remark'              => ['bail', 'nullable', 'string'],
            ]
            + Uploader::validator_rule_upload_array('additional_photos'),
            [],
            trans_property(RentalVehicleReplacement::class)
        )
            ->after(function (\Illuminate\Validation\Validator $validator) use ($request, &$rentalSaleOrder, &$rentalVehicle0, &$rentalVehicle) {
                if (!$validator->failed()) {
                    $rentalSaleOrder = RentalSaleOrder::query()->findOrFail($request->input('so_id'));

                    $rentalVehicle0 = $rentalSaleOrder->RentalVehicle;

                    $pass = $rentalVehicle0->check_status(VeStatusService::YES, [VeStatusRental::RESERVED], [VeStatusDispatch::NOT_DISPATCHED], $validator);
                    if (!$pass) {
                        return;
                    }

                    /** @var RentalVehicle $rentalVehicle */
                    $rentalVehicle = RentalVehicle::query()->find($request->input('new_ve_id'));
                    if (!$rentalVehicle) {
                        $validator->errors()->add('ve_id', 'The vehicle does not exist.');

                        return;
                    }

                    $pass = $rentalVehicle->check_status(VeStatusService::YES, [VeStatusRental::LISTED], [VeStatusDispatch::NOT_DISPATCHED], $validator);
                    if (!$pass) {
                        return;
                    }

                    if ($rentalVehicle->ve_id === $rentalSaleOrder->ve_id) {
                        $validator->errors()->add('new_ve_id', '请选择另外一辆车。');

                        return;
                    }

                    switch ($request->input('replacement_type')) {
                        case VrReplacementType::PERMANENT:
                            $validator->setValue('replacement_start_date', null);
                            $validator->setValue('replacement_end_date', null);
                            $validator->setValue('replacement_status', null);

                            break;

                        case VrReplacementType::TEMPORARY:
                            $validator->setValue('replacement_date', null);

                            break;
                    }
                }
            })
        ;

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $input = $validator->validated();

        DB::transaction(function () use ($rentalVehicle0, &$input, &$rentalVehicleReplacement, &$rentalSaleOrder, $rentalVehicle) {
            $rentalVehicleReplacement = RentalVehicleReplacement::query()
                ->create($input + ['current_ve_id' => $rentalSaleOrder->ve_id])
            ;

            $rentalVehicle0->updateStatus(status_rental: VeStatusRental::LISTED);

            $rentalSaleOrder->ve_id = $rentalVehicle->ve_id;
            $rentalSaleOrder->save();

            $rentalVehicle->updateStatus(status_rental: VeStatusRental::RESERVED);
        });

        return $this->response()->withData($rentalVehicleReplacement)->respond();
    }

    public function show(RentalVehicleReplacement $rentalVehicleReplacement) {}

    #[PermissionAction(PermissionAction::EDIT)]
    public function edit(RentalVehicleReplacement $rentalVehicleReplacement): Response
    {
        $this->options();
        $this->response()->withExtras(
            RentalVehicle::options(
                where: function (Builder $builder) {
                    $builder->whereIn('status_rental', [VeStatusRental::PENDING])
                        ->whereIn('status_dispatch', [VeStatusDispatch::NOT_DISPATCHED])
                    ;
                }
            ),
        );

        $rentalVehicleReplacement->load('CurrentVehicle', 'NewVehicle');

        return $this->response()->withData($rentalVehicleReplacement)->respond();
    }

    #[PermissionAction(PermissionAction::EDIT)]
    public function update(Request $request, RentalVehicleReplacement $rentalVehicleReplacement): Response
    {
        $validator = Validator::make(
            $request->all(),
            [
                'vr_id'                  => ['bail', 'required', Rule::exists(RentalVehicleReplacement::class)],
                'replacement_date'       => ['bail', 'nullable', 'exclude_if:replacement_type,'.VrReplacementType::TEMPORARY, 'required_if:replacement_type,'.VrReplacementType::PERMANENT, 'date'],
                'replacement_start_date' => ['bail', 'nullable', 'exclude_if:replacement_type,'.VrReplacementType::PERMANENT, 'required_if:replacement_type,'.VrReplacementType::TEMPORARY, 'date'],
                'replacement_end_date'   => ['bail', 'nullable', 'exclude_if:replacement_type,'.VrReplacementType::PERMANENT, 'required_if:replacement_type,'.VrReplacementType::TEMPORARY, 'date', 'after:replacement_start_date'],
                'replacement_status'     => ['bail', 'nullable', 'exclude_if:replacement_type,'.VrReplacementType::PERMANENT, 'required_if:replacement_type,'.VrReplacementType::TEMPORARY, Rule::in(VrReplacementStatus::label_keys())],
                'vr_remark'              => ['bail', 'nullable', 'string'],
            ]
            + Uploader::validator_rule_upload_array('additional_photos'),
            [],
            trans_property(RentalVehicleReplacement::class)
        )

            ->after(function (\Illuminate\Validation\Validator $validator) use ($request) {
                if (!$validator->failed()) {
                    switch ($request->input('replacement_type')) {
                        case VrReplacementType::PERMANENT:
                            $validator->setValue('replacement_start_date', null);
                            $validator->setValue('replacement_end_date', null);
                            $validator->setValue('replacement_status', null);

                            break;

                        case VrReplacementType::TEMPORARY:
                            $validator->setValue('replacement_date', null);

                            break;
                    }
                }
            })
        ;

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $input = $validator->validated();

        DB::transaction(function () use (&$input, &$rentalVehicleReplacement) {
            $rentalVehicleReplacement->update($input);
        });

        return $this->response()->withData($rentalVehicleReplacement)->respond();
    }

    public function destroy(RentalVehicleReplacement $rentalVehicleReplacement) {}

    #[PermissionAction(PermissionAction::ADD)]
    #[PermissionAction(PermissionAction::EDIT)]
    public function upload(Request $request): Response
    {
        return Uploader::upload($request, 'vehicle_replacement', ['additional_photos'], $this);
    }

    protected function options(?bool $with_group_count = false): void
    {
        $this->response()->withExtras(
            VrReplacementType::options(),
            VrReplacementStatus::options(),
        );
    }
}
