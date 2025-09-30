<?php

namespace App\Models\Rental\Vehicle;

use App\Attributes\ClassName;
use App\Attributes\ColumnDesc;
use App\Attributes\ColumnType;
use App\Enum\Vehicle\ViIsCompanyBorne;
use App\Models\ModelTrait;
use App\Models\Rental\ImportTrait;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

#[ClassName('车辆保险单', '信息')]
#[ColumnDesc('vi_id')]
#[ColumnDesc('ve_id', required: true)]
#[ColumnDesc('plate_no', required: true)]
#[ColumnDesc('compulsory_policy_file')]
#[ColumnDesc('compulsory_policy_photos')]
#[ColumnDesc('compulsory_policy_addendum_file')]
#[ColumnDesc('compulsory_plate_no')]
#[ColumnDesc('compulsory_policy_number')]
#[ColumnDesc('compulsory_start_date', type: ColumnType::DATE)]
#[ColumnDesc('compulsory_end_date', type: ColumnType::DATE)]
#[ColumnDesc('compulsory_premium')]
#[ColumnDesc('compulsory_insured_company')]
#[ColumnDesc('compulsory_org_code')]
#[ColumnDesc('compulsory_insurance_company')]
#[ColumnDesc('carrier_liability_policy_file')]
#[ColumnDesc('carrier_liability_policy_photos')]
#[ColumnDesc('carrier_liability_plate_no')]
#[ColumnDesc('carrier_liability_policy_number')]
#[ColumnDesc('carrier_liability_start_date', type: ColumnType::DATE)]
#[ColumnDesc('carrier_liability_end_date', type: ColumnType::DATE)]
#[ColumnDesc('carrier_liability_premium')]
#[ColumnDesc('carrier_liability_insured_company')]
#[ColumnDesc('carrier_liability_org_code')]
#[ColumnDesc('carrier_liability_insurance_company')]
#[ColumnDesc('commercial_policy_file')]
#[ColumnDesc('commercial_policy_photos')]
#[ColumnDesc('commercial_policy_addendum_file')]
#[ColumnDesc('commercial_plate_no')]
#[ColumnDesc('commercial_policy_number')]
#[ColumnDesc('commercial_start_date', type: ColumnType::DATE)]
#[ColumnDesc('commercial_end_date', type: ColumnType::DATE)]
#[ColumnDesc('commercial_premium')]
#[ColumnDesc('commercial_insured_company')]
#[ColumnDesc('commercial_org_code')]
#[ColumnDesc('commercial_insurance_company')]
#[ColumnDesc('is_company_borne', desc: '输入0、1')]
#[ColumnDesc('vi_remark')]
/**
 * @property int         $vi_id                               保险单序号
 * @property int         $ve_id                               车辆序号
 * @property string      $plate_no                            车牌号
 * @property null|mixed  $compulsory_policy_file              交强险保单文件路径
 * @property null|mixed  $compulsory_policy_photos            交强险保单照片路径
 * @property null|mixed  $compulsory_policy_addendum_file     交强险批单文件路径
 * @property null|string $compulsory_plate_no
 * @property null|string $compulsory_policy_number            交强险保单号
 * @property null|Carbon $compulsory_start_date               交强险开始日期
 * @property null|Carbon $compulsory_end_date                 交强险结束日期
 * @property null|float  $compulsory_premium                  交强险保费
 * @property null|string $compulsory_insured_company          交强险被保险公司
 * @property null|string $compulsory_org_code                 交强险组织机构代码
 * @property null|string $compulsory_insurance_company        交强险保险公司
 * @property null|mixed  $carrier_liability_policy_file       承运人责任险保单文件路径
 * @property null|mixed  $carrier_liability_policy_photos     承运人责任险保单照片路径
 * @property null|string $carrier_liability_plate_no
 * @property null|string $carrier_liability_policy_number     承运人责任险保单号
 * @property null|Carbon $carrier_liability_start_date        承运人责任险开始日期
 * @property null|Carbon $carrier_liability_end_date          承运人责任险结束日期
 * @property null|float  $carrier_liability_premium           承运人责任险保费
 * @property null|string $carrier_liability_insured_company   承运人责任险被保险公司
 * @property null|string $carrier_liability_org_code          承运人责任险组织机构代码
 * @property null|string $carrier_liability_insurance_company 承运人责任险保险公司
 * @property null|mixed  $commercial_policy_file              商业险保单文件路径
 * @property null|mixed  $commercial_policy_photos            商业险保单照片路径
 * @property null|mixed  $commercial_policy_addendum_file     商业险批单文件路径
 * @property null|string $commercial_plate_no
 * @property null|string $commercial_policy_number            商业险保单号
 * @property null|Carbon $commercial_start_date               商业险开始日期
 * @property null|Carbon $commercial_end_date                 商业险结束日期
 * @property null|float  $commercial_premium                  商业险保费
 * @property null|string $commercial_insured_company          商业险被保险公司
 * @property null|string $commercial_org_code                 商业险组织机构代码
 * @property null|string $commercial_insurance_company        商业险保险公司
 * @property null|int    $is_company_borne                    是否公司承担;1表示是，0表示否
 * @property null|string $vi_remark                           保险单备注
 */
class RentalVehicleInsurance extends Model
{
    use ModelTrait;

    use ImportTrait;

    protected $primaryKey = 'vi_id';

    protected $guarded = ['vi_id'];

    protected $casts = [
        'is_company_borne' => ViIsCompanyBorne::class,
    ];

    protected $appends = [];

    protected $attributes = [];

    public function RentalVehicle(): BelongsTo
    {
        return $this->belongsTo(RentalVehicle::class, 've_id', 've_id')->with('RentalVehicleModel');
    }

    public static function indexQuery(array $search = []): Builder
    {
        $ve_id = $search['ve_id'] ?? null;

        return DB::query()
            ->from('rental_vehicle_insurances', 'vi')
            ->when(null === $ve_id, function (Builder $query) {
                return $query->joinSub(
                    // 直接在 joinSub 中定义子查询
                    DB::table('rental_vehicle_insurances')
                        ->select('ve_id', DB::raw('MAX(compulsory_start_date) as max_compulsory_start_date'))
                        ->groupBy('ve_id'),
                    'p2',
                    function ($join) {
                        $join->on('vi.ve_id', '=', 'p2.ve_id')
                            ->on('vi.compulsory_start_date', '=', 'p2.max_compulsory_start_date')
                        ;
                    }
                );
            })
            ->leftJoin('rental_vehicles as ve', 've.ve_id', '=', 'vi.ve_id')
            ->leftJoin('rental_vehicle_models as vm', 'vm.vm_id', '=', 've.vm_id')
            ->when($ve_id, function (Builder $query) use ($ve_id) {
                $query->where('vi.ve_id', '=', $ve_id);
            })
            ->when(
                null === $ve_id,
                function (Builder $query) {
                    $query->orderByDesc('vi.vi_id');
                },
                function (Builder $query) {
                    $query->orderBy('vi.vi_id');
                }
            )
            ->select('vi.*', 've.plate_no', 'vm.brand_name', 'vm.model_name')
            ->when(null === $ve_id, function (Builder $query) {
                $query->addSelect(DB::raw('CAST(EXTRACT(EPOCH FROM now() - vi.compulsory_end_date) / 86400.0 AS integer ) as compulsory_interval_day'));
                $query->addSelect(DB::raw('CAST(EXTRACT(EPOCH FROM now() - vi.commercial_end_date) / 86400.0 AS integer ) as commercial_interval_day'));
                $query->addSelect(DB::raw('CAST(EXTRACT(EPOCH FROM now() - vi.carrier_liability_end_date) / 86400.0 AS integer ) as carrier_liability_interval_day'));
            })
        ;
    }

    public static function importColumns(): array
    {
        return [
            'plate_no' => [RentalVehicleInsurance::class, 'plate_no'],
            //                'compulsory_plate_no',
            'compulsory_policy_number'     => [RentalVehicleInsurance::class, 'compulsory_policy_number'],
            'compulsory_start_date'        => [RentalVehicleInsurance::class, 'compulsory_start_date'],
            'compulsory_end_date'          => [RentalVehicleInsurance::class, 'compulsory_end_date'],
            'compulsory_premium'           => [RentalVehicleInsurance::class, 'compulsory_premium'],
            'compulsory_insured_company'   => [RentalVehicleInsurance::class, 'compulsory_insured_company'],
            'compulsory_org_code'          => [RentalVehicleInsurance::class, 'compulsory_org_code'],
            'compulsory_insurance_company' => [RentalVehicleInsurance::class, 'compulsory_insurance_company'],
            //                'carrier_liability_plate_no',
            'carrier_liability_policy_number'     => [RentalVehicleInsurance::class, 'carrier_liability_policy_number'],
            'carrier_liability_start_date'        => [RentalVehicleInsurance::class, 'carrier_liability_start_date'],
            'carrier_liability_end_date'          => [RentalVehicleInsurance::class, 'carrier_liability_end_date'],
            'carrier_liability_premium'           => [RentalVehicleInsurance::class, 'carrier_liability_premium'],
            'carrier_liability_insured_company'   => [RentalVehicleInsurance::class, 'carrier_liability_insured_company'],
            'carrier_liability_org_code'          => [RentalVehicleInsurance::class, 'carrier_liability_org_code'],
            'carrier_liability_insurance_company' => [RentalVehicleInsurance::class, 'carrier_liability_insurance_company'],
            //                'commercial_plate_no',
            'commercial_policy_number'     => [RentalVehicleInsurance::class, 'commercial_policy_number'],
            'commercial_start_date'        => [RentalVehicleInsurance::class, 'commercial_start_date'],
            'commercial_end_date'          => [RentalVehicleInsurance::class, 'commercial_end_date'],
            'commercial_premium'           => [RentalVehicleInsurance::class, 'commercial_premium'],
            'commercial_insured_company'   => [RentalVehicleInsurance::class, 'commercial_insured_company'],
            'commercial_org_code'          => [RentalVehicleInsurance::class, 'commercial_org_code'],
            'commercial_insurance_company' => [RentalVehicleInsurance::class, 'commercial_insurance_company'],
            'is_company_borne'             => [RentalVehicleInsurance::class, 'is_company_borne'],
            'vi_remark'                    => [RentalVehicleInsurance::class, 'vi_remark'],
        ];
    }

    public static function importBeforeValidateDo(): \Closure
    {
        return function (&$item) {
            $item['ve_id']                      = RentalVehicle::plateNoKv($item['plate_no'] ?? null);
            $item['compulsory_plate_no']        = $item['plate_no'] ?? null;
            $item['carrier_liability_plate_no'] = $item['plate_no'] ?? null;
            $item['commercial_plate_no']        = $item['plate_no'] ?? null;
        };
    }

    public static function importValidatorRule(array $item, array $fieldAttributes): void
    {
        $rules = [
            've_id' => ['required', 'integer'],
            // 交强险字段
            'compulsory_plate_no'          => ['nullable', 'string', 'max:50'],
            'compulsory_policy_number'     => ['nullable', 'string', 'max:50'],
            'compulsory_start_date'        => ['nullable', 'date'],
            'compulsory_end_date'          => ['nullable', 'date', 'after:compulsory_start_date'],
            'compulsory_premium'           => ['nullable', 'decimal:0,2', 'gte:0'],
            'compulsory_insured_company'   => ['nullable', 'string', 'max:255'],
            'compulsory_org_code'          => ['nullable', 'string', 'max:50'],
            'compulsory_insurance_company' => ['nullable', 'string', 'max:255'],
            // 承运人责任险字段
            'carrier_liability_plate_no'          => ['nullable', 'string', 'max:50'],
            'carrier_liability_policy_number'     => ['nullable', 'string', 'max:50'],
            'carrier_liability_start_date'        => ['nullable', 'date'],
            'carrier_liability_end_date'          => ['nullable', 'date', 'after:carrier_liability_start_date'],
            'carrier_liability_premium'           => ['nullable', 'decimal:0,2', 'gte:0'],
            'carrier_liability_insured_company'   => ['nullable', 'string', 'max:255'],
            'carrier_liability_org_code'          => ['nullable', 'string', 'max:50'],
            'carrier_liability_insurance_company' => ['nullable', 'string', 'max:255'],
            // 商业险字段
            'commercial_plate_no'          => ['nullable', 'string', 'max:50'],
            'commercial_policy_number'     => ['nullable', 'string', 'max:50'],
            'commercial_start_date'        => ['nullable', 'date'],
            'commercial_end_date'          => ['nullable', 'date', 'after:commercial_start_date'],
            'commercial_premium'           => ['nullable', 'decimal:0,2', 'gte:0'],
            'commercial_insured_company'   => ['nullable', 'string', 'max:255'],
            'commercial_org_code'          => ['nullable', 'string', 'max:50'],
            'commercial_insurance_company' => ['nullable', 'string', 'max:255'],

            // 其他字段
            'is_company_borne' => ['nullable', 'boolean'],
            'vi_remark'        => ['nullable', 'string'],
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
            $rentalVehicleInsurance = RentalVehicleInsurance::query()->create($input);
        };
    }

    public static function options(?\Closure $where = null): array
    {
        return [];
    }

    protected function compulsoryPolicyFile(): Attribute
    {
        return $this->uploadFile();
    }

    protected function compulsoryPolicyPhotos(): Attribute
    {
        return $this->uploadFileArray();
    }

    protected function compulsoryPolicyAddendumFile(): Attribute
    {
        return $this->uploadFile();
    }

    protected function carrierLiabilityPolicyFile(): Attribute
    {
        return $this->uploadFile();
    }

    protected function carrierLiabilityPolicyPhotos(): Attribute
    {
        return $this->uploadFileArray();
    }

    protected function commercialPolicyFile(): Attribute
    {
        return $this->uploadFile();
    }

    protected function commercialPolicyPhotos(): Attribute
    {
        return $this->uploadFileArray();
    }

    protected function commercialPolicyAddendumFile(): Attribute
    {
        return $this->uploadFile();
    }
}
