<?php

namespace App\Http\Controllers\Admin\Vehicle;

use App\Attributes\PermissionAction;
use App\Attributes\PermissionType;
use App\Enum\One\OaOaProvince;
use App\Enum\One\OaOaType;
use App\Http\Controllers\Controller;
use App\Models\Rental\One\RentalOneAccount;
use App\Services\PaginateService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

#[PermissionType('122账号管理')]
class RentalOneAccountController extends Controller
{
    public static function labelOptions(Controller $controller): void
    {
        $controller->response()->withExtras(
            OaOaType::labelOptions(),
        );
    }

    #[PermissionAction(PermissionAction::ADD)]
    public function create(): Response
    {
        $this->options();
        $this->response()->withExtras(
            OaOaProvince::options(),
        );

        return $this->response()->respond();
    }

    #[PermissionAction(PermissionAction::INDEX)]
    public function index(Request $request): Response
    {
        $this->options(true);
        $this->response()->withExtras(
        );

        $query = RentalOneAccount::indexQuery();

        $paginate = new PaginateService(
            [],
            [['oa.oa_id', 'desc']],
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
    public function show(RentalOneAccount $rentalOneAccount): Response
    {
        return $this->response()->withData($rentalOneAccount)->respond();
    }

    #[PermissionAction(PermissionAction::EDIT)]
    public function edit(RentalOneAccount $rentalOneAccount): Response
    {
        $this->options();
        $this->response()->withExtras(
            OaOaProvince::options(),
        );

        return $this->response()->withData($rentalOneAccount)->respond();
    }

    #[PermissionAction(PermissionAction::EDIT)]
    public function update(Request $request, ?RentalOneAccount $rentalOneAccount): Response
    {
        $validator = Validator::make(
            $request->all(),
            [
                'oa_type'      => ['required', Rule::in(OaOaType::label_keys())],
                'oa_name'      => ['required', 'string', 'max:255', Rule::unique(RentalOneAccount::class)->ignore($rentalOneAccount)],
                'oa_province'  => ['required', 'string', Rule::in(OaOaProvince::getKeys())],
                'cookie_value' => ['nullable', 'string'],
            ],
            [],
            trans_property(RentalOneAccount::class)
        )
            ->after(function (\Illuminate\Validation\Validator $validator) use (&$rentalVehicle) {
                if (!$validator->failed()) {
                }
            })
        ;

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $input = $validator->validated();
        if (null === $rentalOneAccount) {
            $rentalOneAccount = RentalOneAccount::query()->create($input);
        } else {
            $input['cookie_refresh_at'] = null;
            $rentalOneAccount->update($input);
        }

        return $this->response()->withData($rentalOneAccount)->respond();
    }

    #[PermissionAction(PermissionAction::DELETE)]
    public function destroy(RentalOneAccount $rentalOneAccount): Response
    {
        $rentalOneAccount->delete();

        return $this->response()->withData($rentalOneAccount)->respond();
    }

    protected function options(?bool $with_group_count = false): void
    {
        $this->response()->withExtras(
            OaOaType::options(),
            ['how_cookie_url' => config('setting.manual_host').'/config/122']
        );
    }
}
