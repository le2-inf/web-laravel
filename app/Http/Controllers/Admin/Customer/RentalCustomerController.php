<?php

namespace App\Http\Controllers\Admin\Customer;

use App\Attributes\PermissionAction;
use App\Attributes\PermissionType;
use App\Enum\Customer\CuCuType;
use App\Enum\Customer\CuiCuiGender;
use App\Http\Controllers\Controller;
use App\Models\Admin\Admin;
use App\Models\Configuration;
use App\Models\Rental\Customer\RentalCustomer;
use App\Models\Rental\Customer\RentalCustomerCompany;
use App\Models\Rental\Customer\RentalCustomerIndividual;
use App\Models\Rental\Payment\RentalPayment;
use App\Models\Rental\Sale\RentalSaleOrder;
use App\Models\Rental\Sale\RentalSaleSettlement;
use App\Models\Rental\Vehicle\RentalVehicleInspection;
use App\Models\Rental\Vehicle\RentalVehicleManualViolation;
use App\Models\Rental\Vehicle\RentalVehicleRepair;
use App\Models\Rental\Vehicle\RentalVehicleUsage;
use App\Models\Rental\Vehicle\RentalVehicleViolation;
use App\Services\PaginateService;
use App\Services\Uploader;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

#[PermissionType('顾客管理')]
class RentalCustomerController extends Controller
{
    public static function labelOptions(Controller $controller): void
    {
        $controller->response()->withExtras(
            CuCuType::labelOptions(),
            CuiCuiGender::labelOptions(),
        );
    }

    #[PermissionAction(PermissionAction::INDEX)]
    public function index(Request $request): Response
    {
        $this->options(true);

        $query = RentalCustomer::indexQuery();

        // 如果是管理员或经理，则可以看到所有的用户；如果不是管理员或经理，则只能看到销售或驾管为自己的用户。
        $user = $request->user();

        $role_sales_manager = $user->hasRole(Configuration::fetch('role_sales_manager'));
        if ($role_sales_manager) {
            $query->whereNull('cu.sales_manager')->orWhere('cu.sales_manager', '=', $user->id);
        }

        $role_driver_manager = $user->hasRole(Configuration::fetch('role_driver_manager'));
        if ($role_driver_manager) {
            $query->whereNull('cu.driver_manager')->orWhere('cu.driver_manager', '=', $user->id);
        }

        $paginate = new PaginateService(
            [],
            [['cu.cu_id', 'desc']],
            [],
            []
        );

        $paginate->paginator($query, $request, []);

        return $this->response()->withData($paginate)->respond();
    }

    #[PermissionAction(PermissionAction::ADD)]
    public function store(Request $request): Response
    {
        return $this->update($request, null);
    }

    #[PermissionAction(PermissionAction::SHOW)]
    public function show(RentalCustomer $rentalCustomer): Response
    {
        $rentalCustomer->load('RentalCustomerIndividual', 'RentalCustomerCompany');

        $this->response()->withExtras(
            CuCuType::options(),
            CuiCuiGender::options(),
            CuiCuiGender::flipLabelDic(),
        );

        $this->response()->withExtras(
            RentalVehicleInspection::kvList(cu_id: $rentalCustomer->cu_id),
            RentalSaleOrder::kvList(cu_id: $rentalCustomer->cu_id),
            RentalPayment::kvList(cu_id: $rentalCustomer->cu_id),
            RentalSaleSettlement::kvList(cu_id: $rentalCustomer->cu_id),
            RentalVehicleUsage::kvList(cu_id: $rentalCustomer->cu_id),
            RentalVehicleRepair::kvList(cu_id: $rentalCustomer->cu_id),
            RentalVehicleViolation::kvList(cu_id: $rentalCustomer->cu_id),
            RentalVehicleManualViolation::kvList(cu_id: $rentalCustomer->cu_id),
        );

        return $this->response()->withData($rentalCustomer)->respond();
    }

    #[PermissionAction(PermissionAction::EDIT)]
    public function update(Request $request, ?RentalCustomer $rentalCustomer): Response
    {
        $validator = Validator::make(
            $request->all(),
            [
                'cu_type'              => ['required', 'string', Rule::in(CuCuType::label_keys())],
                'contact_name'         => ['required', 'string', 'max:255'],
                'contact_phone'        => ['required', 'regex:/^\d{11}$/', Rule::unique(RentalCustomer::class, 'contact_phone')->ignore($rentalCustomer)],
                'contact_email'        => ['nullable', 'email', Rule::unique(RentalCustomer::class, 'contact_email')->ignore($rentalCustomer)],
                'contact_wechat'       => ['nullable', 'string', 'max:255'],
                'contact_live_city'    => ['nullable', 'string', 'max:64'],
                'contact_live_address' => ['nullable', 'string', 'max:255'],
                'cu_remark'            => ['nullable', 'string'],

                'sales_manager'  => ['nullable', Rule::exists(Admin::class)],
                'driver_manager' => ['nullable', Rule::exists(Admin::class)],

                'customer_individual'                                => ['nullable', 'array'],
                'customer_individual.cui_name'                       => ['nullable', 'string', 'max:255'],
                'customer_individual.cui_gender'                     => ['nullable', Rule::in(CuiCuiGender::label_keys())],
                'customer_individual.cui_date_of_birth'              => ['nullable', 'date', 'before:today'],
                'customer_individual.cui_id_number'                  => ['nullable', 'regex:/^\d{17}[\dXx]$/'],
                'customer_individual.cui_id_address'                 => ['nullable', 'string', 'max:500'],
                'customer_individual.cui_id_expiry_date'             => ['nullable', 'date', 'after:date_of_birth'],
                'customer_individual.cui_driver_license_number'      => ['nullable', 'string', 'max:50'],
                'customer_individual.cui_driver_license_category'    => ['nullable', 'string', 'regex:/^[A-Z]\d+$/'],
                'customer_individual.cui_driver_license_expiry_date' => ['nullable', 'date'],
                'customer_individual.cui_emergency_contact_name'     => ['nullable', 'string', 'max:64'],
                'customer_individual.cui_emergency_contact_phone'    => ['nullable', 'regex:/^\d{7,15}$/'],
                'customer_individual.cui_emergency_relationship'     => ['nullable', 'string', 'max:64'],

                'customer_company' => ['nullable', 'required_if:cu_type,'.CuCuType::COMPANY, 'array'],
            ]
            + Uploader::validator_rule_upload_object('customer_individual.cui_id1_photo')
            + Uploader::validator_rule_upload_object('customer_individual.cui_id2_photo')
            + Uploader::validator_rule_upload_object('customer_individual.cui_driver_license1_photo')
            + Uploader::validator_rule_upload_object('customer_individual.cui_driver_license2_photo'),
            [],
            trans_property(RentalCustomer::class) + trans_property(RentalCustomerIndividual::class) + trans_property(RentalCustomerCompany::class),
        )->after(function (\Illuminate\Validation\Validator $validator) {
            if (!$validator->failed()) {
            }
        });

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $input = $validator->validated();

        DB::transaction(function () use (&$input, &$rentalCustomer) {
            if (null === $rentalCustomer) {
                $rentalCustomer = RentalCustomer::query()->create($input);
            } else {
                $rentalCustomer->update($input);
            }

            switch ($rentalCustomer->cu_type) {
                case CuCuType::INDIVIDUAL:
                    $rentalCustomer->RentalCustomerCompany()->delete();

                    $input_individual = $input['customer_individual'] ?? [];

                    $rentalCustomer->RentalCustomerIndividual()->updateOrCreate(
                        [
                            'cu_id' => $rentalCustomer->cu_id,
                        ],
                        $input_individual,
                    );

                    break;

                case CuCuType::COMPANY:
                    $rentalCustomer->RentalCustomerIndividual()->delete();

                    $input_company = $input['customer_company'];

                    $rentalCustomer->RentalCustomerCompany()->updateOrCreate(
                        [
                            'cu_id' => $rentalCustomer->cu_id,
                        ],
                        $input_company,
                    );

                    break;

                default:
                    break;
            }
        });

        return $this->response()->withData($rentalCustomer)->respond();
    }

    #[PermissionAction(PermissionAction::DELETE)]
    public function destroy(RentalCustomer $rentalCustomer): Response
    {
        DB::transaction(function () use ($rentalCustomer) {
            $rentalCustomer->RentalCustomerIndividual()->delete();
            $rentalCustomer->RentalCustomerCompany()->delete();
            $rentalCustomer->delete();
        });

        return $this->response()->withData($rentalCustomer)->respond();
    }

    #[PermissionAction(PermissionAction::ADD)]
    public function create(Request $request): Response
    {
        $this->options();

        $this->response()->withExtras(
            Admin::optionsWithRoles(),
        );

        $rentalCustomer = new RentalCustomer([
            'cu_type' => CuCuType::INDIVIDUAL,
        ]);

        return $this->response()->withData($rentalCustomer)->respond();
    }

    #[PermissionAction(PermissionAction::EDIT)]
    public function edit(RentalCustomer $rentalCustomer): Response
    {
        $this->options();

        $this->response()->withExtras(
            Admin::optionsWithRoles(),
        );

        $this->response()->withExtras(
            RentalVehicleInspection::kvList(cu_id: $rentalCustomer->cu_id),
            RentalSaleOrder::kvList(cu_id: $rentalCustomer->cu_id),
            RentalPayment::kvList(cu_id: $rentalCustomer->cu_id),
            RentalSaleSettlement::kvList(cu_id: $rentalCustomer->cu_id),
            RentalVehicleUsage::kvList(cu_id: $rentalCustomer->cu_id),
            RentalVehicleRepair::kvList(cu_id: $rentalCustomer->cu_id),
            RentalVehicleViolation::kvList(cu_id: $rentalCustomer->cu_id),
            RentalVehicleManualViolation::kvList(cu_id: $rentalCustomer->cu_id),
        );

        $rentalCustomer->load('RentalCustomerIndividual', 'RentalCustomerCompany');

        return $this->response()->withData($rentalCustomer)->respond();
    }

    #[PermissionAction(PermissionAction::ADD)]
    #[PermissionAction(PermissionAction::EDIT)]
    public function upload(Request $request): Response
    {
        return Uploader::upload(
            $request,
            'customer',
            ['cui_id1_photo', 'cui_id2_photo', 'cui_driver_license1_photo', 'cui_driver_license2_photo', 'cuc_business_license_photo'],
            $this
        );
    }

    protected function options(?bool $with_group_count = false): void
    {
        $this->response()->withExtras(
            CuCuType::options(),
            CuiCuiGender::options(),
            CuiCuiGender::flipLabelDic(),
        );
    }
}
