<?php

use App\Models\_\Configuration;
use App\Models\Admin\Staff;
use App\Models\Customer\Customer;
use App\Models\Customer\CustomerCompany;
use App\Models\Customer\CustomerIndividual;
use App\Models\One\OneAccount;
use App\Models\Payment\Payment;
use App\Models\Sale\BookingOrder;
use App\Models\Sale\BookingVehicle;
use App\Models\Sale\DocTpl;
use App\Models\Sale\SaleOrder;
use App\Models\Sale\SaleOrderTpl;
use App\Models\Sale\SaleSettlement;
use App\Models\Sale\VehicleReplacement;
use App\Models\Vehicle\ServiceCenter;
use App\Models\Vehicle\Vehicle;
use App\Models\Vehicle\VehicleAccident;
use App\Models\Vehicle\VehicleForceTake;
use App\Models\Vehicle\VehicleInspection;
use App\Models\Vehicle\VehicleInsurance;
use App\Models\Vehicle\VehicleMaintenance;
use App\Models\Vehicle\VehicleManualViolation;
use App\Models\Vehicle\VehicleModel;
use App\Models\Vehicle\VehicleRepair;
use App\Models\Vehicle\VehicleSchedule;

return [
    'dblog' => [
        'models' => [
            class_basename(Staff::class)                  => 'id',
            class_basename(Configuration::class)          => 'cfg_id',
            class_basename(DocTpl::class)                 => 'dt_id',
            class_basename(Customer::class)               => 'cu_id',
            class_basename(CustomerIndividual::class)     => 'cui_id',
            class_basename(CustomerCompany::class)        => 'cui_id',
            class_basename(VehicleModel::class)           => 'vm_id',
            class_basename(Vehicle::class)                => 've_id',
            class_basename(VehicleAccident::class)        => 'va_id',
            class_basename(OneAccount::class)             => 'oa_id',
            class_basename(VehicleRepair::class)          => 'vr_id',
            class_basename(VehicleMaintenance::class)     => 'vm_id',
            class_basename(VehicleInspection::class)      => 'vi_id',
            class_basename(VehicleManualViolation::class) => 'vmv_id',
            class_basename(VehicleForceTake::class)       => 'vft_id',
            class_basename(VehicleReplacement::class)     => 'vr_id',
            class_basename(VehicleSchedule::class)        => 'vs_id',
            class_basename(VehicleInsurance::class)       => 'vi_id',
            class_basename(SaleOrder::class)              => 'so_id',
            class_basename(SaleOrderTpl::class)           => 'sot_id',
            class_basename(SaleSettlement::class)         => 'ss_id',
            class_basename(Payment::class)                => 'rp_id',
            class_basename(BookingVehicle::class)         => 'bv_id',
            class_basename(BookingOrder::class)           => 'bo_id',
            class_basename(ServiceCenter::class)          => 'sc_id',
        ],

        'schema' => 'table_log',

        'union' => [
            'customers' => [
                ['CustomerIndividual', 'cui_id', 'customer_individuals'],
                ['CustomerCompany', 'cuc_id', 'customer_companies'],
            ],
        ],
    ],

    'mock' => [
        'enable' => (bool) env('MOCK_ENABLE', false),
    ],

    'gen' => [
        'month' => [
            'limit_size' => 200000,  // 每个表每月最大不超过20万
            'offset'     => env('GEN_MONTH_OFFSET', -12), // 月份修正
        ],

        'factor' => env('GEN_FACTOR', 3),

        'fake_brands' => [
            '丰田', '福特', '本田', '日产', '大众',
            '宝马', '奔驰', '特斯拉', '现代', '起亚',
        ],

        'fake_models' => [
            '轿车', 'SUV', '跑车', '皮卡', '掀背车',
            '旅行车', '敞篷车', '混合动力车', '电动车', '豪华车',
        ],
    ],

    'manual_host' => env('MANUAL_HOST'),

    'super_role' => [
        'name' => env('SUPER_ROLE_NAME', 'Super Admin'),
    ],

    'super_user' => [
        'email'    => env('SUPER_USER_EMAIL', ''),
        'name'     => env('SUPER_USER_NAME', '超级管理员'),
        'password' => env('SUPER_USER_PASSWORD', ''),
    ],

    'wecom' => [
        'corp_id'                      => env('WECOM_CORP_ID'),
        'app_delivery_agent_id'        => env('WECOM_APP_DELIVERY_AGENT_ID'),
        'app_delivery_secret'          => env('WECOM_APP_DELIVERY_SECRET'),
        'app_delivery_token_cache_key' => env('WECOM_APP_DELIVERY_TOKEN_CACHE_KEY', 'wecom:app_delivery:access_token'),
        'cache_ttl_buffer'             => (int) env('WECOM_TOKEN_TTL_BUFFER', 120), // 以秒为单位的安全缓冲，避免刚好过期
    ],
];
