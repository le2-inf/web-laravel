<?php

namespace App\Models\Vehicle;

use App\Attributes\ClassName;
use App\Enum\Rental\RpIsValid;
use App\Enum\Rental\RpPayStatus;
use App\Enum\Rental\RpPtId;
use App\Enum\Vehicle\ViDrivingLicense;
use App\Enum\Vehicle\ViInspectionType;
use App\Enum\Vehicle\ViOperationLicense;
use App\Enum\Vehicle\ViPolicyCopy;
use App\Enum\Vehicle\ViVehicleDamageStatus;
use App\Models\_\ModelTrait;
use App\Models\Payment\Payment;
use App\Models\Payment\PaymentType;
use App\Models\Sale\SaleOrder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

#[ClassName('验车')]
/**
 * @property int         $vi_id                       验车序号
 * @property int         $so_id                       订单序号
 * @property int         $ve_id                       车辆序号；订单可能换车
 * @property mixed       $inspection_type             验车类型；发车或退车
 * @property null|int    $policy_copy                 保单复印件
 * @property null|int    $driving_license             行驶证
 * @property null|int    $operation_license           营运证（硬卡）
 * @property int         $vehicle_damage_status       车损状态；TRUE 表示有车损，FALSE 表示无车损
 * @property Carbon      $inspection_datetime         验车完成日时
 * @property int         $vi_mileage                  公里数
 * @property mixed       $processed_by                验车人
 * @property null|float  $damage_deduction            车损扣款
 * @property null|string $vi_remark                   验车备注
 * @property null|bool   $add_should_pay              是否为客户应收款
 * @property null|mixed  $additional_photos           附加照片；存储照片路径
 * @property null|mixed  $inspection_info             验车信息；包括验车照片路径和验车文字描述
 * @property mixed       $inspection_type_label       验车类型
 * @property mixed       $policy_copy_label           保单复印件
 * @property mixed       $driving_license_label       行驶证
 * @property mixed       $operation_license_label     营运证
 * @property mixed       $vehicle_damage_status_label 车损状态
 * @property Vehicle     $Vehicle
 * @property SaleOrder   $SaleOrder
 * @property Payment     $Payment
 */
class VehicleInspection extends Model
{
    use ModelTrait;

    protected $primaryKey = 'vi_id';

    protected $guarded = ['vi_id'];

    protected $attributes = [];

    protected $appends = [
        'inspection_type_label',
        'policy_copy_label',
        'driving_license_label',
        'operation_license_label',
        'vehicle_damage_status_label',
    ];

    protected $casts = [
        'inspection_datetime'   => 'datetime:Y-m-d H:i',
        'inspection_type'       => ViInspectionType::class,
        'policy_copy'           => ViPolicyCopy::class,
        'driving_license'       => ViDrivingLicense::class,
        'operation_license'     => ViOperationLicense::class,
        'vehicle_damage_status' => ViVehicleDamageStatus::class,
    ];

    public function Vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 've_id', 've_id');
    }

    public function SaleOrder(): BelongsTo
    {
        return $this->belongsTo(SaleOrder::class, 'so_id', 'so_id');
    }

    public function Payment(): HasOne
    {
        $pt_id = RpPtId::VEHICLE_DAMAGE;

        return $this->hasOne(Payment::class, 'vi_id', 'vi_id')
            ->where('pt_id', '=', $pt_id)->where('is_valid', '=', RpIsValid::VALID)
            ->withDefault(
                [
                    'pt_id'            => $pt_id,
                    'payment_day_type' => PaymentType::query()->where('pt_id', '=', $pt_id)->first(),
                    'should_pay_date'  => now()->format('Y-m-d'),
                    'pay_status'       => RpPayStatus::UNPAID,
                ]
            )->with('PaymentType')
        ;
    }

    public static function indexColumns(): array
    {
        return [
            'VehicleInspection.inspection_type'       => fn ($item) => $item->inspection_type_label,
            'Customer.contact_name'                   => fn ($item) => $item->contact_name,
            'Vehicle.plate_no'                        => fn ($item) => $item->plate_no,
            'VehicleInspection.policy_copy'           => fn ($item) => $item->policy_copy_label,
            'VehicleInspection.driving_license'       => fn ($item) => $item->driving_license_label,
            'VehicleInspection.operation_license'     => fn ($item) => $item->operation_license_label,
            'VehicleInspection.vi_mileage'            => fn ($item) => $item->vi_mileage,
            'VehicleInspection.vehicle_damage_status' => fn ($item) => $item->vehicle_damage_status_label,
            'VehicleInspection.inspection_datetime'   => fn ($item) => $item->inspection_datetime_,
            'VehicleInspection.vi_remark'             => fn ($item) => $item->vi_remark,
            'VehicleInspection.processed_by'          => fn ($item) => $item->processed_by,
            'VehicleInspection.inspection_info'       => fn ($item) => static::str_render($item->inspection_info, 'inspection_info'),
        ];
    }

    public static function indexQuery(array $search = []): Builder
    {
        $ve_id = $search['ve_id'] ?? null;
        $so_id = $search['so_id'] ?? null;
        $cu_id = $search['cu_id'] ?? null;

        return DB::query()
            ->from('vehicle_inspections', 'vi')
            ->leftJoin('vehicles as ve', 've.ve_id', '=', 'vi.ve_id')
            ->leftJoin('sale_orders as so', 'so.so_id', '=', 'vi.so_id')
            ->leftJoin('customers as cu', 'cu.cu_id', '=', 'so.cu_id')
            ->when($ve_id, function (Builder $query) use ($ve_id) {
                $query->where('vi.ve_id', '=', $ve_id);
            })
            ->when($so_id, function (Builder $query) use ($so_id) {
                $so_id && $query->where('vi.so_id', '=', $so_id);
            })
            ->when($cu_id, function (Builder $query) use ($cu_id) {
                $query->where('cu.cu_id', '=', $cu_id);
            })
            ->when(
                null === $ve_id && null === $so_id && null === $cu_id,
                function (Builder $query) {
                    $query->orderByDesc('vi.vi_id');
                },
                function (Builder $query) {
                    $query->orderBy('vi.vi_id');
                }
            )
            ->select('vi.*', 've.plate_no', 'cu.contact_name', 'cu.contact_phone')
            ->addSelect(
                DB::raw(ViInspectionType::toCaseSQL()),
                DB::raw(ViPolicyCopy::toCaseSQL()),
                DB::raw(ViDrivingLicense::toCaseSQL()),
                DB::raw(ViOperationLicense::toCaseSQL()),
                DB::raw(ViVehicleDamageStatus::toCaseSQL()),
                DB::raw("to_char(inspection_datetime, 'YYYY-MM-DD HH24:MI:SS') as inspection_datetime_"),
            )
        ;
    }

    public static function options(?\Closure $where = null): array
    {
        return [];
    }

    protected function additionalPhotos(): Attribute
    {
        return $this->uploadFileArray();
    }

    protected function inspectionInfo(): Attribute
    {
        return $this->arrayInfo();
    }

    protected function inspectionTypeLabel(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->getAttribute('inspection_type')?->label
        );
    }

    protected function policyCopyLabel(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->getAttribute('policy_copy')?->label
        );
    }

    protected function drivingLicenseLabel(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->getAttribute('driving_license')?->label
        );
    }

    protected function operationLicenseLabel(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->getAttribute('operation_license')?->label
        );
    }

    protected function vehicleDamageStatusLabel(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->getAttribute('vehicle_damage_status')?->label
        );
    }
}
