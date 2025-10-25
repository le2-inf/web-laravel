<?php

namespace App\Http\Controllers\Admin\Payment;

use App\Attributes\PermissionAction;
use App\Attributes\PermissionType;
use App\Enum\Rental\PaPaStatus;
use App\Http\Controllers\Controller;
use App\Models\Payment\PaymentAccount;
use App\Services\PaginateService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

#[PermissionType('收付款账号管理')]
class PaymentAccountController extends Controller
{
    public static function labelOptions(Controller $controller): void
    {
        $controller->response()->withExtras(
        );
    }

    #[PermissionAction(PermissionAction::ADD)]
    public function create(): Response
    {
        $this->options();
        $this->response()->withExtras(
        );

        $paymentAccount = new PaymentAccount([
            'pa_status' => PaPaStatus::ENABLED,
        ]);

        return $this->response()->withData($paymentAccount)->respond();
    }

    #[PermissionAction(PermissionAction::INDEX)]
    public function index(Request $request): Response
    {
        $this->options(true);
        $this->response()->withExtras(
        );

        $query = PaymentAccount::indexQuery();

        $paginate = new PaginateService(
            [],
            [],
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
    public function show(PaymentAccount $paymentAccount): Response
    {
        $this->options();

        return $this->response()->withData($paymentAccount)->respond();
    }

    #[PermissionAction(PermissionAction::EDIT)]
    public function edit(PaymentAccount $paymentAccount): Response
    {
        $this->options();

        return $this->response()->withData($paymentAccount)->respond();
    }

    #[PermissionAction(PermissionAction::EDIT)]
    public function update(Request $request, ?PaymentAccount $paymentAccount): Response
    {
        $validator = Validator::make(
            $request->all(),
            [
                'pa_name'   => ['required', 'string', 'max:255'],
                'pa_status' => ['required', Rule::in(PaPaStatus::label_keys())],
                'pa_remark' => ['nullable', 'string'],
            ],
            [],
            trans_property(PaymentAccount::class)
        )
            ->after(function (\Illuminate\Validation\Validator $validator) use (&$vehicle, &$customer) {
                if (!$validator->failed()) {
                }
            })
        ;
        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $input = $validator->validated();

        if (null === $paymentAccount) {
            $paymentAccount = PaymentAccount::query()->create($input);
        } else {
            $paymentAccount->update($input);
        }

        return $this->response()->withData($paymentAccount)->respond();
    }

    #[PermissionAction(PermissionAction::DELETE)]
    public function destroy(PaymentAccount $paymentAccount): Response
    {
        $paymentAccount->delete();

        return $this->response()->withData($paymentAccount)->respond();
    }

    protected function options(?bool $with_group_count = false): void
    {
        $this->response()->withExtras(
            PaPaStatus::options(),
        );
    }
}
