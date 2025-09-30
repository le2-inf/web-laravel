<?php

namespace App\Http\Controllers\Admin\Vehicle;

use App\Attributes\PermissionAction;
use App\Attributes\PermissionType;
use App\Enum\Vehicle\ScScStatus;
use App\Enum\Vehicle\VaClaimStatus;
use App\Enum\Vehicle\VaManagedVehicle;
use App\Enum\Vehicle\VaPickupStatus;
use App\Enum\Vehicle\VaRepairStatus;
use App\Enum\Vehicle\VaSettlementMethod;
use App\Enum\Vehicle\VaSettlementStatus;
use App\Enum\Vehicle\VeStatusService;
use App\Http\Controllers\Controller;
use App\Models\Rental\Customer\RentalCustomer;
use App\Models\Rental\Sale\RentalSaleOrder;
use App\Models\Rental\Vehicle\RentalServiceCenter;
use App\Models\Rental\Vehicle\RentalVehicle;
use App\Models\Rental\Vehicle\RentalVehicleAccident;
use App\Services\PaginateService;
use App\Services\Uploader;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

#[PermissionType('出险管理')]
class RentalVehicleAccidentController extends Controller
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
        $this->response()->withExtras(
            RentalVehicle::options(),
            RentalCustomer::options(),
        );

        $query = RentalVehicleAccident::indexQuery();

        $paginate = new PaginateService(
            [],
            [['va.va_id', 'desc']],
            ['kw', 'va_ve_id', 'va_claim_status', 'va_accident_dt'],
            []
        );

        $paginate->paginator($query, $request, [
            'kw__func' => function ($value, Builder $builder) {
                $builder->where(function (Builder $builder) use ($value) {
                    $builder->where('ve.plate_no', 'like', '%'.$value.'%')
                        ->orWhere('va.accident_location', 'like', '%'.$value.'%')
                        ->orWhere('va.description', 'like', '%'.$value.'%')
                        ->orWhere('cu.contact_name', 'like', '%'.$value.'%')
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
        $this->response()->withExtras(
            RentalVehicle::options(),
            RentalCustomer::options(),
            RentalServiceCenter::options(),
        );

        $rentalVehicleAccident = new RentalVehicleAccident([
            'accident_dt'    => now()->format('Y-m-d H:i:s'),
            'factory_in_dt'  => now()->format('Y-m-d H:i:s'),
            'factory_out_dt' => now()->format('Y-m-d H:i:s'),
            'accident_info'  => [],
        ]);

        return $this->response()->withData($rentalVehicleAccident)->respond();
    }

    #[PermissionAction(PermissionAction::ADD)]
    public function store(Request $request): Response
    {
        return $this->update($request, null);
    }

    #[PermissionAction(PermissionAction::SHOW)]
    public function show(RentalVehicleAccident $rentalVehicleAccident): Response
    {
        $this->options();

        return $this->response()->withData($rentalVehicleAccident)->respond();
    }

    #[PermissionAction(PermissionAction::EDIT)]
    public function edit(RentalVehicleAccident $rentalVehicleAccident): Response
    {
        $this->options();
        $this->response()->withExtras(
            RentalVehicle::options(),
            RentalCustomer::options(),
            RentalServiceCenter::options(),
        );

        $rentalVehicleAccident->load('RentalVehicle', 'RentalSaleOrder'); // ,'RentalSaleOrder.RentalCustomer'

        return $this->response()->withData($rentalVehicleAccident)->respond();
    }

    #[PermissionAction(PermissionAction::EDIT)]
    public function update(Request $request, ?RentalVehicleAccident $rentalVehicleAccident = null): Response
    {
        $validator = Validator::make(
            $request->all(),
            [
                've_id'                         => ['bail', 'required', 'integer'],
                'so_id'                         => ['bail', 'nullable', 'integer'],
                'accident_location'             => ['bail', 'nullable', 'string', 'max:255'],
                'accident_dt'                   => ['bail', 'required', 'date'],
                'responsible_party'             => ['bail', 'nullable', 'string', 'max:255'],
                'claim_status'                  => ['bail', 'nullable', 'string', Rule::in(VaClaimStatus::label_keys())],
                'self_amount'                   => ['bail', 'nullable', 'numeric'],
                'third_party_amount'            => ['bail', 'nullable', 'numeric'],
                'insurance_company'             => ['bail', 'nullable', 'string', 'max:100'],
                'va_description'                => ['bail', 'nullable', 'string'],
                'factory_in_dt'                 => ['bail', 'nullable', 'date'],
                'sc_id'                         => ['bail', 'required', 'integer', Rule::exists(RentalServiceCenter::class)->where('status', ScScStatus::ENABLED)],
                'repair_content'                => ['bail', 'nullable', 'string'],
                'repair_status'                 => ['bail', 'nullable', 'string', Rule::in(VaRepairStatus::label_keys())],
                'factory_out_dt'                => ['bail', 'nullable', 'date'],
                'settlement_status'             => ['bail', 'nullable', 'string', Rule::in(VaSettlementStatus::label_keys())],
                'pickup_status'                 => ['bail', 'nullable', 'string', Rule::in(VaPickupStatus::label_keys())],
                'settlement_method'             => ['bail', 'nullable', 'string', Rule::in(VaSettlementMethod::label_keys())],
                'managed_vehicle'               => ['bail', 'nullable', 'string', Rule::in(VaManagedVehicle::label_keys())],
                'va_remark'                     => ['bail', 'nullable', 'string'],
                'additional_photos'             => ['bail', 'nullable', 'array'],
                'accident_info'                 => ['bail', 'nullable', 'array'],
                'accident_info.*.description'   => ['bail', 'nullable', 'string'],
                'accident_info.*.part_name'     => ['bail', 'nullable', 'string', 'max:255'],
                'accident_info.*.part_cost'     => 'bail', ['nullable', 'decimal:0,2', 'gte:0'],
                'accident_info.*.part_quantity' => ['bail', 'nullable', 'integer', 'min:1'],
            ]
            + Uploader::validator_rule_upload_array('additional_photos')
            + Uploader::validator_rule_upload_array('accident_info.*.info_photos'),
            [],
            trans_property(RentalVehicleAccident::class)
        )
            ->after(function (\Illuminate\Validation\Validator $validator) use ($request, &$rentalVehicle) {
                if (!$validator->failed()) {
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

                    $so_id = $request->input('so_id');

                    if ($so_id) {
                        /** @var RentalSaleOrder $rentalSaleOrder */
                        $RentalSaleOrder = RentalSaleOrder::query()->find($so_id);
                        if (!$RentalSaleOrder) {
                            $validator->errors()->add('so_id', 'The rental_order does not exist.');

                            return;
                        }
                    }
                }
            })
        ;

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $input = $validator->validated();

        DB::transaction(function () use (&$input, &$rentalVehicle, &$rentalVehicleAccident) {
            if (null === $rentalVehicleAccident) {
                $rentalVehicleAccident = RentalVehicleAccident::query()->create($input);
            } else {
                $rentalVehicleAccident->update($input);
            }
        });

        return $this->response()->withData($rentalVehicleAccident)->respond();
    }

    #[PermissionAction(PermissionAction::DELETE)]
    public function destroy(RentalVehicleAccident $rentalVehicleAccident): Response
    {
        $validator = Validator::make(
            [],
            []
        )
            ->after(function (\Illuminate\Validation\Validator $validator) {
                if (!$validator->failed()) {
                }
            })
        ;

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $rentalVehicleAccident->delete();

        return $this->response()->withData($rentalVehicleAccident)->respond();
    }

    #[PermissionAction(PermissionAction::ADD)]
    #[PermissionAction(PermissionAction::EDIT)]
    public function upload(Request $request): Response
    {
        return Uploader::upload($request, 'vehicle_accident', ['additional_photos', 'info_photos'], $this);
    }

    #[PermissionAction(PermissionAction::ADD)]
    public function rentalSaleOrdersOption(Request $request): Response
    {
        $validator = Validator::make(
            $request->all(),
            [
                've_id' => ['bail', 'required', 'integer'],
            ]
        );

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $input = $validator->validated();

        $this->response()->withExtras(
            RentalSaleOrder::options(
                where: function (Builder $builder) use ($input) {
                    $builder->where('so.ve_id', '=', $input['ve_id']);
                }
            )
        );

        return $this->response()->respond();
    }

    protected function options(?bool $with_group_count = false): void
    {
        $this->response()->withExtras(
            $with_group_count ? VaClaimStatus::options_with_count(RentalVehicleAccident::class) : VaClaimStatus::options(),
            $with_group_count ? VaRepairStatus::options_with_count(RentalVehicleAccident::class) : VaRepairStatus::options(),
            VaSettlementStatus::options(),
            VaPickupStatus::options(),
            VaSettlementMethod::options(),
            VaManagedVehicle::options(),
        );
    }
}
