<?php

namespace App\Http\Controllers\Admin\Config;

use App\Attributes\PermissionAction;
use App\Attributes\PermissionType;
use App\Enum\Rental\DtDtExportType;
use App\Enum\Rental\DtDtFileType;
use App\Enum\Rental\DtDtStatus;
use App\Enum\Rental\DtDtType;
use App\Enum\Rental\DtDtTypeMacroChars;
use App\Http\Controllers\Controller;
use App\Models\Rental\Sale\RentalDocTpl;
use App\Services\DocTplService;
use App\Services\PaginateService;
use App\Services\Uploader;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

#[PermissionType('文档模板管理')]
class RentalDocTplController extends Controller
{
    public static function labelOptions(Controller $controller): void
    {
        $controller->response()->withExtras(
            DtDtType::labelOptions(),
            DtDtFileType::labelOptions(),
            DtDtStatus::labelOptions(),
        );
    }

    #[PermissionAction(PermissionAction::INDEX)]
    public function index(Request $request): Response
    {
        $this->options(true);

        $query = RentalDocTpl::indexQuery();

        $paginate = new PaginateService(
            [],
            [['dt.dt_id', 'desc']],
            ['kw'],
            []
        );

        $paginate->paginator($query, $request, [
            'kw__func' => function ($value, Builder $builder) {
                $builder->where(function (Builder $builder) use ($value) {
                    $builder->where('dt.dt_name', 'like', '%'.$value.'%')->orWhere('dt.dt_remark', 'like', '%'.$value.'%');
                });
            },
        ]);

        return $this->response()->withData($paginate)->respond();
    }

    #[PermissionAction(PermissionAction::ADD)]
    public function create(Request $request): Response
    {
        $this->options();

        $rentalDocTpl = new RentalDocTpl([
            'dt_status'    => DtDtStatus::ENABLED,
            'dt_file_type' => DtDtFileType::WORD,
        ]);

        $this->response()->withExtras(
            DtDtType::tryFrom(DtDtType::RENTAL_ORDER)->getFieldsAndRelations(true),
            DtDtType::tryFrom(DtDtType::RENTAL_SETTLEMENT)->getFieldsAndRelations(true),
            DtDtType::tryFrom(DtDtType::RENTAL_PAYMENT)->getFieldsAndRelations(true),
            DtDtType::tryFrom(DtDtType::RENTAL_VEHICLE_INSPECTION)->getFieldsAndRelations(true),
            DtDtStatus::options(),
        );

        return $this->response()->withData($rentalDocTpl)->respond();
    }

    #[PermissionAction(PermissionAction::ADD)]
    public function store(Request $request): Response
    {
        return $this->update($request, null);
    }

    #[PermissionAction(PermissionAction::SHOW)]
    public function show(RentalDocTpl $rentalDocTpl): Response
    {
        $this->options();
        $this->response()->withExtras(
        );

        return $this->response()->withData($rentalDocTpl)->respond();
    }

    #[PermissionAction(PermissionAction::INDEX)]
    public function preview(Request $request, RentalDocTpl $rentalDocTpl, DocTplService $docTplService)
    {
        $input = $request->validate([
            'mode' => ['required', Rule::in(DtDtExportType::label_keys())],
        ]);

        $url = $docTplService->GenerateDoc($rentalDocTpl, $input['mode']);

        return $this->response()->withData($url)->respond();
    }

    #[PermissionAction(PermissionAction::EDIT)]
    public function edit(RentalDocTpl $rentalDocTpl): Response
    {
        $this->options();

        $this->response()->withExtras(
            DtDtType::tryFrom(DtDtType::RENTAL_ORDER)->getFieldsAndRelations(true),
            DtDtType::tryFrom(DtDtType::RENTAL_SETTLEMENT)->getFieldsAndRelations(true),
            DtDtType::tryFrom(DtDtType::RENTAL_PAYMENT)->getFieldsAndRelations(true),
            DtDtType::tryFrom(DtDtType::RENTAL_VEHICLE_INSPECTION)->getFieldsAndRelations(true),
            DtDtStatus::options(),
        );

        return $this->response()->withData($rentalDocTpl)->respond();
    }

    #[PermissionAction(PermissionAction::EDIT)]
    public function update(Request $request, ?RentalDocTpl $rentalDocTpl): Response
    {
        $validator = Validator::make(
            $request->all(),
            [
                'dt_type'      => ['bail', 'required', Rule::in(DtDtType::label_keys())],
                'dt_file_type' => ['bail', 'required', Rule::in(DtDtFileType::label_keys())],
                'dt_name'      => ['bail', 'required', 'max:255'],
                'dt_status'    => ['required', Rule::in(DtDtStatus::label_keys())],
                'dt_remark'    => ['bail', 'nullable', 'string', 'max:255'],
            ]
            + Uploader::validator_rule_upload_object('dt_file', true),
            [],
            trans_property(RentalDocTpl::class)
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

        DB::transaction(function () use (&$input, &$rentalDocTpl) {
            if (null === $rentalDocTpl) {
                /** @var RentalDocTpl $rentalDocTpl */
                $rentalDocTpl = RentalDocTpl::query()->create($input);
            } else {
                $rentalDocTpl->update($input);
            }
        });

        $rentalDocTpl->refresh();

        return $this->response()->withData($rentalDocTpl)->respond();
    }

    #[PermissionAction(PermissionAction::DELETE)]
    public function destroy(RentalDocTpl $rentalDocTpl): Response
    {
        $rentalDocTpl->delete();

        return $this->response()->withData($rentalDocTpl)->respond();
    }

    #[PermissionAction(PermissionAction::EDIT)]
    public function status(Request $request, RentalDocTpl $rentalDocTpl): Response
    {
        $validator = Validator::make(
            $request->all(),
            [
                'dt_status' => ['bail', 'required', Rule::in(DtDtStatus::label_keys())],
            ],
            [],
            trans_property(RentalDocTpl::class)
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

        $rentalDocTpl->update([
            'dt_status' => $input['dt_status'],
        ]);

        return $this->response()->withData($rentalDocTpl)->respond();
    }

    #[PermissionAction(PermissionAction::ADD)]
    #[PermissionAction(PermissionAction::EDIT)]
    public function upload(Request $request): Response
    {
        return Uploader::upload($request, 'rental_doc_tpl', ['dt_file'], $this);
    }

    protected function options(?bool $with_group_count = false): void
    {
        $this->response()->withExtras(
            DtDtType::options(),
            DtDtFileType::options(),
            DtDtStatus::options(),
            DtDtTypeMacroChars::kv(),
        );
    }
}
