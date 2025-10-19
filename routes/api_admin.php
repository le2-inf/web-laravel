<?php

use App\Http\Controllers\Admin\_\HistoryController;
use App\Http\Controllers\Admin\_\LoginController;
use App\Http\Controllers\Admin\_\MockController;
use App\Http\Controllers\Admin\_\PasswordResetController;
use App\Http\Controllers\Admin\_\StatisticsController;
use App\Http\Controllers\Admin\Auth\AdminController;
use App\Http\Controllers\Admin\Auth\PermissionController;
use App\Http\Controllers\Admin\Auth\ProfileController;
use App\Http\Controllers\Admin\Auth\RoleController;
use App\Http\Controllers\Admin\Config\Configuration0Controller;
use App\Http\Controllers\Admin\Config\Configuration1Controller;
use App\Http\Controllers\Admin\Config\ImportController;
use App\Http\Controllers\Admin\Config\RentalCompanyController;
use App\Http\Controllers\Admin\Config\RentalDocTplController;
use App\Http\Controllers\Admin\Config\RentalPaymentTypeController;
use App\Http\Controllers\Admin\Config\RentalServiceCenterController;
use App\Http\Controllers\Admin\Customer\RentalCustomerController;
use App\Http\Controllers\Admin\Device\GpsDataController;
use App\Http\Controllers\Admin\Device\IotDeviceBindingController;
use App\Http\Controllers\Admin\File\StorageController;
use App\Http\Controllers\Admin\Payment\RentalInoutController;
use App\Http\Controllers\Admin\Payment\RentalPaymentAccountController;
use App\Http\Controllers\Admin\Payment\RentalPaymentController;
use App\Http\Controllers\Admin\Payment\RentalSaleOrderRentPaymentController;
use App\Http\Controllers\Admin\Payment\RentalSaleOrderSignPaymentController;
use App\Http\Controllers\Admin\Risk\RentalExpiryDriverController;
use App\Http\Controllers\Admin\Risk\RentalExpiryVehicleController;
use App\Http\Controllers\Admin\Risk\RentalVehicleForceTakeController;
use App\Http\Controllers\Admin\Risk\RentalVehicleInsuranceController;
use App\Http\Controllers\Admin\Risk\RentalVehicleScheduleController;
use App\Http\Controllers\Admin\Risk\RentalViolationCountController;
use App\Http\Controllers\Admin\Sale\RentalBookingOrderController;
use App\Http\Controllers\Admin\Sale\RentalBookingVehicleController;
use App\Http\Controllers\Admin\Sale\RentalSaleOrderCancelController;
use App\Http\Controllers\Admin\Sale\RentalSaleOrderController;
use App\Http\Controllers\Admin\Sale\RentalSaleOrderTplController;
use App\Http\Controllers\Admin\Sale\RentalSaleSettlementController;
use App\Http\Controllers\Admin\Sale\RentalVehiclePreparationController;
use App\Http\Controllers\Admin\Sale\RentalVehicleReplacementController;
use App\Http\Controllers\Admin\Vehicle\RentalOneAccountController;
use App\Http\Controllers\Admin\Vehicle\RentalVehicleAccidentController;
use App\Http\Controllers\Admin\Vehicle\RentalVehicleController;
use App\Http\Controllers\Admin\Vehicle\RentalVehicleInspectionController;
use App\Http\Controllers\Admin\Vehicle\RentalVehicleMaintenanceController;
use App\Http\Controllers\Admin\Vehicle\RentalVehicleManualViolationController;
use App\Http\Controllers\Admin\Vehicle\RentalVehicleModelController;
use App\Http\Controllers\Admin\Vehicle\RentalVehicleRepairController;
use App\Http\Controllers\Admin\Vehicle\RentalVehicleViolationController;
use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\TemporaryAdmin;
use Illuminate\Support\Facades\Route;

Route::prefix('no-auth')->group(function () {
    Route::post('/login', [LoginController::class, 'login']);
    Route::resource('password', PasswordResetController::class)->only(['store', 'update']);

    if (config('setting.mock.enable')) {
        Route::apiResource('/mock', MockController::class)->only(['index', 'update']);
    }

    Route::get('/storage/tmp/{filename}', [StorageController::class, 'downloadTmp'])->name('storage.tmp')->middleware('signed');
    Route::get('/storage/share/{filename}', [StorageController::class, 'downloadShare'])->name('storage.share')->middleware('signed');
});

Route::group(['middleware' => [config('setting.mock.enable') ? TemporaryAdmin::class : 'auth:sanctum', CheckPermission::class]], function () {
    //    Route::apiResource('file', FileController::class);
    //    Route::apiResource('file-name', FileNameController::class);

    Route::resource('statistics', StatisticsController::class)->only('index');

    Route::get('/history/{table}/{id}', HistoryController::class);

    Route::singleton('profile', ProfileController::class);

    Route::resource('admins', AdminController::class);

    Route::resource('permissions', PermissionController::class);

    Route::resource('roles', RoleController::class);

    Route::resource('rental-configuration-app', Configuration0Controller::class)->parameters(['rental-configuration-app' => 'configuration']);
    Route::resource('rental-configuration-system', Configuration1Controller::class)->parameters(['rental-configuration-system' => 'configuration']);

    Route::put('rental-doc-tpls/{rental_doc_tpl}/status', [RentalDocTplController::class, 'status']);
    Route::get('rental-doc-tpls/{rental_doc_tpl}/preview', [RentalDocTplController::class, 'preview']);
    Route::post('rental-doc-tpls/upload', [RentalDocTplController::class, 'upload']);
    Route::resource('rental-doc-tpls', RentalDocTplController::class);

    Route::singleton('rental-company', RentalCompanyController::class);

    Route::apiSingleton('rental-payment-types', RentalPaymentTypeController::class);

    Route::resource('rental-vehicle-models', RentalVehicleModelController::class);

    Route::resource('rental-one-accounts', RentalOneAccountController::class);

    Route::resource('rental-payment-accounts', RentalPaymentAccountController::class);

    Route::put('rental-sale-order-tpls/{rental_order_tpl}/status', [RentalSaleOrderTplController::class, 'status']);
    Route::post('rental-sale-order-tpls/upload', [RentalSaleOrderTplController::class, 'upload']);
    Route::resource('rental-sale-order-tpls', RentalSaleOrderTplController::class);

    Route::resource('rental-service-centers', RentalServiceCenterController::class);

    Route::get('rental-imports/template', [ImportController::class, 'template']);
    Route::post('rental-imports/upload', [ImportController::class, 'upload']);
    Route::singleton('rental-imports', ImportController::class);

    Route::post('rental-vehicles/upload', [RentalVehicleController::class, 'upload']);
    Route::resource('rental-vehicles', RentalVehicleController::class);

    Route::get('rental-vehicle-inspections/rental-sale-orders-option', [RentalVehicleInspectionController::class, 'rentalSaleOrdersOption']);
    Route::post('rental-vehicle-inspections/upload', [RentalVehicleInspectionController::class, 'upload']);
    Route::get('rental-vehicle-inspections/{rental_vehicle_inspection}/doc', [RentalVehicleInspectionController::class, 'doc']);
    Route::resource('rental-vehicle-inspections', RentalVehicleInspectionController::class);

    Route::resource('rental-vehicle-manual-violations', RentalVehicleManualViolationController::class);

    Route::resource('rental-vehicle-violations', RentalVehicleViolationController::class);

    Route::get('rental-vehicle-repairs/rental-sale-orders-option', [RentalVehicleRepairController::class, 'rentalSaleOrdersOption']);
    Route::post('rental-vehicle-repairs/upload', [RentalVehicleRepairController::class, 'upload']);
    Route::resource('rental-vehicle-repairs', RentalVehicleRepairController::class);

    Route::get('rental-vehicle-maintenances/rental-sale-orders-option', [RentalVehicleMaintenanceController::class, 'rentalSaleOrdersOption']);
    Route::post('rental-vehicle-maintenances/upload', [RentalVehicleMaintenanceController::class, 'upload']);
    Route::resource('rental-vehicle-maintenances', RentalVehicleMaintenanceController::class);

    Route::get('rental-vehicle-accidents/rental-sale-orders-option', [RentalVehicleAccidentController::class, 'rentalSaleOrdersOption']);
    Route::post('rental-vehicle-accidents/upload', [RentalVehicleAccidentController::class, 'upload']);
    Route::resource('rental-vehicle-accidents', RentalVehicleAccidentController::class);

    Route::post('rental-customers/upload', [RentalCustomerController::class, 'upload']);
    Route::resource('rental-customers', RentalCustomerController::class);

    Route::resource('rental-vehicle-preparations', RentalVehiclePreparationController::class)->only('index', 'create', 'store');

    Route::get('rental-sale-orders/rental-payments-option', [RentalSaleOrderController::class, 'rentalPaymentsOption']);
    Route::get('rental-sale-orders/{rental_sale_order}/doc', [RentalSaleOrderController::class, 'doc']);
    Route::get('rental-sale-order-tpls/{rental_sale_order_tpl}/generate', [RentalSaleOrderController::class, 'generate']);
    Route::post('rental-sale-orders/upload', [RentalSaleOrderController::class, 'upload']);
    Route::resource('rental-sale-orders', RentalSaleOrderController::class);

    Route::apiSingleton('rental-sale-orders.cancel', RentalSaleOrderCancelController::class);

    Route::post('rental-vehicle-replacement/upload', [RentalVehicleReplacementController::class, 'upload']);
    Route::resource('rental-vehicle-replacement', RentalVehicleReplacementController::class);

    Route::post('rental-sale-settlement/upload', [RentalSaleSettlementController::class, 'upload']);
    Route::get('rental-sale-settlement/{rental_sale_settlement}/doc', [RentalSaleSettlementController::class, 'doc']);
    Route::put('rental-sale-settlement/{rental_sale_settlement}/approve', [RentalSaleSettlementController::class, 'approve']);
    Route::resource('rental-sale-settlement', RentalSaleSettlementController::class);

    Route::post('rental-booking-vehicles/upload', [RentalBookingVehicleController::class, 'upload']);
    Route::put('rental-booking-vehicles/{rental_booking_vehicle}/status', [RentalBookingVehicleController::class, 'status']);
    Route::resource('rental-booking-vehicles', RentalBookingVehicleController::class);

    Route::get('rental-booking-orders/{rental_booking_vehicle}/generate', [RentalBookingOrderController::class, 'generate']);
    Route::resource('rental-booking-orders', RentalBookingOrderController::class);

    // sale
    Route::get('rental-payments/{rental_payment}/doc', [RentalPaymentController::class, 'doc']);
    Route::put('rental-payments/{rental_payment}/undo', [RentalPaymentController::class, 'undo']); // 退还
    Route::resource('rental-payments', RentalPaymentController::class);

    Route::resource('rental-inouts', RentalInoutController::class)->only('index');

    Route::get('rental-sale-order/{so_id}/sign-pay/create', [RentalSaleOrderSignPaymentController::class, 'create'])->where('so_id', '[0-9]+');
    Route::resource('rental-sale-order.sign-pay', RentalSaleOrderSignPaymentController::class)->only('create', 'store');

    Route::get('rental-sale-order/{so_id}/rent-pay/create', [RentalSaleOrderRentPaymentController::class, 'create'])->where('so_id', '[0-9]+');
    Route::resource('rental-sale-order.rent-pay', RentalSaleOrderRentPaymentController::class)->only('create', 'store');

    // risk
    Route::post('rental-vehicle-schedules/upload', [RentalVehicleScheduleController::class, 'upload']);
    Route::get('rental-vehicle-schedules/st_vehicle', [RentalVehicleScheduleController::class, 'st_vehicle']);
    Route::resource('rental-vehicle-schedules', RentalVehicleScheduleController::class);

    Route::post('rental-vehicle-insurances/upload', [RentalVehicleInsuranceController::class, 'upload']);
    Route::resource('rental-vehicle-insurances', RentalVehicleInsuranceController::class);

    Route::post('rental-vehicle-force-takes/upload', [RentalVehicleForceTakeController::class, 'upload']);
    Route::resource('rental-vehicle-force-takes', RentalVehicleForceTakeController::class);

    Route::resource('rental-expiry-drivers', RentalExpiryDriverController::class)->only('index');

    Route::resource('rental-violation-counts', RentalViolationCountController::class)->only('index');

    Route::resource('rental-expiry-vehicles', RentalExpiryVehicleController::class)->only('index');

    // device
    Route::resource('iot-device-bindings', IotDeviceBindingController::class);
    Route::get('gps-data/history/{vehicle}', [GpsDataController::class, 'history']);
});
