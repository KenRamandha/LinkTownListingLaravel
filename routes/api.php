<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\AuthController;
use App\Http\Controllers\MeController;
use App\Http\Controllers\MenuController;
use App\Http\Controllers\PublicSalesController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\SalesOrderController;
use App\Http\Controllers\HomeController;

Route::get('/ping', fn() => response()->json(['success' => true, 'message' => 'pong']));

// ===== PUBLIC =====
Route::middleware('optional.auth')->group(function () {
    Route::get('/home', [HomeController::class, 'index']);
    Route::get('/menus/{key}', [MenuController::class, 'showPublicAware']);
    Route::get('/sales/public-listings', [PublicSalesController::class, 'index']);
});

// ===== AUTH (wajib token) =====
Route::post('/auth/login', [AuthController::class, 'login']);
Route::post('/auth/logout', [AuthController::class, 'logout'])->middleware('auth');

Route::middleware('auth')->group(function () {
    Route::get('/me', [MeController::class, 'show']);
    Route::get('/permissions', [MeController::class, 'permissions']);

    // Attendance
    Route::post('/attendance/clock', [AttendanceController::class, 'clock']);
    Route::get('/attendance/logs', [AttendanceController::class, 'logs']);

    // Sales
    Route::get('/sales/orders', [SalesOrderController::class, 'index']);
    Route::post('/sales/orders', [SalesOrderController::class, 'store']);
});
