<?php

namespace App\Services;

use App\Http\Controllers\Admin\Customer\RentalCustomerController;
use App\Http\Controllers\Admin\Payment\RentalInoutController;
use App\Http\Controllers\Admin\Payment\RentalPaymentController;
use App\Http\Controllers\Admin\Sale\RentalSaleOrderController;
use App\Http\Controllers\Admin\Sale\RentalSaleSettlementController;
use App\Http\Controllers\Admin\Sale\RentalVehicleReplacementController;
use App\Http\Controllers\Admin\Vehicle\RentalVehicleAccidentController;
use App\Http\Controllers\Admin\Vehicle\RentalVehicleController;
use App\Http\Controllers\Admin\Vehicle\RentalVehicleInspectionController;
use App\Http\Controllers\Admin\Vehicle\RentalVehicleMaintenanceController;
use App\Http\Controllers\Admin\Vehicle\RentalVehicleModelController;
use App\Http\Controllers\Admin\Vehicle\RentalVehicleRepairController;
use App\Http\Controllers\Admin\Vehicle\RentalVehicleViolationController;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class PageExcel
{
    public function __construct(protected string $class) {}

    public static function columns(string $key)
    {
        return match ($key) {
            RentalVehicleController::class => [
                'RentalVehicle.ve_id'            => fn ($item) => $item->ve_id,
                'RentalVehicle.plate_no'         => fn ($item) => $item->plate_no,
                'RentalVehicleModel.brand_model' => fn ($item) => $item->brand_name.'-'.$item->model_name,
                'RentalVehicle.ve_owner'         => fn ($item) => $item->ve_owner,
                'RentalVehicle.ve_usage'         => fn ($item) => $item->ve_usage,
                'RentalVehicle.ve_type'          => fn ($item) => $item->ve_type,
                'RentalVehicle.ve_purchase_date' => fn ($item) => $item->ve_purchase_date,
                'RentalVehicle.status_service'   => fn ($item) => $item->status_service_label,
            ],
            RentalVehicleModelController::class => [
                'RentalVehicleModel.vm_id'      => fn ($item) => $item->vm_id,
                'RentalVehicleModel.brand_name' => fn ($item) => $item->brand_name,
                'RentalVehicleModel.model_name' => fn ($item) => $item->model_name,
            ],
            RentalVehicleViolationController::class => [
                'RentalVehicleViolation.plate_no'           => fn ($item) => $item->plate_no,
                'RentalVehicleViolation.violation_datetime' => fn ($item) => $item->violation_datetime_,
                'RentalVehicleViolation.location'           => fn ($item) => $item->location,
                'RentalVehicleViolation.fine_amount'        => fn ($item) => $item->fine_amount,
                'RentalVehicleViolation.penalty_points'     => fn ($item) => $item->penalty_points,
                'RentalVehicleViolation.payment_status'     => fn ($item) => $item->payment_status_label,
                'RentalVehicleViolation.process_status'     => fn ($item) => $item->process_status_label,
                'RentalVehicleViolation.violation_content'  => fn ($item) => $item->violation_content,
                'RentalVehicleViolation.vv_remark'          => fn ($item) => $item->vv_remark,
            ],
            RentalVehicleInspectionController::class => [
                'RentalVehicleInspection.inspection_type'       => fn ($item) => $item->inspection_type_label,
                'RentalCustomer.contact_name'                   => fn ($item) => $item->contact_name,
                'RentalVehicle.plate_no'                        => fn ($item) => $item->plate_no,
                'RentalVehicleInspection.policy_copy'           => fn ($item) => $item->policy_copy_label,
                'RentalVehicleInspection.driving_license'       => fn ($item) => $item->driving_license_label,
                'RentalVehicleInspection.operation_license'     => fn ($item) => $item->operation_license_label,
                'RentalVehicleInspection.vi_mileage'            => fn ($item) => $item->vi_mileage,
                'RentalVehicleInspection.vehicle_damage_status' => fn ($item) => $item->vehicle_damage_status_label,
                'RentalVehicleInspection.inspection_datetime'   => fn ($item) => $item->inspection_datetime_,
                'RentalVehicleInspection.vi_remark'             => fn ($item) => $item->vi_remark,
                'RentalVehicleInspection.processed_by'          => fn ($item) => $item->processed_by,
                'RentalVehicleInspection.inspection_info'       => fn ($item) => static::str_render($item->inspection_info, 'inspection_info'),
            ],
            RentalVehicleRepairController::class => [
                'RentalVehicle.plate_no'                 => fn ($item) => $item->plate_no,
                'RentalCustomer.contact_name'            => fn ($item) => $item->contact_name,
                'RentalVehicleRepair.entry_datetime'     => fn ($item) => $item->entry_datetime_,
                'RentalVehicleRepair.vr_mileage'         => fn ($item) => $item->vr_mileage,
                'RentalVehicleRepair.repair_cost'        => fn ($item) => $item->repair_cost,
                'RentalVehicleRepair.delay_days'         => fn ($item) => $item->delay_days,
                'RentalServiceCenter.sc_name'            => fn ($item) => $item->sc_name,
                'RentalVehicleRepair.repair_content'     => fn ($item) => $item->repair_content,
                'RentalVehicleRepair.departure_datetime' => fn ($item) => $item->departure_datetime_,
                'RentalVehicleRepair.repair_status'      => fn ($item) => $item->repair_status_label,
                'RentalVehicleRepair.pickup_status'      => fn ($item) => $item->pickup_status_label,
                'RentalVehicleRepair.settlement_status'  => fn ($item) => $item->settlement_status_label,
                'RentalVehicleRepair.custody_vehicle'    => fn ($item) => $item->custody_vehicle_label,
                'RentalVehicleRepair.repair_attribute'   => fn ($item) => $item->repair_attribute_label,
                'RentalVehicleRepair.vr_remark'          => fn ($item) => $item->vr_remark,
                'RentalVehicleRepair.repair_info'        => fn ($item) => static::str_render($item->repair_info, 'repair_info'),
            ],
            RentalVehicleMaintenanceController::class => [
                'RentalVehicle.plate_no'                       => fn ($item) => $item->plate_no,
                'RentalCustomer.contact_name'                  => fn ($item) => $item->contact_name,
                'RentalServiceCenter.sc_name'                  => fn ($item) => $item->sc_name,
                'RentalVehicleMaintenance.entry_datetime'      => fn ($item) => $item->entry_datetime_,
                'RentalVehicleMaintenance.maintenance_amount'  => fn ($item) => $item->maintenance_amount,
                'RentalVehicleMaintenance.entry_mileage'       => fn ($item) => $item->entry_mileage,
                'RentalVehicleMaintenance.departure_datetime'  => fn ($item) => $item->departure_datetime_,
                'RentalVehicleMaintenance.maintenance_mileage' => fn ($item) => $item->maintenance_mileage,
                'RentalVehicleMaintenance.settlement_status'   => fn ($item) => $item->settlement_status_label,
                'RentalVehicleMaintenance.pickup_status'       => fn ($item) => $item->pickup_status_label,
                'RentalVehicleMaintenance.settlement_method'   => fn ($item) => $item->settlement_method_label,
                'RentalVehicleMaintenance.custody_vehicle'     => fn ($item) => $item->custody_vehicle_label,
                'RentalVehicleMaintenance.vm_remark'           => fn ($item) => $item->vm_remark,
                'RentalVehicleMaintenance.maintenance_info'    => fn ($item) => static::str_render($item->maintenance_info, 'maintenance_info'),
            ],
            RentalVehicleAccidentController::class => [
                'RentalVehicle.plate_no'                   => fn ($item) => $item->plate_no,
                'RentalCustomer.contact_name'              => fn ($item) => $item->contact_name,
                'RentalVehicleAccident.accident_location'  => fn ($item) => $item->accident_location,
                'RentalVehicleAccident.accident_dt'        => fn ($item) => $item->accident_dt_,
                'RentalVehicleAccident.responsible_party'  => fn ($item) => $item->responsible_party,
                'RentalVehicleAccident.claim_status'       => fn ($item) => $item->claim_status_label,
                'RentalVehicleAccident.self_amount'        => fn ($item) => $item->self_amount,
                'RentalVehicleAccident.third_party_amount' => fn ($item) => $item->third_party_amount,
                'RentalVehicleAccident.insurance_company'  => fn ($item) => $item->insurance_company,
                'RentalVehicleAccident.description'        => fn ($item) => $item->description,
                'RentalVehicleAccident.factory_in_dt'      => fn ($item) => $item->factory_in_dt_,
                'RentalServiceCenter.sc_name'              => fn ($item) => $item->sc_name,
                'RentalVehicleAccident.repair_content'     => fn ($item) => $item->repair_content,
                'RentalVehicleAccident.repair_status'      => fn ($item) => $item->repair_status_label,
                'RentalVehicleAccident.factory_out_dt'     => fn ($item) => $item->factory_out_dt_,
                'RentalVehicleAccident.settlement_status'  => fn ($item) => $item->settlement_status_label,
                'RentalVehicleAccident.pickup_status'      => fn ($item) => $item->pickup_status_label,
                'RentalVehicleAccident.settlement_method'  => fn ($item) => $item->settlement_method_label,
                'RentalVehicleAccident.managed_vehicle'    => fn ($item) => $item->managed_vehicle_label,
                'RentalVehicleAccident.va_remark'          => fn ($item) => $item->va_remark,
                'RentalVehicleAccident.accident_info'      => fn ($item) => static::str_render($item->accident_info, 'accident_info'),
            ],
            RentalCustomerController::class => [
                'RentalCustomer.cu_id'                                    => fn ($item) => $item->cu_id,
                'RentalCustomer.cu_type'                                  => fn ($item) => $item->cu_type_label,
                'RentalCustomer.contact_name'                             => fn ($item) => $item->contact_name,
                'RentalCustomer.contact_phone'                            => fn ($item) => $item->contact_phone,
                'RentalCustomer.contact_email'                            => fn ($item) => $item->contact_email,
                'RentalCustomer.contact_wechat'                           => fn ($item) => $item->contact_wechat,
                'RentalCustomer.contact_live_city'                        => fn ($item) => $item->contact_live_city,
                'RentalCustomer.contact_live_address'                     => fn ($item) => $item->contact_live_address,
                'RentalCustomer.cu_remark'                                => fn ($item) => $item->cu_remark,
                'RentalCustomerIndividual.cui_name'                       => fn ($item) => $item->cui_name,
                'RentalCustomerIndividual.cui_gender'                     => fn ($item) => $item->cui_gender_label,
                'RentalCustomerIndividual.cui_date_of_birth'              => fn ($item) => $item->cui_date_of_birth,
                'RentalCustomerIndividual.cui_id_number'                  => fn ($item) => $item->cui_id_number,
                'RentalCustomerIndividual.cui_id_address'                 => fn ($item) => $item->cui_id_address,
                'RentalCustomerIndividual.cui_id_expiry_date'             => fn ($item) => $item->cui_id_expiry_date,
                'RentalCustomerIndividual.cui_driver_license_number'      => fn ($item) => $item->cui_driver_license_number,
                'RentalCustomerIndividual.cui_driver_license_category'    => fn ($item) => $item->cui_driver_license_category,
                'RentalCustomerIndividual.cui_driver_license_expiry_date' => fn ($item) => $item->cui_driver_license_expiry_date,
                'RentalCustomerIndividual.cui_emergency_contact_name'     => fn ($item) => $item->cui_emergency_contact_name,
                'RentalCustomerIndividual.cui_emergency_contact_phone'    => fn ($item) => $item->cui_emergency_contact_phone,
                'RentalCustomerIndividual.cui_emergency_relationship'     => fn ($item) => $item->cui_emergency_relationship,
                'RentalCustomerCompany.cuc_unified_credit_code'           => fn ($item) => $item->cuc_unified_credit_code,
                'RentalCustomerCompany.cuc_registration_address'          => fn ($item) => $item->cuc_registration_address,
                'RentalCustomerCompany.cuc_office_address'                => fn ($item) => $item->cuc_office_address,
                'RentalCustomerCompany.cuc_establishment_date'            => fn ($item) => $item->cuc_establishment_date,
                'RentalCustomerCompany.cuc_number_of_employees'           => fn ($item) => $item->cuc_number_of_employees,
                'RentalCustomerCompany.cuc_industry'                      => fn ($item) => $item->cuc_industry,
                'RentalCustomerCompany.cuc_annual_revenue'                => fn ($item) => $item->cuc_annual_revenue,
                'RentalCustomerCompany.cuc_legal_representative'          => fn ($item) => $item->cuc_legal_representative,
                'RentalCustomerCompany.cuc_contact_person_position'       => fn ($item) => $item->cuc_contact_person_position,
                'RentalCustomerCompany.cuc_tax_registration_number'       => fn ($item) => $item->cuc_tax_registration_number,
                'RentalCustomerCompany.cuc_business_scope'                => fn ($item) => $item->cuc_business_scope,
            ],
            RentalSaleOrderController::class => [
                'RentalSaleOrder.rental_type'                     => fn ($item) => $item->rental_type_label,
                'RentalSaleOrder.rental_payment_type'             => fn ($item) => $item->rental_payment_type_label,
                'RentalCustomer.contact_name'                     => fn ($item) => $item->contact_name,
                'RentalCustomer.contact_phone'                    => fn ($item) => $item->contact_phone,
                'RentalVehicle.plate_no'                          => fn ($item) => $item->plate_no,
                'RentalVehicleModel.brand_model'                  => fn ($item) => $item->brand_name.'-'.$item->model_name,
                'RentalSaleOrder.contract_number'                 => fn ($item) => $item->contract_number,
                'RentalSaleOrder.rental_start'                    => fn ($item) => $item->rental_start,
                'RentalSaleOrder.installments'                    => fn ($item) => $item->installments,
                'RentalSaleOrder.rental_end'                      => fn ($item) => $item->rental_end,
                'RentalSaleOrder.deposit_amount'                  => fn ($item) => $item->deposit_amount,
                'RentalSaleOrder.management_fee_amount'           => fn ($item) => $item->management_fee_amount,
                'RentalSaleOrder.rent_amount'                     => fn ($item) => $item->rent_amount,
                'RentalSaleOrder.payment_day'                     => fn ($item) => $item->payment_day,
                'RentalSaleOrder.total_rent_amount'               => fn ($item) => $item->total_rent_amount,
                'RentalSaleOrder.insurance_base_fee_amount'       => fn ($item) => $item->insurance_base_fee_amount,
                'RentalSaleOrder.insurance_additional_fee_amount' => fn ($item) => $item->insurance_additional_fee_amount,
                'RentalSaleOrder.other_fee_amount'                => fn ($item) => $item->other_fee_amount,
                'RentalSaleOrder.total_amount'                    => fn ($item) => $item->total_amount,
                'RentalSaleOrder.order_status'                    => fn ($item) => $item->order_status_label,
                'RentalSaleOrder.order_at'                        => fn ($item) => $item->order_at_,
                'RentalSaleOrder.signed_at'                       => fn ($item) => $item->signed_at_,
                'RentalSaleOrder.canceled_at'                     => fn ($item) => $item->canceled_at_,
                'RentalSaleOrder.completed_at'                    => fn ($item) => $item->completed_at_,
                'RentalSaleOrder.early_termination_at'            => fn ($item) => $item->early_termination_at_,
            ],
            RentalVehicleReplacementController::class => [
                'RentalCustomer.contact_name'                     => fn ($item) => $item->contact_name,
                'RentalCustomer.contact_phone'                    => fn ($item) => $item->contact_phone,
                'RentalVehicleReplacement.replacement_type'       => fn ($item) => $item->replacement_type_label,
                'RentalVehicleReplacement.current_ve_plate_no'    => fn ($item) => $item->current_ve_plate_no,
                'RentalVehicleReplacement.new_ve_plate_no'        => fn ($item) => $item->new_ve_plate_no,
                'RentalVehicleReplacement.replacement_date'       => fn ($item) => $item->replacement_date,
                'RentalVehicleReplacement.replacement_start_date' => fn ($item) => $item->replacement_start_date,
                'RentalVehicleReplacement.replacement_end_date'   => fn ($item) => $item->replacement_end_date,
                'RentalVehicleReplacement.replacement_status'     => fn ($item) => $item->replacement_status_label,
                'RentalVehicleReplacement.vr_remark'              => fn ($item) => $item->vr_remark,
            ],

            RentalSaleSettlementController::class => [
                'RentalCustomer.contact_name'                     => fn ($item) => $item->contact_name,
                'RentalCustomer.contact_phone'                    => fn ($item) => $item->contact_phone,
                'RentalVehicle.plate_no'                          => fn ($item) => $item->plate_no,
                'RentalVehicleModel.brand_model'                  => fn ($item) => $item->brand_name.'-'.$item->model_name,
                'RentalSaleSettlement.deposit_amount'             => fn ($item) => $item->deposit_amount,
                'RentalSaleSettlement.received_deposit'           => fn ($item) => $item->received_deposit,
                'RentalSaleSettlement.early_return_penalty'       => fn ($item) => $item->early_return_penalty,
                'RentalSaleSettlement.overdue_inspection_penalty' => fn ($item) => $item->overdue_inspection_penalty,
                'RentalSaleSettlement.overdue_rent'               => fn ($item) => $item->overdue_rent,
                'RentalSaleSettlement.overdue_penalty'            => fn ($item) => $item->overdue_penalty,
                'RentalSaleSettlement.accident_depreciation_fee'  => fn ($item) => $item->accident_depreciation_fee,
                'RentalSaleSettlement.insurance_surcharge'        => fn ($item) => $item->insurance_surcharge,
                'RentalSaleSettlement.violation_withholding_fee'  => fn ($item) => $item->violation_withholding_fee,
                'RentalSaleSettlement.violation_penalty'          => fn ($item) => $item->violation_penalty,
                'RentalSaleSettlement.repair_fee'                 => fn ($item) => $item->repair_fee,
                'RentalSaleSettlement.insurance_deductible'       => fn ($item) => $item->insurance_deductible,
                'RentalSaleSettlement.forced_collection_fee'      => fn ($item) => $item->forced_collection_fee,
                'RentalSaleSettlement.other_deductions'           => fn ($item) => $item->other_deductions,
                'RentalSaleSettlement.other_deductions_remark'    => fn ($item) => $item->other_deductions_remark,
                'RentalSaleSettlement.refund_amount'              => fn ($item) => $item->refund_amount,
                'RentalSaleSettlement.refund_details'             => fn ($item) => $item->refund_details,
                'RentalSaleSettlement.settlement_amount'          => fn ($item) => $item->settlement_amount,
                'RentalSaleSettlement.deposit_return_date'        => fn ($item) => $item->deposit_return_date,
                'RentalSaleSettlement.return_status'              => fn ($item) => $item->return_status_label,
                'RentalSaleSettlement.return_datetime'            => fn ($item) => $item->return_datetime_,
                'RentalSaleSettlement.ss_remark'                  => fn ($item) => $item->ss_remark,
            ],

            RentalInoutController::class => [
                'RentalInout.pa_name'                => fn ($item) => $item->pa_name,
                'RentalInout.io_type'                => fn ($item) => $item->io_type_label,
                'RentalCustomer.contact_name'        => fn ($item) => $item->_contact_name,
                'RentalPaymentType.pt_name'          => fn ($item) => $item->_pt_name,
                'RentalInout.occur_datetime'         => fn ($item) => $item->occur_datetime_,
                'RentalInout.occur_amount'           => fn ($item) => $item->occur_amount,
                'RentalInout.account_balance'        => fn ($item) => $item->account_balance,
                'RentalPayment.should_pay_date'      => fn ($item) => $item->_should_pay_date,
                'RentalPayment.should_pay_amount'    => fn ($item) => $item->_should_pay_amount,
                'RentalSaleOrder.contract_number'    => fn ($item) => $item->_contract_number,
                'RentalVehicle.plate_no'             => fn ($item) => $item->_plate_no,
                'RentalVehicleModel.brand_full_name' => fn ($item) => $item->_brand_full_name,
                'RentalPayment.rp_remark'            => fn ($item) => $item->rp_remark,
            ],
            RentalPaymentController::class => [
                'RentalSaleOrder.contract_number'    => fn ($item) => $item->contract_number,
                'RentalVehicle.plate_no'             => fn ($item) => $item->plate_no,
                'RentalVehicleModel.brand_model'     => fn ($item) => $item->brand_name.'-'.$item->model_name,
                'RentalCustomer.contact_name'        => fn ($item) => $item->contact_name,
                'RentalPaymentType.pt_name'          => fn ($item) => $item->pt_name,
                'RentalPayment.should_pay_date'      => fn ($item) => $item->should_pay_date,
                'RentalPayment.should_pay_amount'    => fn ($item) => $item->should_pay_amount,
                'RentalPayment.actual_pay_date'      => fn ($item) => $item->actual_pay_date,
                'RentalPayment.actual_pay_amount'    => fn ($item) => $item->actual_pay_amount,
                'RentalPayment.pay_status_label'     => fn ($item) => $item->pay_status_label,
                'RentalPayment.is_valid_label'       => fn ($item) => $item->is_valid_label,
                'RentalSaleOrder.order_status_label' => fn ($item) => $item->order_status_label,
                'RentalPayment.rp_remark'            => fn ($item) => $item->rp_remark,
            ],
        };
    }

    public static function check_request($request): bool
    {
        return 'excel' === $request->output;
    }

    public function export(Builder $query): array
    {
        $controllerClass = preg_replace('{Controller$}', '', class_basename($this->class));

        $filename = trans('controller.'.$controllerClass).trans('app.actions.index').'_'.uniqid().'.xlsx';

        // 初始化 Spreadsheet
        $spreadsheet = new Spreadsheet();

        $sheet = $spreadsheet->getActiveSheet();

        // 设置表头
        $columns = static::columns($this->class);

        $properties = trans('property');

        $columnIndex = 1;
        foreach ($columns as $column_title => $column_value) {
            $cell_address = Coordinate::stringFromColumnIndex($columnIndex).'1';
            $cell_value   = data_get($properties, $column_title);
            $sheet->setCellValue($cell_address, $cell_value);
            ++$columnIndex;
        }

        // 冻结首行并设置样式
        $sheet->freezePane('A2');
        //        $sheet->getStyle('A1:'.Coordinate::stringFromColumnIndex(count($columns)).'1')->getFont()->setBold(true);

        // 分块查询并写入数据
        $row = 2;
        $query->chunk(500, function ($items) use ($columns, $sheet, &$row) {
            //            $values($sheet, $items, $row);
            foreach ($items as $item) {
                $columnIndex = 1;
                foreach ($columns as $column_title => $column_value) {
                    $cell = Coordinate::stringFromColumnIndex($columnIndex++).$row;
                    $sheet->setCellValue($cell, $column_value($item));
                }

                ++$row;
            }
        });

        // 自动调整列宽（在所有数据写入后执行）
        foreach (range(1, count($columns)) as $colIdx) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($colIdx))
                ->setAutoSize(true)
            ;
        }

        // 写入输出流
        $writer = new Xlsx($spreadsheet);

        $path = 'share/'.$filename;

        $diskLocal = Storage::disk('local');

        $absPath = $diskLocal->path($path);

        $writer->save($absPath);

        return temporarySignStorageAppShare($absPath);
    }

    private static function str_render($info, $tpl): string
    {
        return view('excel.'.$tpl, ['info' => json_decode($info, true)])->render();
    }
}
