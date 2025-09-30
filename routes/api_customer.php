<?php

use App\Http\Controllers\Customer\_\AuthController;
use App\Http\Controllers\Customer\Rental\RentalPaymentController;
use App\Http\Controllers\Customer\Rental\RentalSaleOrderController;
use App\Http\Controllers\Customer\Rental\RentalVehicleAccidentController;
use App\Http\Controllers\Customer\Rental\RentalVehicleMaintenanceController;
use App\Http\Controllers\Customer\Rental\RentalVehicleManualViolationController;
use App\Http\Controllers\Customer\Rental\RentalVehicleRepairController;
use App\Http\Controllers\Customer\Rental\RentalVehicleReplacementController;
use App\Http\Controllers\Customer\Rental\RentalVehicleViolationController;
use App\Http\Controllers\Customer\Risk\RentalExpiryDriverController;
use App\Http\Controllers\Customer\Risk\RentalExpiryVehicleController;
use App\Http\Controllers\Customer\Risk\RentalVehicleScheduleController;
use App\Http\Middleware\TemporaryCustomer;
use Illuminate\Support\Facades\Route;

Route::prefix('no-auth')->group(callback: function () {
    Route::post('send-verification-code', [AuthController::class, 'sendVerificationCode']);
    Route::post('login', [AuthController::class, 'login']);

    if (config('setting.mock.enable')) {
        Route::put('mock', [AuthController::class, 'mock']);
    }
});

Route::group(['middleware' => [config('setting.mock.enable') ? TemporaryCustomer::class : 'auth:sanctum']], function () {
    Route::get('user', [AuthController::class, 'getUserInfo']);

    Route::resource('rental-sale-orders', RentalSaleOrderController::class)->only(['index']);
    Route::resource('rental-payments', RentalPaymentController::class)->only(['index']);
    Route::resource('rental-vehicle-accidents', RentalVehicleAccidentController::class)->only(['index']);
    Route::resource('rental-vehicle-replacement', RentalVehicleReplacementController::class)->only(['index']);
    Route::resource('rental-vehicle-maintenances', RentalVehicleMaintenanceController::class)->only(['index']);
    Route::resource('rental-vehicle-repairs', RentalVehicleRepairController::class)->only(['index']);
    Route::resource('rental-vehicle-manual-violations', RentalVehicleManualViolationController::class)->only(['index']);
    Route::resource('rental-vehicle-violations', RentalVehicleViolationController::class)->only(['index']);
    // 风控
    Route::resource('rental-vehicle-schedules', RentalVehicleScheduleController::class)->only('index');
    Route::resource('rental-expiry-drivers', RentalExpiryDriverController::class)->only('index');
    Route::resource('rental-expiry-vehicles', RentalExpiryVehicleController::class)->only('index');
});
