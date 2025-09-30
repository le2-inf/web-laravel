<?php

namespace App\Http\Controllers\Admin\Sale;

use App\Attributes\PermissionAction;
use App\Attributes\PermissionType;
use App\Enum\Booking\BvBType;
use App\Enum\Booking\BvIsListed;
use App\Enum\Booking\RbvProps;
use App\Enum\Vehicle\VeStatusService;
use App\Http\Controllers\Controller;
use App\Models\Rental\Sale\RentalBookingVehicle;
use App\Models\Rental\Vehicle\RentalVehicle;
use App\Services\PaginateService;
use App\Services\Uploader;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

#[PermissionType('预定车辆管理')]
class RentalBookingVehicleController extends Controller
{
    public static function labelOptions(Controller $controller): void
    {
        $controller->response()->withExtras(
        );
    }

    #[PermissionAction(PermissionAction::ADD)]
    public function create(Request $request): Response
    {
        $rentalBookingVehicles = new RentalBookingVehicle([
            'b_type'            => BvBType::WEEKLY_RENT,
            'registration_date' => date('Y-m-d'),
            'pickup_date'       => date('Y-m-d'),
        ]);

        $this->options();
        $this->response()->withExtras(
            RentalVehicle::optionsNo(),
        );

        return $this->response()->withData($rentalBookingVehicles)->respond();
    }

    #[PermissionAction(PermissionAction::EDIT)]
    public function edit(RentalBookingVehicle $rentalBookingVehicle): Response
    {
        $rentalBookingVehicle->load('RentalVehicle');

        $this->options();

        return $this->response()->withData($rentalBookingVehicle)->respond();
    }

    #[PermissionAction(PermissionAction::INDEX)]
    public function index(Request $request)
    {
        $this->options(true);
        $query = RentalBookingVehicle::indexQuery();

        $paginate = new PaginateService(
            [],
            [['bv.bv_id', 'desc']],
            ['kw'],
            []
        );

        $paginate->paginator($query, $request, [
            'kw__func' => function ($value, Builder $builder) {
                $builder->where(function (Builder $builder) use ($value) {
                    $builder->where('bv.plate_no', 'like', '%'.$value.'%')
                        ->orWhere('bv.b_note', 'like', '%'.$value.'%')
                    ;
                });
            },
        ]);

        return $this->response()->withData($paginate)->respond();
    }

    #[PermissionAction(PermissionAction::SHOW)]
    public function show(RentalBookingVehicle $rentalBookingVehicle)
    {
        $this->options();
        $rentalBookingVehicle->load(['RentalVehicle']);

        return $this->response()->withData($rentalBookingVehicle)->respond();
    }

    #[PermissionAction(PermissionAction::ADD)]
    public function store(Request $request)
    {
        return $this->update($request, null);
    }

    #[PermissionAction(PermissionAction::EDIT)]
    public function update(Request $request, ?RentalBookingVehicle $rentalBookingVehicle)
    {
        $validator = Validator::make(
            $request->all(),
            [
                'b_type'             => ['bail', Rule::excludeIf(null !== $rentalBookingVehicle), 'required', Rule::in(BvBType::label_keys())],
                'plate_no'           => ['bail', Rule::excludeIf(null !== $rentalBookingVehicle), 'required', 'string', Rule::exists(RentalVehicle::class, 'plate_no')->where('status_service', VeStatusService::YES)],
                'pickup_date'        => ['bail', 'required', 'nullable', 'date'],
                'rent_per_amount'    => ['bail', 'required', 'decimal:0,2', 'gte:0'],
                'deposit_amount'     => ['bail', 'required', 'decimal:0,2', 'gte:0'],
                'min_rental_periods' => ['bail', 'required', 'numeric', 'min:0'],
                'registration_date'  => ['bail', 'required', 'nullable', 'date'],
                'b_mileage'          => ['bail', 'nullable', 'integer', 'min:0'],
                'service_interval'   => ['bail', 'nullable', 'integer', 'min:0'],
                'b_props'            => ['bail', 'nullable', 'array'],
                'b_props.*'          => ['bail', 'string', Rule::in(array_keys(RbvProps::kv))],
                'b_note'             => ['bail', 'nullable', 'string'],
            ]
            + Uploader::validator_rule_upload_object('bv_photo')
            + Uploader::validator_rule_upload_array('bv_additional_photos'),
            [],
            trans_property(RentalBookingVehicle::class)
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

        if (null === $rentalBookingVehicle) {
            $rentalBookingVehicle = RentalBookingVehicle::query()->create($input + ['is_listed' => BvIsListed::LISTED, 'listed_at' => now()]);
        } else {
            $rentalBookingVehicle->update($input);
        }

        $rentalBookingVehicle->load(['RentalVehicle']);

        return $this->response()->withData($rentalBookingVehicle)->respond();
    }

    #[PermissionAction(PermissionAction::DELETE)]
    public function destroy(RentalBookingVehicle $rentalBookingVehicle)
    {
        $rentalBookingVehicle->delete();

        return $this->response()->withData($rentalBookingVehicle)->respond();
    }

    #[PermissionAction(PermissionAction::EDIT)]
    public function status(Request $request, RentalBookingVehicle $rentalBookingVehicle): Response
    {
        $validator = Validator::make(
            $request->all(),
            [
                'is_listed' => ['bail', 'required', Rule::in(BvIsListed::label_keys())],
            ],
            [],
            trans_property(RentalBookingVehicle::class)
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

        $rentalBookingVehicle->update($input);

        return $this->response()->withData($rentalBookingVehicle)->respond();
    }

    #[PermissionAction(PermissionAction::ADD)]
    #[PermissionAction(PermissionAction::EDIT)]
    public function upload(Request $request): Response
    {
        return Uploader::upload(
            $request,
            'rental_booking_vehicle',
            [
                'bv_photo',
                'bv_additional_photos',
            ],
            $this
        );
    }

    protected function options(?bool $with_group_count = false): void
    {
        $this->response()->withExtras(
            BvBType::options(),
            RbvProps::options(),
            BvBType::labelDic(),
        );
    }
}
