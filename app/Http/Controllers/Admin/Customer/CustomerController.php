<?php

namespace App\Http\Controllers\Admin\Customer;

use App\Attributes\PermissionAction;
use App\Attributes\PermissionType;
use App\Enum\Customer\CuCuType;
use App\Enum\Customer\CuiCuiGender;
use App\Http\Controllers\Controller;
use App\Models\_\Configuration;
use App\Models\Admin\Admin;
use App\Models\Customer\Customer;
use App\Models\Customer\CustomerCompany;
use App\Models\Customer\CustomerIndividual;
use App\Models\Payment\Payment;
use App\Models\Sale\SaleOrder;
use App\Models\Sale\SaleSettlement;
use App\Models\Vehicle\VehicleInspection;
use App\Models\Vehicle\VehicleManualViolation;
use App\Models\Vehicle\VehicleRepair;
use App\Models\Vehicle\VehicleUsage;
use App\Models\Vehicle\VehicleViolation;
use App\Services\PaginateService;
use App\Services\Uploader;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

#[PermissionType('顾客管理')]
class CustomerController extends Controller
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

        $query   = Customer::indexQuery();
        $columns = Customer::indexColumns();

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

        $paginate->paginator($query, $request, [], $columns);

        return $this->response()->withData($paginate)->respond();
    }

    #[PermissionAction(PermissionAction::ADD)]
    public function store(Request $request): Response
    {
        return $this->update($request, null);
    }

    #[PermissionAction(PermissionAction::SHOW)]
    public function show(Customer $customer): Response
    {
        $customer->load('CustomerIndividual', 'CustomerCompany');

        $this->response()->withExtras(
            CuCuType::options(),
            CuiCuiGender::options(),
            CuiCuiGender::flipLabelDic(),
        );

        $this->response()->withExtras(
            VehicleInspection::kvList(cu_id: $customer->cu_id),
            SaleOrder::kvList(cu_id: $customer->cu_id),
            Payment::kvList(cu_id: $customer->cu_id),
            SaleSettlement::kvList(cu_id: $customer->cu_id),
            VehicleUsage::kvList(cu_id: $customer->cu_id),
            VehicleRepair::kvList(cu_id: $customer->cu_id),
            VehicleViolation::kvList(cu_id: $customer->cu_id),
            VehicleManualViolation::kvList(cu_id: $customer->cu_id),
        );

        return $this->response()->withData($customer)->respond();
    }

    #[PermissionAction(PermissionAction::EDIT)]
    public function update(Request $request, ?Customer $customer): Response
    {
        $validator = Validator::make(
            $request->all(),
            [
                'cu_type'              => ['required', 'string', Rule::in(CuCuType::label_keys())],
                'contact_name'         => ['required', 'string', 'max:255'],
                'contact_phone'        => ['required', 'regex:/^\d{11}$/', Rule::unique(Customer::class, 'contact_phone')->ignore($customer)],
                'contact_email'        => ['nullable', 'email', Rule::unique(Customer::class, 'contact_email')->ignore($customer)],
                'contact_wechat'       => ['nullable', 'string', 'max:255'],
                'contact_live_city'    => ['nullable', 'string', 'max:64'],
                'contact_live_address' => ['nullable', 'string', 'max:255'],
                'cu_cert_no'           => ['nullable', 'string', 'max:50'],
                'cu_cert_valid_to'     => ['nullable', 'date'],
                'cu_remark'            => ['nullable', 'string', 'max:255'],

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
            + Uploader::validator_rule_upload_object('customer_individual.cui_driver_license2_photo')
            + Uploader::validator_rule_upload_object('cu_cert_photo')
            + Uploader::validator_rule_upload_array('cu_additional_photos'),
            [],
            trans_property(Customer::class) + trans_property(CustomerIndividual::class) + trans_property(CustomerCompany::class),
        )->after(function (\Illuminate\Validation\Validator $validator) {
            if (!$validator->failed()) {
            }
        });

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $input = $validator->validated();

        DB::transaction(function () use (&$input, &$customer) {
            if (null === $customer) {
                $customer = Customer::query()->create($input);
            } else {
                $customer->update($input);
            }

            switch ($customer->cu_type) {
                case CuCuType::INDIVIDUAL:
                    $customer->CustomerCompany()->delete();

                    $input_individual = $input['customer_individual'] ?? [];

                    $customer->CustomerIndividual()->updateOrCreate(
                        [
                            'cu_id' => $customer->cu_id,
                        ],
                        $input_individual,
                    );

                    break;

                case CuCuType::COMPANY:
                    $customer->CustomerIndividual()->delete();

                    $input_company = $input['customer_company'];

                    $customer->CustomerCompany()->updateOrCreate(
                        [
                            'cu_id' => $customer->cu_id,
                        ],
                        $input_company,
                    );

                    break;

                default:
                    break;
            }
        });

        return $this->response()->withData($customer)->respond();
    }

    #[PermissionAction(PermissionAction::DELETE)]
    public function destroy(Customer $customer): Response
    {
        DB::transaction(function () use ($customer) {
            $customer->CustomerIndividual()->delete();
            $customer->CustomerCompany()->delete();
            $customer->delete();
        });

        return $this->response()->withData($customer)->respond();
    }

    #[PermissionAction(PermissionAction::ADD)]
    public function create(Request $request): Response
    {
        $this->options();

        $this->response()->withExtras(
            Admin::optionsWithRoles(),
        );

        $customer = new Customer([
            'cu_type' => CuCuType::INDIVIDUAL,
        ]);

        return $this->response()->withData($customer)->respond();
    }

    #[PermissionAction(PermissionAction::EDIT)]
    public function edit(Customer $customer): Response
    {
        $this->options();

        $this->response()->withExtras(
            Admin::optionsWithRoles(),
        );

        $this->response()->withExtras(
            VehicleInspection::kvList(cu_id: $customer->cu_id),
            SaleOrder::kvList(cu_id: $customer->cu_id),
            Payment::kvList(cu_id: $customer->cu_id),
            SaleSettlement::kvList(cu_id: $customer->cu_id),
            VehicleUsage::kvList(cu_id: $customer->cu_id),
            VehicleRepair::kvList(cu_id: $customer->cu_id),
            VehicleViolation::kvList(cu_id: $customer->cu_id),
            VehicleManualViolation::kvList(cu_id: $customer->cu_id),
        );

        $customer->load('CustomerIndividual', 'CustomerCompany');

        return $this->response()->withData($customer)->respond();
    }

    #[PermissionAction(PermissionAction::ADD)]
    #[PermissionAction(PermissionAction::EDIT)]
    public function upload(Request $request): Response
    {
        return Uploader::upload(
            $request,
            'customer',
            ['cui_id1_photo', 'cui_id2_photo', 'cui_driver_license1_photo', 'cui_driver_license2_photo', 'cuc_business_license_photo', 'cu_cert_photo', 'cu_additional_photos'],
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
