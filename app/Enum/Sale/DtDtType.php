<?php

namespace App\Enum\Sale;

use App\Enum\EnumLikeBase;
use App\Models\Payment\Payment;
use App\Models\Sale\SaleOrder;
use App\Models\Sale\SaleSettlement;
use App\Models\Vehicle\VehicleInspection;

class DtDtType extends EnumLikeBase
{
    public const string SALE_ORDER      = SaleOrder::class;
    public const string SALE_SETTLEMENT = SaleSettlement::class;

    public const string PAYMENT = Payment::class;

    public const string VEHICLE_INSPECTION = VehicleInspection::class;

    public const array LABELS = [
        self::SALE_ORDER         => '租车合同模板',
        self::SALE_SETTLEMENT    => '结算单模板',
        self::PAYMENT            => '财务收据模板',
        self::VEHICLE_INSPECTION => '验车单模板',
    ];

    public function getFieldsAndRelations(bool $kv = false, bool $valueOnly = false): array
    {
        $value = null;

        switch ($this->value) {
            case self::SALE_ORDER:
                $SaleOrder = new SaleOrder();
                if ($valueOnly) {
                    $SaleOrder->setFieldMode(false);
                }
                $value = $SaleOrder->getFieldsAndRelations(['Customer', 'Customer.CustomerIndividual', 'Vehicle', 'Company', 'Vehicle.VehicleInsurances']);

                if ($kv) {
                    $key = self::SALE_ORDER.'Fields';

                    $value = [$key => $value];
                }

                break;

            case self::SALE_SETTLEMENT:
                $SaleSettlement = new SaleSettlement();
                if ($valueOnly) {
                    $SaleSettlement->setFieldMode(false);
                }
                $value = $SaleSettlement->getFieldsAndRelations(['SaleOrder', 'SaleOrder.Customer', 'SaleOrder.Vehicle']);
                if ($kv) {
                    $key = self::SALE_SETTLEMENT.'Fields';

                    $value = [$key => $value];
                }

                break;

            case self::PAYMENT:
                $Payment = new Payment();
                if ($valueOnly) {
                    $Payment->setFieldMode(false);
                }
                $value = $Payment->getFieldsAndRelations(['PaymentAccount', 'PaymentType', 'SaleOrder', 'SaleOrder.Customer']);
                if ($kv) {
                    $key = self::PAYMENT.'Fields';

                    $value = [$key => $value];
                }

                break;

            case self::VEHICLE_INSPECTION:
                $VehicleInspection = new VehicleInspection();
                if ($valueOnly) {
                    $VehicleInspection->setFieldMode(false);
                }
                $value = $VehicleInspection->getFieldsAndRelations(['Vehicle', 'SaleOrder', 'SaleOrder.Customer']);
                if ($kv) {
                    $key = self::VEHICLE_INSPECTION.'Fields';

                    $value = [$key => $value];
                }
        }

        return $value;
    }
}
