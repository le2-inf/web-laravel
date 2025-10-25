<?php

namespace App\Models\Delivery;

use App\Enum\Delivery\DcDcKey;
use App\Enum\Delivery\DcDcProvider;
use App\Enum\Delivery\DcDcStatus;
use App\Enum\Delivery\DlSendStatus;
use App\Enum\Rental\DtDtStatus;
use App\Enum\Rental\RpIsValid;
use App\Enum\Rental\RpPayStatus;
use App\Enum\Rental\SoOrderStatus;
use App\Enum\Vehicle\VvProcessStatus;
use App\Models\ModelTrait;
use App\Models\Rental\Payment\RentalPayment;
use App\Models\Rental\Sale\RentalSaleOrder;
use App\Models\Rental\Vehicle\RentalVehicleInsurance;
use App\Models\Rental\Vehicle\RentalVehicleSchedule;
use App\Models\Rental\Vehicle\RentalVehicleViolation;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * @property int                 $dc_id       消息类型ID
 * @property DcDcKey|string      $dc_key      消息类型KEY;不重复
 * @property string              $dc_title    消息类型标题
 * @property string              $dc_template 消息类型模板
 * @property int                 $dc_tn       消息类型触发日期; =T-N
 * @property DcDcProvider|string $dc_provider 消息类型发送方式
 * @property DcDcStatus|int      $dc_status   消息类型状态
 */
class RentalDeliveryChannel extends Model
{
    use ModelTrait;

    protected $primaryKey = 'dc_id';

    protected $guarded = ['dc_id'];

    protected $casts = [
        'dc_key'      => DcDcKey::class,
        'dc_provider' => DcDcProvider::class,
        'dc_status'   => DcDcStatus::class,
    ];

    protected $attributes = [];

    protected $appends = [
        'dc_key_label',
        'dc_provider_label',
        'dc_status_label',
    ];

    public static function indexQuery(array $search = []): Builder
    {
        return DB::query()
            ->from('rental_delivery_channels', 'dc')
            ->orderByDesc('dc.dc_id')
            ->select('dc.*')
            ->addSelect(
                DB::raw(DcDcKey::toCaseSQL()),
                DB::raw(DcDcProvider::toCaseSQL()),
                DB::raw(DcDcStatus::toCaseSQL()),
                DB::raw(" ('T-' || dc.dc_tn ) as dc_tn_label"),
            )
        ;
    }

    public static function options(?\Closure $where = null): array
    {
        $key = preg_replace('/^.*\\\/', '', get_called_class()).'Options';

        $value = DB::query()
            ->from('rental_doc_tpls', 'dt')
            ->where('dt.dt_status', '=', DtDtStatus::ENABLED)
            ->when($where, $where)
            ->orderBy('dt.dt_id', 'desc')
            ->select(DB::raw("concat(dt_file_type,'|',dt_name,'→docx') as text,concat(dt.dt_id,'|docx') as value"))
            ->get()->toArray()
        ;

        return [$key => $value];
    }

    public function DeliveryLogs(): HasMany
    {
        return $this->hasMany(RentalDeliveryLog::class);
    }

    public function make_payment(): void
    {
        $from = Carbon::now()->addDays($this->dc_tn)->subDays(3)->format('Y-m-d');
        $to   = Carbon::now()->addDays($this->dc_tn)->format('Y-m-d');

        RentalPayment::query()
            ->where('pay_status', '=', RpPayStatus::UNPAID)
            ->where('is_valid', '=', RpIsValid::VALID)
            ->whereBetween('should_pay_date', [$from, $to])
            ->whereHas('RentalSaleOrder', function (\Illuminate\Database\Eloquent\Builder $query) {
                $query->where('order_status', '=', SoOrderStatus::SIGNED);
            })
            ->orderby('rp_id')
            ->with('RentalSaleOrder', 'RentalSaleOrder.RentalVehicle', 'RentalSaleOrder.RentalSaleOrderExt')
            ->chunk(100, function ($payments) {
                /** @var RentalPayment $payment */
                foreach ($payments as $payment) {
                    $soe_wecom_group_url = $payment->RentalSaleOrder?->RentalSaleOrderExt?->soe_wecom_group_url;
                    if (!$soe_wecom_group_url) {
                        Log::channel('console')->info("因未设置soe_wecom_group_url,跳过{$this->dc_key}类型通知。", [$payment->RentalSaleOrder->so_full_label]);

                        continue;
                    }

                    $dc_key = $this->dc_key;
                    $dc_tn  = $this->dc_tn;
                    $dl_key = $payment->rp_id;
                    $exist  = RentalDeliveryLog::query()
                        ->where('dc_key', '=', $dc_key)
                        ->where('dc_tn', '=', $dc_tn)
                        ->where('dl_key', '=', $dl_key)
                        ->exists()
                    ;
                    if ($exist) {
                        continue;
                    }

                    RentalDeliveryLog::query()->insert([
                        'dc_key'         => $dc_key,
                        'dc_tn'          => $dc_tn,
                        'dl_key'         => $dl_key,
                        'rp_id'          => $payment->rp_id,
                        'recipients_url' => $soe_wecom_group_url,
                        'content_title'  => $this->dc_title,
                        'content_body'   => Blade::render($this->dc_template, $payment),
                        'send_status'    => DlSendStatus::ST_PENDING,
                        'send_attempt'   => '0',
                        'scheduled_for'  => now(),
                    ]);
                }
            })
        ;
    }

    public function make_settlement(): void
    {
        $from = Carbon::now()->addDays($this->dc_tn)->subDays(3)->format('Y-m-d');
        $to   = Carbon::now()->addDays($this->dc_tn)->format('Y-m-d');

        RentalSaleOrder::query()
            ->where('order_status', '=', SoOrderStatus::SIGNED)
            ->whereBetween('rental_end', [$from, $to])
            ->orderby('so_id')
            ->with(['RentalVehicle', 'RentalSaleOrderExt'])
            ->chunk(100, function ($saleOrders) {
                /** @var RentalSaleOrder $saleOrder */
                foreach ($saleOrders as $saleOrder) {
                    $soe_wecom_group_url = $saleOrder->RentalSaleOrderExt?->soe_wecom_group_url;
                    if (!$soe_wecom_group_url) {
                        Log::channel('console')->info("因未设置soe_wecom_group_url,跳过{$this->dc_key}类型通知。", [$saleOrder->so_full_label]);

                        continue;
                    }

                    $dc_key = $this->dc_key;
                    $dc_tn  = $this->dc_tn;
                    $dl_key = $saleOrder->so_id;

                    $exist = RentalDeliveryLog::query()
                        ->where('dc_key', '=', $dc_key)
                        ->where('dc_tn', '=', $dc_tn)
                        ->where('dl_key', '=', $dl_key)
                        ->exists()
                    ;
                    if ($exist) {
                        continue;
                    }

                    RentalDeliveryLog::query()->insert([
                        'dc_key'         => $dc_key,
                        'dc_tn'          => $dc_tn,
                        'dl_key'         => $dl_key,
                        'so_id'          => $saleOrder->so_id,
                        'recipients_url' => $soe_wecom_group_url,
                        'content_title'  => $this->dc_title,
                        'content_body'   => Blade::render($this->dc_template, $saleOrder),
                        'send_status'    => DlSendStatus::ST_PENDING,
                        'send_attempt'   => '0',
                        'scheduled_for'  => now(),
                    ]);
                }
            })
        ;
    }

    public function make_vehicle_insurance(): void
    {
        $from = Carbon::now()->addDays($this->dc_tn)->subDays(3)->format('Y-m-d');
        $to   = Carbon::now()->addDays($this->dc_tn)->format('Y-m-d');

        RentalVehicleInsurance::query()
            ->whereBetween('compulsory_end_date', [$from, $to])
            ->orderBy('vi_id')
            ->with(['RentalVehicle.VehicleManager.AdminExt'])
            ->chunk(100, function ($rentalVehicleInsurances) {
                /** @var array<RentalVehicleInsurance> $rentalVehicleInsurances */
                foreach ($rentalVehicleInsurances as $rentalVehicleInsurance) {
                    $wecom_name = $rentalVehicleInsurance?->RentalVehicle?->VehicleManager?->AdminExt?->wecom_name;
                    if (!$wecom_name) {
                        Log::channel('console')->info("wecom_name,跳过{$this->dc_key}类型通知。", [$rentalVehicleInsurance?->RentalVehicle?->plate_no, $rentalVehicleInsurance?->RentalVehicle?->VehicleManager?->name]);

                        continue;
                    }

                    $dc_key = $this->dc_key;
                    $dc_tn  = $this->dc_tn;
                    $dl_key = $rentalVehicleInsurance->compulsory_plate_no.'|'.$rentalVehicleInsurance->compulsory_end_date;

                    $exist = RentalDeliveryLog::query()
                        ->where('dc_key', '=', $dc_key)
                        ->where('dc_tn', '=', $dc_tn)
                        ->where('dl_key', '=', $dl_key)
                        ->exists()
                    ;
                    if ($exist) {
                        continue;
                    }

                    RentalDeliveryLog::query()->insert([
                        'dc_key'        => $dc_key,
                        'dc_tn'         => $dc_tn,
                        'dl_key'        => $dl_key,
                        'vi_id'         => $rentalVehicleInsurance->vi_id,
                        'recipients'    => json_encode([$wecom_name]),
                        'content_title' => $this->dc_title,
                        'content_body'  => Blade::render($this->dc_template, $rentalVehicleInsurance),
                        'send_status'   => DlSendStatus::ST_PENDING,
                        'send_attempt'  => '0',
                        'scheduled_for' => now(),
                    ]);
                }
            })
        ;
    }

    public function make_vehicle_schedule(): void
    {
        $from = Carbon::now()->addDays($this->dc_tn)->subDays(3)->format('Y-m-d');
        $to   = Carbon::now()->addDays($this->dc_tn)->format('Y-m-d');

        RentalVehicleSchedule::query()
            ->whereBetween('next_inspection_date', [$from, $to])
            ->orderBy('vs_id')
            ->with(['RentalVehicle'])
            ->chunk(100, function ($rentalVehicleSchedules) {
                /** @var RentalVehicleSchedule $rentalVehicleSchedule */
                foreach ($rentalVehicleSchedules as $rentalVehicleSchedule) {
                    $wecom_name = $rentalVehicleSchedule?->RentalVehicle?->VehicleManager?->AdminExt?->wecom_name;
                    if (!$wecom_name) {
                        Log::channel('console')->info("wecom_name,跳过{$this->dc_key}类型通知。", [$rentalVehicleSchedule?->RentalVehicle?->plate_no, $rentalVehicleSchedule?->RentalVehicle?->VehicleManager?->name]);

                        continue;
                    }

                    $dc_key = $this->dc_key;
                    $dc_tn  = $this->dc_tn;
                    $dl_key = $rentalVehicleSchedule->inspection_type.'|'.$rentalVehicleSchedule->RentalVehicle->plate_no.'|'.$rentalVehicleSchedule->next_inspection_date->format('Y-m-d');

                    $exist = RentalDeliveryLog::query()
                        ->where('dc_key', '=', $dc_key)
                        ->where('dc_tn', '=', $dc_tn)
                        ->where('dl_key', '=', $dl_key)
                        ->exists()
                    ;
                    if ($exist) {
                        continue;
                    }
                    RentalDeliveryLog::query()->insert([
                        'dc_key'        => $dc_key,
                        'dc_tn'         => $dc_tn,
                        'dl_key'        => $dl_key,
                        'vs_id'         => $rentalVehicleSchedule->vs_id,
                        'recipients'    => json_encode([$wecom_name]),
                        'content_title' => $this->dc_title,
                        'content_body'  => Blade::render($this->dc_template, $rentalVehicleSchedule),
                        'send_status'   => DlSendStatus::ST_PENDING,
                        'send_attempt'  => '0',
                        'scheduled_for' => now(),
                    ]);
                }
            })
        ;
    }

    public function make_vehicle_violation(): void
    {
        $from = Carbon::now()->subDays(3000)->format('Y-m-d');
        $to   = Carbon::now()->format('Y-m-d');

        RentalVehicleViolation::query()
            ->where('process_status', '=', VvProcessStatus::UNPROCESSED)
            ->whereBetween('violation_datetime', [$from, $to])
            ->whereHas('RentalVehicleUsage.RentalSaleOrder', function (\Illuminate\Database\Eloquent\Builder $q) {
                $q->where('so_id', '>', '0');
            })
            ->orderBy('vv_id')
            ->with(['RentalVehicleUsage.RentalSaleOrder.RentalSaleOrderExt'])
            ->chunk(100, function ($rentalVehicleViolations) {
                /** @var RentalVehicleViolation $rentalVehicleViolation */
                foreach ($rentalVehicleViolations as $rentalVehicleViolation) {
                    $soe_wecom_group_url = $rentalVehicleViolation?->RentalVehicleUsage?->RentalSaleOrder?->RentalSaleOrderExt?->soe_wecom_group_url;
                    if (!$soe_wecom_group_url) {
                        Log::channel('console')->info("因未设置soe_wecom_group_url,跳过{$this->dc_key}类型通知。", [$rentalVehicleViolation?->RentalVehicleUsage?->RentalSaleOrder->so_full_label]);

                        continue;
                    }

                    $dc_key = $this->dc_key;
                    $dc_tn  = $this->dc_tn;
                    $dl_key = $rentalVehicleViolation->plate_no.'|'.$rentalVehicleViolation->violation_datetime;

                    $exist = RentalDeliveryLog::query()
                        ->where('dc_key', '=', $dc_key)
                        ->where('dc_tn', '=', $dc_tn)
                        ->where('dl_key', '=', $dl_key)
                        ->exists()
                    ;

                    if ($exist) {
                        continue;
                    }

                    RentalDeliveryLog::query()->insert([
                        'dc_key'         => $dc_key,
                        'dc_tn'          => $dc_tn,
                        'dl_key'         => $dl_key,
                        'vv_id'          => $rentalVehicleViolation->vv_id,
                        'recipients_url' => $soe_wecom_group_url,
                        'content_title'  => $this->dc_title,
                        'content_body'   => Blade::render($this->dc_template, $rentalVehicleViolation),
                        'send_status'    => DlSendStatus::ST_PENDING,
                        'send_attempt'   => '0',
                        'scheduled_for'  => now(),
                    ]);
                }
            })
        ;
    }

    protected function dcKeyLabel(): Attribute
    {
        return Attribute::make(
            get : fn () => $this->getAttribute('dc_key')?->label
        );
    }

    protected function dcProviderLabel(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->getAttribute('dc_provider')?->label ?? null
        );
    }

    protected function dcStatusLabel(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $this->getAttribute('dc_status')?->label ?? null
        );
    }
}
