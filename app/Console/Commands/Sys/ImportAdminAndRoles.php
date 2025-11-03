<?php

namespace App\Console\Commands\Sys;

use App\Enum\Admin\AdmUserType;
use App\Enum\Admin\ArIsCustom;
use App\Models\Admin\Staff;
use App\Models\Admin\StaffRole;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportAdminAndRoles extends Command
{
    protected $signature   = 'sys:import-admin-and-roles';
    protected $description = '导入应用内置角色';

    /** 导入的内置角色清单 */
    protected array $builtinRoles = [
        'Super Admin' => [],
        '车管'        => ['GpsData', 'IotDeviceBinding', 'ExpiryDriver', 'ExpiryVehicle', 'VehicleForceTake', 'VehicleInsurance', 'VehicleSchedule', 'ViolationCount', 'ServiceCenter', 'VehicleAccident', 'VehicleMaintenance', 'VehicleRepair', 'OneAccount', 'Vehicle', 'VehicleInspection', 'VehicleManualViolation', 'VehicleModel', 'VehicleViolation'],
        '财务'        => ['Inout', 'PaymentAccount', 'Payment', 'PaymentType', 'SaleOrderRentPayment', 'SaleOrderSignPayment'],
        '销售'        => ['BookingOrder', 'BookingVehicle', 'SaleOrder', 'SaleOrderCancel', 'SaleOrderTpl', 'SaleSettlement', 'VehiclePreparation', 'VehicleReplacement', 'Customer'],
        '修理厂'      => ['VehicleAccident', 'VehicleMaintenance', 'VehicleRepair'],
        '经理'        => ['Configuration0', 'DocTpl', 'Customer', 'Inout', 'Payment', 'PaymentAccount', 'PaymentType', 'Staff', 'StaffPermission', 'StaffRole', 'OneAccount', 'Vehicle', 'ServiceCenter', 'SaleOrder', 'Customer', 'DeliveryWecomGroup', 'DeliveryWecomMember', 'DocTpl'],
        '系统管理'    => ['Configuration0', 'Configuration1', 'Import', 'Staff', 'StaffPermission', 'StaffRole', 'DeliveryChannel', 'DeliveryLog'],
    ];

    private array $mock_admins = [
        '演示经理'   => '经理',
        '演示修理厂' => '修理厂',
        '演示销售'   => '销售',
        '演示车管'   => '车管',
        '演示财务'   => '财务',
    ];

    public function handle(): int
    {
        DB::transaction(function () {
            foreach ($this->builtinRoles as $name => $permissions) {
                $role = StaffRole::query()->updateOrCreate(['name' => $name, 'guard_name' => 'web'], ['is_custom' => ArIsCustom::NO]);
                if ($permissions) {
                    $role->givePermissionTo($permissions);
                }
            }
            $this->info('内置角色导入完成（基于 name+guard_name 去重）。');

            if (config('setting.mock.enable')) {
                foreach ($this->mock_admins as $staff_name => $role_name) {
                    $staff = Staff::query()->updateOrCreate(['name' => $staff_name], ['user_type' => AdmUserType::MOCK]);

                    $role = StaffRole::query()->where(['name' => $role_name])->firstOrFail();
                    $staff->assignRole($role);
                }

                $this->info('演示用户导入完成（基于 name 去重）。');
            }
        });

        return self::SUCCESS;
    }
}
