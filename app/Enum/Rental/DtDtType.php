<?php

namespace App\Enum\Rental;

use App\Enum\EnumLikeBase;
use App\Models\Rental\Payment\RentalPayment;
use App\Models\Rental\Sale\RentalSaleOrder;
use App\Models\Rental\Sale\RentalSaleSettlement;
use App\Models\Rental\Vehicle\RentalVehicleInspection;

class DtDtType extends EnumLikeBase
{
    public const string RENTAL_ORDER      = RentalSaleOrder::class;
    public const string RENTAL_SETTLEMENT = RentalSaleSettlement::class;

    public const string RENTAL_PAYMENT = RentalPayment::class;

    public const string RENTAL_VEHICLE_INSPECTION = RentalVehicleInspection::class;

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
                $RentalSaleOrder = new RentalSaleOrder();
                if ($valueOnly) {
                    $RentalSaleOrder->setFieldMode(false);
                }
                $value = $RentalSaleOrder->getFieldsAndRelations(['RentalCustomer', 'RentalCustomer.RentalCustomerIndividual', 'RentalVehicle', 'RentalCompany', 'RentalVehicle.RentalVehicleInsurances']);

                if ($kv) {
                    $key = self::RENTAL_ORDER.'Fields';

                    $value = [$key => $value];
                }

                break;

            case self::RENTAL_SETTLEMENT:
                $RentalSaleSettlement = new RentalSaleSettlement();
                if ($valueOnly) {
                    $RentalSaleSettlement->setFieldMode(false);
                }
                $value = $RentalSaleSettlement->getFieldsAndRelations(['RentalSaleOrder', 'RentalSaleOrder.RentalCustomer', 'RentalSaleOrder.RentalVehicle']);
                if ($kv) {
                    $key = self::RENTAL_SETTLEMENT.'Fields';

                    $value = [$key => $value];
                }

                break;

            case self::RENTAL_PAYMENT:
                $RentalPayment = new RentalPayment();
                if ($valueOnly) {
                    $RentalPayment->setFieldMode(false);
                }
                $value = $RentalPayment->getFieldsAndRelations(['RentalPaymentAccount', 'RentalPaymentType', 'RentalSaleOrder', 'RentalSaleOrder.RentalCustomer']);
                if ($kv) {
                    $key = self::RENTAL_PAYMENT.'Fields';

                    $value = [$key => $value];
                }

                break;

            case self::RENTAL_VEHICLE_INSPECTION:
                $RentalVehicleInspection = new RentalVehicleInspection();
                if ($valueOnly) {
                    $RentalVehicleInspection->setFieldMode(false);
                }
                $value = $RentalVehicleInspection->getFieldsAndRelations(['RentalVehicle', 'RentalSaleOrder', 'RentalSaleOrder.RentalCustomer']);
                if ($kv) {
                    $key = self::RENTAL_VEHICLE_INSPECTION.'Fields';

                    $value = [$key => $value];
                }
        }

        return $value;
    }
}
