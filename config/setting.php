<?php

return [
    'dblog' => [
        'tables' => [
            'admins'                           => 'id',
            'configurations'                   => 'cfg_id',
            'admin_model_has_roles'            => 'role_id',
            'rental_doc_tpls'                  => 'dt_id',
            'rental_customers'                 => 'cu_id',
            'rental_customer_individuals'      => 'cui_id',
            'rental_vehicle_models'            => 'vm_id',
            'rental_vehicles'                  => 've_id',
            'rental_vehicle_accidents'         => 'va_id',
            'rental_one_accounts'              => 'oa_id',
            'rental_vehicle_repairs'           => 'vr_id',
            'rental_vehicle_maintenances'      => 'vm_id',
            'rental_vehicle_inspections'       => 'vi_id',
            'rental_vehicle_manual_violations' => 'vmv_id',
            'rental_vehicle_force_takes'       => 'vft_id',
            'rental_vehicle_replacements'      => 'vr_id',
            'rental_vehicle_schedules'         => 'vs_id',
            'rental_vehicle_insurances'        => 'vi_id',
            'rental_sale_orders'               => 'so_id',
            'rental_sale_order_tpls'           => 'sot_id',
            'rental_sale_settlements'          => 'ss_id',
            'rental_payments'                  => 'rp_id',
            'rental_booking_vehicles'          => 'bv_id',
            'rental_booking_orders'            => 'bo_id',
            'rental_service_centers'           => 'sc_id',
        ],

        'schema' => 'table_log',

        'union' => [
            'rental_customers' => [
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
