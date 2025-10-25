<?php

namespace App\Models\Rental\Vehicle;

use App\Attributes\ClassName;
use App\Attributes\ColumnDesc;
use App\Enum\Vehicle\VeStatusService;
use App\Enum\Vehicle\VsInspectionType;
use App\Models\Configuration;
use App\Models\ModelTrait;
use App\Models\Rental\ImportTrait;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

#[ClassName('车辆年检', '记录')]
#[ColumnDesc('inspection_type', required: true, enum_class: VsInspectionType::class)]
#[ColumnDesc('inspector', required: true)]
#[ColumnDesc('inspection_date', required: true)]
#[ColumnDesc('inspection_amount', required: true)]
#[ColumnDesc('next_inspection_date', required: true)]
#[ColumnDesc('vs_remark')]
/**
 * @property int                     $vs_id                      年检记录序号
 * @property string|VsInspectionType $inspection_type            年检类型
 * @property int                     $ve_id                      车辆序号
 * @property string                  $inspector                  年检处理人
 * @property Carbon                  $inspection_date            年检日期
 * @property float                   $inspection_amount          年检金额
 * @property Carbon|string           $next_inspection_date       下次年检日期
 * @property null|mixed              $additional_photos          附加照片;存储照片路径的JSON数组
 * @property null|string             $vs_remark                  年检备注
 * @property null|string             $vehicle_last_date          最后一次车辆年检时间
 * @property null|string             $gas_cylinder_last_date     最后一次气罐年检时间
 * @property null|string             $certificate_last_date      最后一次车证年检时间
 * @property null|string             $business_license_last_date 最后一次营业执照年检时间
 * @property RentalVehicle           $RentalVehicle
 */
class RentalVehicleSchedule extends Model
{
    use ModelTrait;

    use ImportTrait;

    protected $primaryKey = 'vs_id';

    protected $guarded = ['vs_id'];

    protected $attributes = [];

    protected $casts = [
        'inspection_date'      => 'date:Y-m-d',
        'next_inspection_date' => 'date:Y-m-d',
        'maintenance_amount'   => 'decimal:2',
        'inspection_type'      => VsInspectionType::class,
    ];

    protected $appends = [
        'inspection_type_label',
    ];

    public function RentalVehicle(): BelongsTo
    {
        return $this->belongsTo(RentalVehicle::class, 've_id', 've_id')->with('RentalVehicleModel');
    }

    public static function indexQuery(array $search = []): Builder
    {
        $ve_id = $search['ve_id'] ?? null;

        return DB::query()
            ->from('rental_vehicle_schedules', 'vs')
            ->when(null === $ve_id, function (Builder $query) {
                return $query->joinSub(
                    // 直接在 joinSub 中定义子查询
                    DB::table('rental_vehicle_schedules')
                        ->select('ve_id', 'inspection_type', DB::raw('MAX(next_inspection_date) as max_next_inspection_date'))
                        ->groupBy('ve_id', 'inspection_type'),
                    'p2',
                    function ($join) {
                        $join->on('vs.ve_id', '=', 'p2.ve_id')
                            ->on('vs.inspection_type', '=', 'p2.inspection_type')
                            ->on('vs.next_inspection_date', '=', 'p2.max_next_inspection_date')
                        ;
                    }
                );
            })
            ->leftJoin('rental_vehicles as ve', 've.ve_id', '=', 'vs.ve_id')
            ->leftJoin('rental_vehicle_models as vm', 'vm.vm_id', '=', 've.vm_id')
            ->when($ve_id, function (Builder $query) use ($ve_id) {
                $query->where('vs.ve_id', '=', $ve_id);
            })
            ->when(
                null === $ve_id,
                function (Builder $query) {
                    $query->whereRaw(
                        'EXTRACT(EPOCH FROM now() - vs.next_inspection_date) / 86400.0 <= ?',
                        [Configuration::fetch('risk.vs_interval_day.less')]
                    );
                },
            )
            ->when(
                null === $ve_id,
                function (Builder $query) {
                    $query->orderByDesc('vs.next_inspection_date');
                },
                function (Builder $query) {
                    $query->orderBy('vs.vs_id');
                }
            )
            ->select('vs.*', 've.plate_no', 'vm.brand_name', 'vm.model_name')
            ->addSelect(
                DB::raw(VsInspectionType::toCaseSQL()),
            )
            ->when(null === $ve_id, function (Builder $query) {
                $query->addSelect(DB::raw('CAST(EXTRACT(EPOCH FROM now() - vs.next_inspection_date) / 86400.0 AS INTEGER) as vs_interval_day'));
            })
        ;
    }

    public static function importColumns(): array
    {
        return [
            'inspection_type'      => [RentalVehicleSchedule::class, 'inspection_type'],
            'plate_no'             => [RentalVehicle::class, 'plate_no'],
            'inspector'            => [RentalVehicleSchedule::class, 'inspector'],
            'inspection_date'      => [RentalVehicleSchedule::class, 'inspection_date'],
            'inspection_amount'    => [RentalVehicleSchedule::class, 'inspection_amount'],
            'next_inspection_date' => [RentalVehicleSchedule::class, 'next_inspection_date'],
            'vs_remark'            => [RentalVehicleSchedule::class, 'vs_remark'],
        ];
    }

    public static function importBeforeValidateDo(): \Closure
    {
        return function (&$item) {
            $item['inspection_type'] = VsInspectionType::searchValue($item['inspection_type'] ?? null);
            $item['ve_id']           = RentalVehicle::plateNoKv($item['plate_no'] ?? null);
        };
    }

    public static function importValidatorRule(array $item, array $fieldAttributes): void
    {
        $rules = [
            'inspection_type'      => ['required', 'string', Rule::in(VsInspectionType::label_keys())],
            've_id'                => ['required', 'integer'],
            'inspector'            => ['required', 'string', 'max:255'],
            'inspection_date'      => ['required', 'date'],
            'next_inspection_date' => ['required', 'date', 'after:inspection_date'],
            'inspection_amount'    => ['required', 'decimal:0,2', 'gte:0'],
            'vs_remark'            => ['nullable', 'string'],
        ];

        $validator = Validator::make($item, $rules, [], $fieldAttributes);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
    }

    public static function importAfterValidatorDo(): \Closure
    {
        return function () {};
    }

    public static function importCreateDo(): \Closure
    {
        return function ($input) {
            $rentalVehicleSchedule = RentalVehicleSchedule::query()->create($input);
        };
    }

    public static function options(?\Closure $where = null): array
    {
        return [];
    }

    public static function stQuery(array $search = []): Builder
    {
        $subSql = "
SELECT
  ve_id,
  MAX(inspection_date) FILTER (WHERE inspection_type = 'vehicle')       AS vehicle_last_date,
  MAX(inspection_date) FILTER (WHERE inspection_type = 'gas_cylinder')  AS gas_cylinder_last_date,
  MAX(inspection_date) FILTER (WHERE inspection_type = 'certificate')   AS certificate_last_date,
  MAX(inspection_date) FILTER (WHERE inspection_type = 'business_license')   AS business_license_last_date
FROM rental_vehicle_schedules
GROUP BY ve_id
";

        return DB::query()
            ->from('rental_vehicles', 've')
            ->leftJoin('rental_vehicle_models as vm', 've.vm_id', '=', 'vm.vm_id')
            ->leftJoinSub($subSql, 'vs', 'vs.ve_id', '=', 've.ve_id')
            ->select('ve.*', 'vm.brand_name', 'vm.model_name', 'vs.vehicle_last_date', 'vs.gas_cylinder_last_date', 'vs.certificate_last_date', 'vs.business_license_last_date')
            ->orderBy('ve.ve_id', 'desc')
            ->where('ve.status_service', '=', VeStatusService::YES)
        ;
    }

    public static function stColumns(): array
    {
        return [
            'RentalVehicle.ve_id'                              => fn ($item) => $item->ve_id,
            'RentalVehicle.plate_no'                           => fn ($item) => $item->plate_no,
            'RentalVehicleModel.brand_model'                   => fn ($item) => $item->brand_name.'-'.$item->model_name,
            'RentalVehicle.ve_license_owner'                   => fn ($item) => $item->ve_license_owner,
            'RentalVehicle.ve_license_vin_code'                => fn ($item) => $item->ve_license_vin_code,
            'RentalVehicle.ve_license_engine_no'               => fn ($item) => $item->ve_license_engine_no,
            'RentalVehicleSchedule.vehicle_last_date'          => fn ($item) => $item->vehicle_last_date,
            'RentalVehicleSchedule.gas_cylinder_last_date'     => fn ($item) => $item->gas_cylinder_last_date,
            'RentalVehicleSchedule.certificate_last_date'      => fn ($item) => $item->certificate_last_date,
            'RentalVehicleSchedule.business_license_last_date' => fn ($item) => $item->business_license_last_date,
        ];
    }

    protected function inspectionTypeLabel(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->getAttribute('inspection_type')?->label
        );
    }

    protected function additionalPhotos(): Attribute
    {
        return $this->uploadFileArray();
    }
}
