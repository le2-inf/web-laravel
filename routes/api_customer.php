<?php

use App\Http\Controllers\Customer\_\AuthController;
use App\Http\Controllers\Customer\Rental\PaymentController;
use App\Http\Controllers\Customer\Rental\SaleOrderController;
use App\Http\Controllers\Customer\Rental\VehicleAccidentController;
use App\Http\Controllers\Customer\Rental\VehicleMaintenanceController;
use App\Http\Controllers\Customer\Rental\VehicleManualViolationController;
use App\Http\Controllers\Customer\Rental\VehicleRepairController;
use App\Http\Controllers\Customer\Rental\VehicleReplacementController;
use App\Http\Controllers\Customer\Rental\VehicleViolationController;
use App\Http\Controllers\Customer\Risk\ExpiryDriverController;
use App\Http\Controllers\Customer\Risk\ExpiryVehicleController;
use App\Http\Controllers\Customer\Risk\VehicleScheduleController;
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

    Route::resource('sale-orders', SaleOrderController::class)->only(['index']);
    Route::resource('payments', PaymentController::class)->only(['index']);
    Route::resource('vehicle-accidents', VehicleAccidentController::class)->only(['index']);
    Route::resource('vehicle-replacement', VehicleReplacementController::class)->only(['index']);
    Route::resource('vehicle-maintenances', VehicleMaintenanceController::class)->only(['index']);
    Route::resource('vehicle-repairs', VehicleRepairController::class)->only(['index']);
    Route::resource('vehicle-manual-violations', VehicleManualViolationController::class)->only(['index']);
    Route::resource('vehicle-violations', VehicleViolationController::class)->only(['index']);
    // 风控
    Route::resource('vehicle-schedules', VehicleScheduleController::class)->only('index');
    Route::resource('expiry-drivers', ExpiryDriverController::class)->only('index');
    Route::resource('expiry-vehicles', ExpiryVehicleController::class)->only('index');
});
