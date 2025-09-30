<?php

namespace App\Http\Controllers\Admin\Config;

use App\Attributes\PermissionNoneType;
use App\Http\Controllers\Controller;
use App\Http\Middleware\CheckAdminIsMock;
use App\Models\Rental\RentalCompany;
use App\Services\Uploader;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

#[PermissionNoneType('公司信息管理')]
class RentalCompanyController extends Controller
{
    public function __construct()
    {
        $this->middleware(CheckAdminIsMock::class);
    }

    public function show()
    {
        $company = RentalCompany::query()->firstOrNew();

        return $this->response()->withData($company)->respond();
    }

    public function edit(Request $request)
    {
        $company = RentalCompany::query()->firstOrNew();

        return $this->response()->withData($company)->respond();
    }

    public function update(Request $request): Response
    {
        $validator = Validator::make(
            $request->all(),
            [
                'cp_name'                 => ['bail', 'required', 'max:255'],
                'cp_address'              => ['bail', 'nullable', 'max:255'],
                'cp_longitude'            => ['bail', 'nullable', 'numeric'],
                'cp_latitude'             => ['bail', 'nullable', 'numeric'],
                'cp_phone'                => ['bail', 'nullable', 'max:255'],
                'cp_description'          => ['bail', 'nullable', 'string'],
                'cp_rental_notes'         => ['bail', 'nullable', 'string'],
                'cp_purchase_notes'       => ['bail', 'nullable', 'string'],
                'cp_invoice_text'         => ['bail', 'nullable', 'string'],
                'cp_bank_name'            => ['bail', 'nullable', 'max:255'],
                'cp_bank_account_no'      => ['bail', 'nullable', 'max:255'],
                'cp_social_credit_code'   => ['bail', 'nullable', 'max:255'],
                'cp_wechat_notify_mobile' => ['bail', 'nullable', 'max:255'],
            ] + Uploader::validator_rule_upload_object('cp_company_photo', false)
            + Uploader::validator_rule_upload_object('cp_business_license_photo', false),
            [],
            trans_property(RentalCompany::class)
        );

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $input = $validator->validated();

        DB::transaction(function () use (&$input, &$rentalCompany) {
            $rentalCompany = RentalCompany::query()->firstOrNew();
            $rentalCompany->update($input);
        });

        return $this->response()->withData($rentalCompany)->respond();
    }

    public static function labelOptions(Controller $controller): void
    {
        $controller->response()->withExtras(
        );
    }

    protected function options(?bool $with_group_count = false): void
    {
        $this->response()->withExtras(
        );
    }
}
