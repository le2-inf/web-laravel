<?php

namespace App\Models\Rental\Customer;

use App\Attributes\ClassName;
use App\Attributes\ColumnDesc;
use App\Enum\Customer\CuCuType;
use App\Enum\Customer\CuiCuiGender;
use App\Exceptions\ClientException;
use App\Models\Admin\Admin;
use App\Models\ModelTrait;
use App\Models\Rental\ImportTrait;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\HasApiTokens;

#[ClassName('客户', '信息')]
#[ColumnDesc('cu_type', required: true, enum_class: CuCuType::class)]
#[ColumnDesc('contact_name', required: true, )]
#[ColumnDesc('contact_phone', required: true, unique: true, )]
#[ColumnDesc('contact_email', unique: true)]
#[ColumnDesc('contact_wechat', )]
#[ColumnDesc('contact_live_city', )]
#[ColumnDesc('contact_live_address', )]
#[ColumnDesc('cu_remark', )]
/**
 * @property int                           $cu_id                    客户序号
 * @property CuCuType|string               $cu_type                  客户类型
 * @property string                        $contact_name             联系人姓名
 * @property string                        $contact_phone            联系电话
 * @property null|string                   $contact_email            联系人邮箱
 * @property null|string                   $contact_wechat           联系人微信号
 * @property null|string                   $contact_live_city        现住城市
 * @property null|string                   $contact_live_address     现住地址
 * @property null|string                   $cu_remark                顾客备注
 * @property null|int                      $sales_manager            负责销售
 * @property null|int                      $driver_manager           负责驾管
 *                                                                   -
 * @property null|RentalCustomerIndividual $RentalCustomerIndividual
 * @property null|RentalCustomerCompany    $RentalCustomerCompany
 * @property null|Admin                    $SalesManager
 * @property null|Admin                    $DriverManager
 *                                                                   -
 */
class RentalCustomer extends Authenticatable
{
    use ModelTrait;

    use HasApiTokens;

    use ImportTrait;

    protected $primaryKey = 'cu_id';

    protected $guarded = ['cu_id'];

    protected $casts = [
        'cu_type' => CuCuType::class,
    ];

    protected $appends = [
        'cu_full_label',
        'cu_type_label',
    ];

    public static function options(?\Closure $where = null): array
    {
        $key   = preg_replace('/^.*\\\/', '', get_called_class()).'Options';
        $value = static::query()->toBase()
            ->select(DB::raw("CONCAT(contact_name,' | ',contact_phone) as text,cu_id as value"))
            ->get()
        ;

        return [$key => $value];
    }

    public static function plateNoKv(?string $contact_phone = null)
    {
        static $kv = null;

        if (null === $kv) {
            $kv = DB::query()
                ->from('rental_customers')
                ->select('cu_id', 'contact_phone')
                ->pluck('cu_id', 'contact_phone')
                ->toArray()
            ;
        }

        if ($contact_phone) {
            return $kv[$contact_phone] ?? null;
        }

        return $kv;
    }

    public function RentalCustomerIndividual(): HasOne
    {
        return $this->hasOne(RentalCustomerIndividual::class, 'cu_id', 'cu_id');
    }

    public function RentalCustomerCompany(): HasOne
    {
        return $this->hasOne(RentalCustomerCompany::class, 'cu_id', 'cu_id');
    }

    public static function indexQuery(array $search = []): Builder
    {
        return DB::query()
            ->from('rental_customers', 'cu')
            ->leftJoin('rental_customer_companies as cuc', function (JoinClause $join) {
                $join->on('cuc.cu_id', '=', 'cu.cu_id')
                    ->where('cu.cu_type', '=', CuCuType::COMPANY)
                ;
            })
            ->leftJoin('rental_customer_individuals as cui', function (JoinClause $join) {
                $join->on('cui.cu_id', '=', 'cu.cu_id')
                    ->where('cu.cu_type', '=', CuCuType::INDIVIDUAL)
                ;
            })
            ->select('cuc.*', 'cui.*', 'cu.*') // cu.* 在最后，这样可以让空值在前
            ->addSelect(
                DB::raw(CuCuType::toCaseSQL()),
                DB::raw(CuiCuiGender::toCaseSQL()),
            )
        ;
    }

    public function SalesManager(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'sales_manager', 'cu_id');
    }

    public function DriverManager(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'driver_manager', 'cu_id');
    }

    public static function importColumns(): array
    {
        return [
            'cu_type'                        => [RentalCustomer::class, 'cu_type'],
            'contact_name'                   => [RentalCustomer::class, 'contact_name'],
            'contact_phone'                  => [RentalCustomer::class, 'contact_phone'],
            'contact_email'                  => [RentalCustomer::class, 'contact_email'],
            'contact_wechat'                 => [RentalCustomer::class, 'contact_wechat'],
            'contact_live_city'              => [RentalCustomer::class, 'contact_live_city'],
            'contact_live_address'           => [RentalCustomer::class, 'contact_live_address'],
            'cu_remark'                      => [RentalCustomer::class, 'cu_remark'],
            'cui_name'                       => [RentalCustomerIndividual::class, 'cui_name'],
            'cui_gender'                     => [RentalCustomerIndividual::class, 'cui_gender'],
            'cui_date_of_birth'              => [RentalCustomerIndividual::class, 'cui_date_of_birth'],
            'cui_id_number'                  => [RentalCustomerIndividual::class, 'cui_id_number'],
            'cui_id_address'                 => [RentalCustomerIndividual::class, 'cui_id_address'],
            'cui_id_expiry_date'             => [RentalCustomerIndividual::class, 'cui_id_expiry_date'],
            'cui_driver_license_number'      => [RentalCustomerIndividual::class, 'cui_driver_license_number'],
            'cui_driver_license_category'    => [RentalCustomerIndividual::class, 'cui_driver_license_category'],
            'cui_driver_license_expiry_date' => [RentalCustomerIndividual::class, 'cui_driver_license_expiry_date'],
            'cui_emergency_relationship'     => [RentalCustomerIndividual::class, 'cui_emergency_relationship'],
            'cui_emergency_contact_name'     => [RentalCustomerIndividual::class, 'cui_emergency_contact_name'],
            'cui_emergency_id_number'        => [RentalCustomerIndividual::class, 'cui_emergency_id_number'],
            'cui_emergency_contact_phone'    => [RentalCustomerIndividual::class, 'cui_emergency_contact_phone'],
        ];
    }

    public static function importBeforeValidateDo(): \Closure
    {
        return function (&$item) {
            $item['cu_type']                   = CuCuType::searchValue($item['cu_type']);
            $item['cui_gender']                = CuiCuiGender::searchValue($item['cui_gender'] ?? null);
            static::$fields['contact_phone'][] = $item['contact_phone'] ?? null;
            static::$fields['contact_email'][] = $item['contact_email'] ?? null;
        };
    }

    public static function importValidatorRule(array $item, array $fieldAttributes): void
    {
        $rules = [
            // customer
            'cu_type'              => ['required', 'string', Rule::in(CuCuType::label_keys())],
            'contact_name'         => ['required', 'string', 'max:255'],
            'contact_phone'        => ['required', 'regex:/^\d{11}$/'],
            'contact_email'        => ['nullable', 'email'],
            'contact_wechat'       => ['nullable', 'string', 'max:255'],
            'contact_live_city'    => ['nullable', 'string', 'max:64'],
            'contact_live_address' => ['nullable', 'string', 'max:255'],
            'cu_remark'            => ['nullable', 'string'],
            // customer_individuals
            'cui_name'                       => ['nullable', 'string', 'max:255'],
            'cui_gender'                     => ['nullable', Rule::in(CuiCuiGender::label_keys())],
            'cui_date_of_birth'              => ['nullable', 'date', 'before:today'],
            'cui_id_number'                  => ['nullable', 'regex:/^\d{17}[\dXx]$/'],
            'cui_id_address'                 => ['nullable', 'string', 'max:500'],
            'cui_id_expiry_date'             => ['nullable', 'date', 'after:date_of_birth'],
            'cui_driver_license_number'      => ['nullable', 'string', 'max:50'],
            'cui_driver_license_category'    => ['nullable', 'string', 'regex:/^[A-Z]\d+$/'],
            'cui_driver_license_expiry_date' => ['nullable', 'date'],
            'cui_emergency_contact_name'     => ['nullable', 'string', 'max:64'],
            'cui_emergency_contact_phone'    => ['nullable', 'regex:/^\d{7,15}$/'],
            'cui_emergency_relationship'     => ['nullable', 'string', 'max:64'],
        ];

        $validator = Validator::make($item, $rules, [], $fieldAttributes);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
    }

    public static function importAfterValidatorDo(): \Closure
    {
        return function () {
            // contact_phone
            $contact_phone = RentalCustomer::query()->whereIn('contact_phone', static::$fields['contact_phone'])->pluck('contact_phone')->toArray();
            if (count($contact_phone) > 0) {
                throw new ClientException('以下联系电话已经存在：'.join(',', $contact_phone));
            }

            // contact_email
            $contact_email = RentalCustomer::query()->whereIn('contact_email', static::$fields['contact_email'])->pluck('contact_email')->toArray();
            if (count($contact_email) > 0) {
                throw new ClientException('以下联系邮箱已经存在：'.join(',', $contact_email));
            }
        };
    }

    public static function importCreateDo(): \Closure
    {
        return function ($input) {
            $rentalCustomer = RentalCustomer::query()->create($input);

            switch ($rentalCustomer->cu_type) {
                case CuCuType::INDIVIDUAL:
                    $rentalCustomer->RentalCustomerIndividual()->updateOrCreate(
                        [
                            'cu_id' => $rentalCustomer->cu_id,
                        ],
                        $input,
                    );

                    break;

                case CuCuType::COMPANY:
                    $rentalCustomer->RentalCustomerCompany()->updateOrCreate(
                        [
                            'cu_id' => $rentalCustomer->cu_id,
                        ],
                        $input,
                    );

                    break;

                default:
                    break;
            }
        };
    }

    protected function cuTypeLabel(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->getAttribute('cu_type')?->label
        );
    }

    protected function cuFullLabel(): Attribute
    {
        return Attribute::make(
            get: fn () => join(' | ', [
                $this->getOriginal('contact_name'),
                $this->getOriginal('contact_phone'),
            ])
        );
    }
}
