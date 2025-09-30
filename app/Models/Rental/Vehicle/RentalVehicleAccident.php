<?php

namespace App\Models\Rental\Vehicle;

use App\Attributes\ClassName;
use App\Attributes\ColumnDesc;
use App\Attributes\ColumnType;
use App\Enum\Vehicle\VaClaimStatus;
use App\Enum\Vehicle\VaManagedVehicle;
use App\Enum\Vehicle\VaPickupStatus;
use App\Enum\Vehicle\VaRepairStatus;
use App\Enum\Vehicle\VaSettlementMethod;
use App\Enum\Vehicle\VaSettlementStatus;
use App\Models\ModelTrait;
use App\Models\Rental\Customer\RentalCustomer;
use App\Models\Rental\ImportTrait;
use App\Models\Rental\Sale\RentalSaleOrder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

#[ClassName('出险', '记录')]
#[ColumnDesc('va_id')]
#[ColumnDesc('ve_id')]
#[ColumnDesc('plate_no', required: true)]
#[ColumnDesc('so_id')]
#[ColumnDesc('accident_location')]
#[ColumnDesc('accident_dt', type: ColumnType::DATETIME, required: true)]
#[ColumnDesc('responsible_party')]
#[ColumnDesc('claim_status', enum_class: VaClaimStatus::class)]
#[ColumnDesc('self_amount')]
#[ColumnDesc('third_party_amount')]
#[ColumnDesc('insurance_company')]
#[ColumnDesc('va_description')]
#[ColumnDesc('factory_in_dt', type: ColumnType::DATETIME)]
#[ColumnDesc('repair_content')]
#[ColumnDesc('repair_status', enum_class: VaRepairStatus::class)]
#[ColumnDesc('factory_out_dt', type: ColumnType::DATETIME)]
#[ColumnDesc('settlement_status', enum_class: VaSettlementStatus::class)]
#[ColumnDesc('pickup_status', enum_class: VaPickupStatus::class)]
#[ColumnDesc('settlement_method', enum_class: VaSettlementMethod::class)]
#[ColumnDesc('managed_vehicle', enum_class: VaManagedVehicle::class)]
#[ColumnDesc('va_remark')]
#[ColumnDesc('additional_photos')]
#[ColumnDesc('accident_info')]
/**
 * @property int                            $va_id               出险序号
 * @property int                            $ve_id               车辆序号
 * @property null|int                       $so_id               订单序号
 * @property int                            $sc_id               修理厂序号
 * @property null|string                    $accident_location   事故地点
 * @property Carbon                         $accident_dt         出险日时
 * @property null|string                    $responsible_party   责任方
 * @property null|string|VaClaimStatus      $claim_status        理赔状态
 * @property null|float                     $self_amount         己方金额(元)
 * @property null|float                     $third_party_amount  第三方金额(元)
 * @property null|string                    $insurance_company   保险公司
 * @property null|string                    $va_description      详细描述
 * @property null|Carbon                    $factory_in_dt       进厂日时
 * @property null|string                    $repair_content      维修内容
 * @property null|string|VaRepairStatus     $repair_status       维修状态
 * @property null|Carbon                    $factory_out_dt      出厂日时
 * @property null|string|VaSettlementStatus $settlement_status   结算状态
 * @property null|string|VaPickupStatus     $pickup_status       提车状态
 * @property null|string|VaSettlementMethod $settlement_method   结算方式
 * @property null|string|VaManagedVehicle   $managed_vehicle     代管车辆
 * @property null|string                    $va_remark           出险备注
 * @property null|mixed                     $additional_photos   附加照片
 * @property null|array                     $accident_info       车辆出险照片以及描述
 * @property RentalVehicle                  $RentalVehicle
 * @property RentalCustomer                 $RentalCustomer
 * @property RentalServiceCenter            $RentalServiceCenter
 */
class RentalVehicleAccident extends Model
{
    use ModelTrait;

    use ImportTrait;

    protected $primaryKey = 'va_id';

    protected $guarded = ['va_id'];

    protected $attributes = [];

    protected $casts = [
        'accident_dt'        => 'datetime:Y-m-d H:i',
        'factory_in_dt'      => 'datetime:Y-m-d H:i',
        'factory_out_dt'     => 'datetime:Y-m-d H:i',
        'self_amount'        => 'decimal:2',
        'third_party_amount' => 'decimal:2',
        'claim_status'       => VaClaimStatus::class,
        'repair_status'      => VaRepairStatus::class,
        'settlement_status'  => VaSettlementStatus::class,
        'pickup_status'      => VaPickupStatus::class,
        'settlement_method'  => VaSettlementMethod::class,
        'managed_vehicle'    => VaManagedVehicle::class,
    ];

    protected $appends = [
        'claim_status_label',
        'repair_status_label',
        'settlement_status_label',
        'pickup_status_label',
        'settlement_method_label',
        'managed_vehicle_label',
    ];

    public function RentalVehicle(): BelongsTo
    {
        return $this->belongsTo(RentalVehicle::class, 've_id', 've_id');
    }

    public function RentalSaleOrder(): BelongsTo
    {
        return $this->belongsTo(RentalSaleOrder::class, 'so_id', 'so_id');
    }

    public function RentalServiceCenter(): BelongsTo
    {
        return $this->belongsTo(RentalServiceCenter::class, 'sc_id', 'sc_id');
    }

    public static function indexQuery(array $search = []): Builder
    {
        $ve_id = $search['ve_id'] ?? null;
        $so_id = $search['so_id'] ?? null;
        $sc_id = $search['sc_id'] ?? null;

        return DB::query()
            ->from('rental_vehicle_accidents', 'va')
            ->leftJoin('rental_service_centers as sc', 'sc.sc_id', '=', 'va.sc_id')
            ->leftJoin('rental_vehicles as ve', 've.ve_id', '=', 'va.ve_id')
            ->leftJoin('rental_vehicle_models as _vm', '_vm.vm_id', '=', 've.vm_id')
            ->leftJoin('rental_sale_orders as so', 'so.so_id', '=', 'va.so_id')
            ->leftJoin('rental_customers as cu', 'cu.cu_id', '=', 'so.cu_id')
            ->when($ve_id, function (Builder $query) use ($ve_id) {
                $query->where('va.ve_id', '=', $ve_id);
            })
            ->when($so_id, function (Builder $query) use ($so_id) {
                $query->where('va.so_id', '=', $so_id);
            })
            ->when($sc_id, function (Builder $query) use ($sc_id) {
                $query->where('va.sc_id', '=', $sc_id);
            })
            ->when(
                null === $ve_id && null === $so_id,
                function (Builder $query) {
                    $query->orderByDesc('va.va_id');
                },
                function (Builder $query) {
                    $query->orderBy('va.va_id');
                }
            )
            ->select('va.*', 'sc.sc_name', 've.plate_no', 'cu.contact_name', 'cu.contact_phone', '_vm.brand_name', '_vm.model_name')
            ->addSelect(
                DB::raw(VaClaimStatus::toCaseSQL()),
                DB::raw(VaRepairStatus::toCaseSQL()),
                DB::raw(VaSettlementStatus::toCaseSQL()),
                DB::raw(VaPickupStatus::toCaseSQL()),
                DB::raw(VaSettlementMethod::toCaseSQL()),
                DB::raw(VaManagedVehicle::toCaseSQL()),
                DB::raw('EXTRACT(EPOCH FROM factory_in_dt - factory_out_dt) / 86400.0 as va_interval_day'),
                DB::raw("to_char(accident_dt, 'YYYY-MM-DD HH24:MI') as accident_dt_"),
                DB::raw("to_char(factory_in_dt, 'YYYY-MM-DD HH24:MI') as factory_in_dt_"),
                DB::raw("to_char(factory_out_dt, 'YYYY-MM-DD HH24:MI') as factory_out_dt_"),
            )
        ;
    }

    public static function importColumns(): array
    {
        return [
            'plate_no'           => [RentalVehicleAccident::class, 'plate_no'],
            'accident_location'  => [RentalVehicleAccident::class, 'accident_location'],
            'accident_dt'        => [RentalVehicleAccident::class, 'accident_dt'],
            'responsible_party'  => [RentalVehicleAccident::class, 'responsible_party'],
            'claim_status'       => [RentalVehicleAccident::class, 'claim_status'],
            'self_amount'        => [RentalVehicleAccident::class, 'self_amount'],
            'third_party_amount' => [RentalVehicleAccident::class, 'third_party_amount'],
            'insurance_company'  => [RentalVehicleAccident::class, 'insurance_company'],
            'va_description'     => [RentalVehicleAccident::class, 'va_description'],
            'factory_in_dt'      => [RentalVehicleAccident::class, 'factory_in_dt'],
            'sc_name'            => [RentalServiceCenter::class, 'sc_name'],
            'repair_content'     => [RentalVehicleAccident::class, 'repair_content'],
            'repair_status'      => [RentalVehicleAccident::class, 'repair_status'],
            'factory_out_dt'     => [RentalVehicleAccident::class, 'factory_out_dt'],
            'settlement_status'  => [RentalVehicleAccident::class, 'settlement_status'],
            'pickup_status'      => [RentalVehicleAccident::class, 'pickup_status'],
            'settlement_method'  => [RentalVehicleAccident::class, 'settlement_method'],
            'managed_vehicle'    => [RentalVehicleAccident::class, 'managed_vehicle'],
            'va_remark'          => [RentalVehicleAccident::class, 'va_remark'],
        ];
    }

    public static function importBeforeValidateDo(): \Closure
    {
        return function (&$item) {
            $item['ve_id']             = RentalVehicle::plateNoKv($item['plate_no'] ?? null);
            $item['sc_id']             = RentalServiceCenter::nameKv($item['sc_name'] ?? null);
            $item['claim_status']      = VaClaimStatus::searchValue($item['claim_status'] ?? null);
            $item['repair_status']     = VaRepairStatus::searchValue($item['repair_status'] ?? null);
            $item['settlement_status'] = VaSettlementStatus::searchValue($item['settlement_status'] ?? null);
            $item['pickup_status']     = VaPickupStatus::searchValue($item['pickup_status'] ?? null);
            $item['settlement_method'] = VaSettlementMethod::searchValue($item['settlement_method'] ?? null);
            $item['managed_vehicle']   = VaManagedVehicle::searchValue($item['managed_vehicle'] ?? null);
        };
    }

    public static function importValidatorRule(array $item, array $fieldAttributes): void
    {
        $rules = [
            've_id'              => ['required', 'integer'],
            'so_id'              => ['nullable', 'integer'],
            'accident_location'  => ['nullable', 'string', 'max:255'],
            'accident_dt'        => ['required', 'date'],
            'responsible_party'  => ['nullable', 'string', 'max:255'],
            'claim_status'       => ['nullable', 'string', Rule::in(VaClaimStatus::label_keys())],
            'self_amount'        => ['nullable', 'numeric'],
            'third_party_amount' => ['nullable', 'numeric'],
            'insurance_company'  => ['nullable', 'string', 'max:100'],
            'va_description'     => ['nullable', 'string'],
            'factory_in_dt'      => ['nullable', 'date'],
            'sc_id'              => ['required', 'integer'],
            'repair_content'     => ['nullable', 'string'],
            'repair_status'      => ['nullable', 'string', Rule::in(VaRepairStatus::label_keys())],
            'factory_out_dt'     => ['nullable', 'date'],
            'settlement_status'  => ['nullable', 'string', Rule::in(VaSettlementStatus::label_keys())],
            'pickup_status'      => ['nullable', 'string', Rule::in(VaPickupStatus::label_keys())],
            'settlement_method'  => ['nullable', 'string', Rule::in(VaSettlementMethod::label_keys())],
            'managed_vehicle'    => ['nullable', 'string', Rule::in(VaManagedVehicle::label_keys())],
            'va_remark'          => ['nullable', 'string'],
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
            $rentalVehicleAccident = RentalVehicleAccident::query()->create($input);
        };
    }

    public static function options(?\Closure $where = null): array
    {
        return [];
    }

    protected function claimStatusLabel(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->getAttribute('claim_status')?->label
        );
    }

    protected function repairStatusLabel(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->getAttribute('repair_status')?->label
        );
    }

    protected function settlementStatusLabel(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->getAttribute('settlement_status')?->label
        );
    }

    protected function pickupStatusLabel(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->getAttribute('pickup_status')?->label
        );
    }

    protected function settlementMethodLabel(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->getAttribute('settlement_method')?->label
        );
    }

    protected function managedVehicleLabel(): Attribute
    {
        return Attribute::make(
            get : fn () => $this->getAttribute('managed_vehicle')?->label
        );
    }

    protected function additionalPhotos(): Attribute
    {
        return $this->uploadFileArray();
    }

    protected function accidentInfo(): Attribute
    {
        return $this->arrayInfo();
    }
}
