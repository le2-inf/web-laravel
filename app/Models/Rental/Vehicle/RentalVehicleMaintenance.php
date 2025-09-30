<?php

namespace App\Models\Rental\Vehicle;

use App\Attributes\ClassName;
use App\Attributes\ColumnDesc;
use App\Attributes\ColumnType;
use App\Enum\Rental\RpIsValid;
use App\Enum\Rental\RpPayStatus;
use App\Enum\Rental\RpPtId;
use App\Enum\Vehicle\VmCustodyVehicle;
use App\Enum\Vehicle\VmPickupStatus;
use App\Enum\Vehicle\VmSettlementMethod;
use App\Enum\Vehicle\VmSettlementStatus;
use App\Models\ModelTrait;
use App\Models\Rental\ImportTrait;
use App\Models\Rental\Payment\RentalPayment;
use App\Models\Rental\Payment\RentalPaymentType;
use App\Models\Rental\Sale\RentalSaleOrder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

#[ClassName('车辆保养', '记录')]
#[ColumnDesc('vm_id')]
#[ColumnDesc('ve_id')]
#[ColumnDesc('plate_no', required: true)]
#[ColumnDesc('so_id')]
#[ColumnDesc('entry_datetime', required: true, type: ColumnType::DATETIME)]
#[ColumnDesc('maintenance_amount', required: true)]
#[ColumnDesc('entry_mileage')]
#[ColumnDesc('next_maintenance_date', type: ColumnType::DATE)]
#[ColumnDesc('departure_datetime', type: ColumnType::DATETIME)]
#[ColumnDesc('maintenance_mileage')]
#[ColumnDesc('settlement_status', enum_class: VmSettlementStatus::class)]
#[ColumnDesc('pickup_status', enum_class: VmPickupStatus::class)]
#[ColumnDesc('settlement_method', enum_class: VmSettlementMethod::class)]
#[ColumnDesc('custody_vehicle', enum_class: VmCustodyVehicle::class)]
#[ColumnDesc('vm_remark')]
#[ColumnDesc('additional_photos')]
#[ColumnDesc('maintenance_info')]
/**
 * @property int                 $vm_id                   保养序号
 * @property int                 $ve_id                   车辆序号
 * @property null|int            $so_id                   订单序号
 * @property int                 $sc_id                   修理厂序号
 * @property Carbon              $entry_datetime          进厂日时
 * @property float               $maintenance_amount      保养金额;元
 * @property int                 $entry_mileage           进厂公里数
 * @property null|Carbon         $next_maintenance_date   下次保养日期
 * @property Carbon              $departure_datetime      出厂日时
 * @property null|int            $maintenance_mileage     保养里程;公里
 * @property null|string         $settlement_status       结算状态
 * @property null|string         $pickup_status           提车状态
 * @property null|string         $settlement_method       结算方式
 * @property null|string         $custody_vehicle         代管车辆
 * @property null|string         $vm_remark               保养备注
 * @property null|mixed          $additional_photos       附加照片;JSON 格式存储图片路径或链接
 * @property null|mixed          $maintenance_info        车辆保养信息
 * @property null|string         $settlement_status_label 结算状态
 * @property null|string         $pickup_status_label     提车状态
 * @property null|string         $settlement_method_label 结算方式
 * @property null|string         $custody_vehicle_label   代管车辆
 * @property RentalVehicle       $RentalVehicle
 * @property RentalSaleOrder     $RentalSaleOrder
 * @property RentalPayment       $RentalPayment
 * @property null|int            $add_should_pay
 * @property RentalServiceCenter $RentalServiceCenter
 */
class RentalVehicleMaintenance extends Model
{
    use ModelTrait;

    use ImportTrait;

    protected $primaryKey = 'vm_id';

    protected $guarded = ['vm_id'];

    protected $attributes = [];

    protected $casts = [
        'entry_datetime'     => 'datetime:Y-m-d H:i',
        'departure_datetime' => 'datetime:Y-m-d H:i',
        'maintenance_amount' => 'decimal:2',
        'settlement_status'  => VmSettlementStatus::class,
        'pickup_status'      => VmPickupStatus::class,
        'settlement_method'  => VmSettlementMethod::class,
        'custody_vehicle'    => VmCustodyVehicle::class,
    ];

    protected $appends = [
        'settlement_status_label',
        'pickup_status_label',
        'settlement_method_label',
        'custody_vehicle_label',
    ];

    public function RentalVehicle(): BelongsTo
    {
        return $this->belongsTo(RentalVehicle::class, 've_id', 've_id');
    }

    public function RentalSaleOrder(): BelongsTo
    {
        return $this->belongsTo(RentalSaleOrder::class, 'so_id', 'so_id');
    }

    public function RentalPayment(): HasOne
    {
        $pt_id = RpPtId::MAINTENANCE_FEE;

        return $this->hasOne(RentalPayment::class, 'vm_id', 'vm_id')
            ->where('pt_id', '=', $pt_id)->where('is_valid', '=', RpIsValid::VALID)
            ->withDefault(
                [
                    'pt_id'               => $pt_id,
                    'rental_payment_type' => RentalPaymentType::query()->where('pt_id', '=', $pt_id)->first(),
                    'should_pay_date'     => now()->format('Y-m-d'),
                    'pay_status'          => RpPayStatus::UNPAID,
                ]
            )
        ;
    }

    public function RentalServiceCenter(): BelongsTo
    {
        return $this->belongsTo(RentalServiceCenter::class, 'sc_id', 'sc_id');
    }

    public static function indexQuery(array $search = []): Builder
    {
        $ve_id = $search['ve_id'] ?? null;
        $cu_id = $search['cu_id'] ?? null;
        $sc_id = $search['sc_id'] ?? null;

        return DB::query()
            ->from('rental_vehicle_maintenances', 'vm')
            ->leftJoin('rental_service_centers as sc', 'sc.sc_id', '=', 'vm.sc_id')
            ->leftJoin('rental_vehicles as ve', 've.ve_id', '=', 'vm.ve_id')
            ->leftJoin('rental_vehicle_models as _vm', '_vm.vm_id', '=', 've.vm_id')
            ->leftJoin('rental_sale_orders as so', 'so.so_id', '=', 'vm.so_id')
            ->leftJoin('rental_customers as cu', 'cu.cu_id', '=', 'so.cu_id')
            ->when($ve_id, function (Builder $query) use ($ve_id) {
                $query->where('vm.ve_id', '=', $ve_id);
            })
            ->when($cu_id, function (Builder $query) use ($cu_id) {
                $query->where('so.cu_id', '=', $cu_id);
            })
            ->when($sc_id, function (Builder $query) use ($sc_id) {
                $query->where('vm.sc_id', '=', $sc_id);
            })
            ->when(
                null === $ve_id && null === $cu_id,
                function (Builder $query) {
                    $query->orderByDesc('vm.vm_id');
                },
                function (Builder $query) {
                    $query->orderBy('vm.vm_id');
                }
            )
            ->select('vm.*', 'sc.sc_name', 've.plate_no', 'cu.contact_name', 'cu.contact_phone', '_vm.brand_name', '_vm.model_name')
            ->addSelect(
                DB::raw(VmCustodyVehicle::toCaseSQL()),
                DB::raw(VmPickupStatus::toCaseSQL()),
                DB::raw(VmSettlementMethod::toCaseSQL()),
                DB::raw(VmSettlementStatus::toCaseSQL()),
                DB::raw("to_char(entry_datetime, 'YYYY-MM-DD HH24:MI') as entry_datetime_"),
                DB::raw("to_char(departure_datetime, 'YYYY-MM-DD HH24:MI') as departure_datetime_"),
            )
            ->addSelect(DB::raw('EXTRACT(EPOCH FROM entry_datetime - departure_datetime) / 86400.0 as vm_interval_day'))
        ;
    }

    public static function importColumns(): array
    {
        return [
            'plate_no'              => [RentalVehicleMaintenance::class, 'plate_no'],
            'sc_name'               => [RentalServiceCenter::class, 'sc_name'],
            'entry_datetime'        => [RentalVehicleMaintenance::class, 'entry_datetime'],
            'maintenance_amount'    => [RentalVehicleMaintenance::class, 'maintenance_amount'],
            'entry_mileage'         => [RentalVehicleMaintenance::class, 'entry_mileage'],
            'next_maintenance_date' => [RentalVehicleMaintenance::class, 'next_maintenance_date'],
            'departure_datetime'    => [RentalVehicleMaintenance::class, 'departure_datetime'],
            'maintenance_mileage'   => [RentalVehicleMaintenance::class, 'maintenance_mileage'],
            'settlement_status'     => [RentalVehicleMaintenance::class, 'settlement_status'],
            'pickup_status'         => [RentalVehicleMaintenance::class, 'pickup_status'],
            'settlement_method'     => [RentalVehicleMaintenance::class, 'settlement_method'],
            'custody_vehicle'       => [RentalVehicleMaintenance::class, 'custody_vehicle'],
            'vm_remark'             => [RentalVehicleMaintenance::class, 'vm_remark'],
        ];
    }

    public static function importBeforeValidateDo(): \Closure
    {
        return function (&$item) {
            $item['ve_id']             = RentalVehicle::plateNoKv($item['plate_no'] ?? null);
            $item['sc_id']             = RentalServiceCenter::nameKv($item['sc_name'] ?? null);
            $item['settlement_status'] = VmSettlementStatus::searchValue($item['settlement_status'] ?? null);
            $item['pickup_status']     = VmPickupStatus::searchValue($item['pickup_status'] ?? null);
            $item['settlement_method'] = VmSettlementMethod::searchValue($item['settlement_method'] ?? null);
            $item['custody_vehicle']   = VmCustodyVehicle::searchValue($item['custody_vehicle'] ?? null);
        };
    }

    public static function importValidatorRule(array $item, array $fieldAttributes): void
    {
        $rules = [
            've_id'               => ['required', 'integer'],
            'so_id'               => ['nullable', 'integer'],
            'entry_datetime'      => ['required', 'date'],
            'entry_mileage'       => ['nullable', 'integer', 'min:0'],
            'maintenance_mileage' => ['nullable', 'integer', 'min:0'],
            'maintenance_amount'  => ['nullable', 'decimal:0,2', 'gte:0'],
            'sc_id'               => ['required', 'integer'],
            'departure_datetime'  => ['nullable', 'date'],
            'settlement_status'   => ['required', 'string', Rule::in(VmSettlementStatus::label_keys())],
            'pickup_status'       => ['required', 'string', Rule::in(VmPickupStatus::label_keys())],
            'settlement_method'   => ['required', 'string', Rule::in(VmSettlementMethod::label_keys())],
            'custody_vehicle'     => ['required', 'string', Rule::in(VmCustodyVehicle::label_keys())],
            'vm_remark'           => ['nullable', 'string'],
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
            $rentalVehicleMaintenance = RentalVehicleMaintenance::query()->create($input);
        };
    }

    public static function options(?\Closure $where = null): array
    {
        return [];
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

    protected function custodyVehicleLabel(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->getAttribute('custody_vehicle')?->label
        );
    }

    protected function additionalPhotos(): Attribute
    {
        return $this->uploadFileArray();
    }

    protected function maintenanceInfo(): Attribute
    {
        return $this->arrayInfo();
    }
}
