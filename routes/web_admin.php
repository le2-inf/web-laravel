<?php

use App\Http\Controllers\Admin\_\HomeController;
use App\Http\Controllers\Admin\Auth\AdminController;
use App\Http\Controllers\Admin\Auth\PermissionController;
use App\Http\Controllers\Admin\Auth\ProfileController;
use App\Http\Controllers\Admin\Auth\RoleController;
use App\Http\Controllers\Admin\Config\Configuration0Controller;
use App\Http\Controllers\Admin\Config\Configuration1Controller;
use App\Http\Middleware\CheckPermission;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('home');
});

Auth::routes(['register' => false]);
// Auth::routes();

// Web pages
Route::group(['middleware' => ['auth', CheckPermission::class]], function () {
    Route::get('/home', [HomeController::class, 'index'])->name('home');

    Route::controller(ProfileController::class)->group(function () {
        Route::get('/profile/edit', 'edit')->name('profile.edit');
        Route::put('/profile/update', 'update')->name('profile.update');
    });

    Route::resource('config0', Configuration0Controller::class)->parameters(['config0' => 'configuration']);

    Route::resource('config1', Configuration1Controller::class)->parameters(['config1' => 'configuration']);

    // Roles
    //        Route::resource('roles', AdminRoleController::class);

    // Admin
    Route::resource('admins', AdminController::class);

    // Permissions
    Route::resource('permissions', PermissionController::class);

    // Roles
    Route::resource('roles', RoleController::class);
});
