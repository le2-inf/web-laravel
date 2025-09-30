<?php

namespace App\Enum\Config;

use App\Models\Rental\Customer\RentalCustomer;
use App\Models\Rental\Payment\RentalPayment;
use App\Models\Rental\Sale\RentalSaleOrder;
use App\Models\Rental\Vehicle\RentalVehicle;
use App\Models\Rental\Vehicle\RentalVehicleAccident;
use App\Models\Rental\Vehicle\RentalVehicleInsurance;
use App\Models\Rental\Vehicle\RentalVehicleMaintenance;
use App\Models\Rental\Vehicle\RentalVehicleManualViolation;
use App\Models\Rental\Vehicle\RentalVehicleRepair;
use App\Models\Rental\Vehicle\RentalVehicleSchedule;

class ImportConfig
{
    private static array $keys = [
        RentalVehicle::class,
        RentalCustomer::class,
        RentalVehicleSchedule::class,
        RentalVehicleInsurance::class,
        RentalSaleOrder::class,
        RentalPayment::class,  // 必须先导入 RentalSaleOrder
        RentalVehicleRepair::class,
        RentalVehicleMaintenance::class,
        RentalVehicleAccident::class,
        RentalVehicleManualViolation::class,
    ];

    public static function keys(): array
    {
        return static::$keys;
    }

    public static function options(): array
    {
        $result = [];
        foreach (static::keys() as $model) {
            $result[] = [
                'text'  => trans_model($model).trans_model_suffix($model),
                'value' => $model,
            ];
        }

        return [preg_replace('/^.*\\\/', '', get_called_class()).'Options' => $result];
    }

    public static function kv(): array
    {
        $result = [];
        foreach (static::keys() as $model) {
            $result[$model] = trans_model($model).trans_model_suffix($model);
        }

        return [preg_replace('/^.*\\\/', '', get_called_class()).'kv' => $result];
    }
}
