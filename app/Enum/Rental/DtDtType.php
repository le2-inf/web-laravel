<?php

namespace App\Enum\Rental;

use App\Enum\EnumLikeBase;
use App\Models\Payment\Payment;
use App\Models\Sale\SaleOrder;
use App\Models\Sale\SaleSettlement;
use App\Models\Vehicle\VehicleInspection;

class DtDtType extends EnumLikeBase
{
    public const string RENTAL_ORDER      = SaleOrder::class;
    public const string RENTAL_SETTLEMENT = SaleSettlement::class;

    public const string RENTAL_PAYMENT = Payment::class;

    public const string RENTAL_VEHICLE_INSPECTION = VehicleInspection::class;

    public const array LABELS = [
        self::RENTAL_ORDER              => '租车合同模板',
        self::RENTAL_SETTLEMENT         => '结算单模板',
        self::RENTAL_PAYMENT            => '财务收据模板',
        self::RENTAL_VEHICLE_INSPECTION => '验车单模板',
    ];

    public function getFieldsAndRelations(bool $kv = false, bool $valueOnly = false): array
    {
        $value = null;

        switch ($this->value) {
            case self::RENTAL_ORDER:
                $SaleOrder = new SaleOrder();
                if ($valueOnly) {
                    $SaleOrder->setFieldMode(false);
                }
                $value = $SaleOrder->getFieldsAndRelations(['Customer', 'Customer.CustomerIndividual', 'Vehicle', 'Company', 'Vehicle.VehicleInsurances']);

                if ($kv) {
                    $key = self::RENTAL_ORDER.'Fields';

                    $value = [$key => $value];
                }

                break;

            case self::RENTAL_SETTLEMENT:
                $SaleSettlement = new SaleSettlement();
                if ($valueOnly) {
                    $SaleSettlement->setFieldMode(false);
                }
                $value = $SaleSettlement->getFieldsAndRelations(['SaleOrder', 'SaleOrder.Customer', 'SaleOrder.Vehicle']);
                if ($kv) {
                    $key = self::RENTAL_SETTLEMENT.'Fields';

                    $value = [$key => $value];
                }

                break;

            case self::RENTAL_PAYMENT:
                $Payment = new Payment();
                if ($valueOnly) {
                    $Payment->setFieldMode(false);
                }
                $value = $Payment->getFieldsAndRelations(['PaymentAccount', 'PaymentType', 'SaleOrder', 'SaleOrder.Customer']);
                if ($kv) {
                    $key = self::RENTAL_PAYMENT.'Fields';

                    $value = [$key => $value];
                }

                break;

            case self::RENTAL_VEHICLE_INSPECTION:
                $VehicleInspection = new VehicleInspection();
                if ($valueOnly) {
                    $VehicleInspection->setFieldMode(false);
                }
                $value = $VehicleInspection->getFieldsAndRelations(['Vehicle', 'SaleOrder', 'SaleOrder.Customer']);
                if ($kv) {
                    $key = self::RENTAL_VEHICLE_INSPECTION.'Fields';

                    $value = [$key => $value];
                }
        }

        return $value;
    }
}
