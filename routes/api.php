<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Core\AuthController;
use App\Http\Controllers\Core\MeController;
use App\Http\Controllers\Core\HomeController;
use App\Http\Controllers\Core\RolesController;
use App\Http\Controllers\Core\ModulesController;
use App\Http\Controllers\Core\FeaturesController;
use App\Http\Controllers\Core\PermissionsMasterController;
use App\Http\Controllers\Core\UsersController;

use App\Http\Controllers\Menus\MenuController;
use App\Http\Controllers\Menus\MenuAdminController;

use App\Http\Controllers\Attendance\AttendanceController;
use App\Http\Controllers\Attendance\AttendanceGeofencesController;
use App\Http\Controllers\Attendance\AttendanceShiftsController;
use App\Http\Controllers\Attendance\AttendanceSchedulesController;

use App\Http\Controllers\Audit\AuditLogsController;
use App\Http\Controllers\Products\ProductController;
use App\Http\Controllers\Visits\VisitController;

use App\Http\Controllers\UserProduct\UserProductController;
use App\Http\Controllers\UserProduct\UserProductPublicController;
use App\Http\Controllers\UserProduct\MasterController;
use App\Http\Controllers\UserProduct\LocationController;

use App\Http\Controllers\Transaction\TransactionController;


Route::middleware('optional.auth')->group(function () {
    Route::get('/home', [HomeController::class, 'index']);
    Route::get('/menus/{key}', [MenuController::class, 'showPublicAware']);
    Route::get('/craxion-menus/{key}', [MenuController::class, 'showPublicAwareCraxion']);
    Route::get('/products/home', [ProductController::class, 'home']);
    Route::get('/products/search/filters', [ProductController::class, 'searchFilters']);
    Route::get('/provinces', [ProductController::class, 'provinces']);
    Route::get('/provinces/{provinceId}/cities', [ProductController::class, 'citiesByProvince'])->whereNumber('provinceId');
    Route::get('/products/search', [ProductController::class, 'search'])->name('products.search');
    Route::get('/cities/{cityId}/products/top', [ProductController::class, 'topByCity'])->whereNumber('cityId');
    Route::get('/products/{productId}', [ProductController::class, 'show']);

    // User Products Public API (similar to products API but using MsProduct models)
    Route::get('/user-products/home', [UserProductPublicController::class, 'home']);
    Route::get('/user-products/search/filters', [UserProductPublicController::class, 'searchFilters']);
    Route::get('/user-products/search', [UserProductPublicController::class, 'search'])->name('user-products.search');
    Route::get('/cities/{cityId}/user-products/top', [UserProductPublicController::class, 'topByCity'])->whereNumber('cityId');
    Route::get('/user-products/{productId}', [UserProductPublicController::class, 'show']);

    // Master data endpoints
    Route::get('/master/product-details', [MasterController::class, 'productDetails']);

    // Location endpoints
    Route::get('/locations/provinces', [LocationController::class, 'provinces']);
    Route::get('/locations/cities', [LocationController::class, 'cities']);
    Route::get('/locations/areas', [LocationController::class, 'areas']);
});

Route::post('/auth/login', [AuthController::class, 'login']);
Route::post('/auth/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
Route::post('/auth/refresh', [AuthController::class, 'refresh'])->middleware('auth:sanctum');
Route::post('/auth/change-password', [AuthController::class, 'changePassword'])->middleware('auth:sanctum');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [MeController::class, 'show']);
    Route::get('/permissions', [MeController::class, 'permissions']);
    Route::get('/me/profile', [MeController::class, 'profile']);
    Route::put('/me/profile', [MeController::class, 'updateProfile']);
    Route::post('/me/profile/photo', [MeController::class, 'updatePhoto']);

    // User Products
    Route::get('/user_product', [UserProductController::class, 'index']);
    Route::post('/user_product', [UserProductController::class, 'store']);
    Route::get('/user_product/{product_id}', [UserProductController::class, 'show']);
    Route::put('/user_product/{product_id}', [UserProductController::class, 'update']);
    Route::put('/user_product/{product_id}/publish', [UserProductController::class, 'publish']);
    Route::get('/user_product/{product_id}/lamudi/preview', [UserProductController::class, 'lamudiPreview']);
    Route::delete('/user_product/{product_id}', [UserProductController::class, 'destroy']);
    Route::delete('/user_product/images/{image_id}', [UserProductController::class, 'deleteImage']);

    Route::post('/attendance/clock', [AttendanceController::class, 'clock']);
    Route::get('/attendance/allowed-locations', [AttendanceController::class, 'allowedLocations']);
    Route::get('/attendance/overview', [AttendanceController::class, 'overview']);

    Route::get('/attendance/logs', [AttendanceController::class, 'logs']);
    Route::get('/attendance/history', [AttendanceController::class, 'history']);
    Route::get('/attendance/history/{mappingId}', [AttendanceController::class, 'historyDetail']);

    Route::get('/attendance/geofences', [AttendanceGeofencesController::class, 'index']);
    Route::post('/attendance/geofences', [AttendanceGeofencesController::class, 'store']);
    Route::put('/attendance/geofences/{id}', [AttendanceGeofencesController::class, 'update']);
    Route::delete('/attendance/geofences/{id}', [AttendanceGeofencesController::class, 'destroy']);

    Route::get('/attendance/shifts', [AttendanceShiftsController::class, 'index']);
    Route::post('/attendance/shifts', [AttendanceShiftsController::class, 'store']);
    Route::put('/attendance/shifts/{id}', [AttendanceShiftsController::class, 'update']);
    Route::delete('/attendance/shifts/{id}', [AttendanceShiftsController::class, 'destroy']);

    Route::get('/attendance/schedules', [AttendanceSchedulesController::class, 'index']);
    Route::post('/attendance/schedules', [AttendanceSchedulesController::class, 'store']);
    Route::put('/attendance/schedules/{id}', [AttendanceSchedulesController::class, 'update']);
    Route::delete('/attendance/schedules/{id}', [AttendanceSchedulesController::class, 'destroy']);

    Route::post('/visits/clock', [VisitController::class, 'clock']);
    Route::get('/visits/today', [VisitController::class, 'today']);
    Route::get('/visits/history', [VisitController::class, 'history']);
    Route::get('/visits/{visit}', [VisitController::class, 'show']);

    Route::get('/roles', [RolesController::class, 'index']);
    Route::post('/roles', [RolesController::class, 'store']);
    Route::put('/roles/{id}', [RolesController::class, 'update']);
    Route::delete('/roles/{id}', [RolesController::class, 'destroy']);
    Route::get('/roles/{id}/permissions', [RolesController::class, 'permissions']);
    Route::put('/roles/{id}/permissions', [RolesController::class, 'setPermissions']);

    Route::get('/modules', [ModulesController::class, 'index']);
    Route::post('/modules', [ModulesController::class, 'store']);
    Route::put('/modules/{id}', [ModulesController::class, 'update']);
    Route::delete('/modules/{id}', [ModulesController::class, 'destroy']);

    Route::get('/features', [FeaturesController::class, 'index']);
    Route::post('/features', [FeaturesController::class, 'store']);
    Route::put('/features/{id}', [FeaturesController::class, 'update']);
    Route::delete('/features/{id}', [FeaturesController::class, 'destroy']);

    Route::get('/permissions/master', [PermissionsMasterController::class, 'index']);

    Route::get('/menus', [MenuAdminController::class, 'menus']);
    Route::post('/menus', [MenuAdminController::class, 'createMenu']);
    Route::put('/menus/{id}', [MenuAdminController::class, 'updateMenu']);
    Route::delete('/menus/{id}', [MenuAdminController::class, 'deleteMenu']);
    Route::get('/menus/{id}/items', [MenuAdminController::class, 'items']);
    Route::post('/menus/{id}/items', [MenuAdminController::class, 'createItem']);
    Route::put('/menu-items/{itemId}', [MenuAdminController::class, 'updateItem']);
    Route::delete('/menu-items/{itemId}', [MenuAdminController::class, 'deleteItem']);

    Route::get('/audit-logs', [AuditLogsController::class, 'index']);

    Route::get('/users', [UsersController::class, 'index']);
    Route::post('/users', [UsersController::class, 'store']);
    Route::get('/users/{id}', [UsersController::class, 'show']);
    Route::put('/users/{id}', [UsersController::class, 'update']);
    Route::delete('/users/{id}', [UsersController::class, 'destroy']);
    Route::get('/users/{id}/roles', [UsersController::class, 'roles']);
    Route::put('/users/{id}/roles', [UsersController::class, 'setRoles']);
    Route::get('/users/{id}/permissions', [UsersController::class, 'permissions']);
    Route::put('/users/{id}/permissions', [UsersController::class, 'setPermissions']);
    Route::get('/users/{id}/profile', [UsersController::class, 'profile']);
    Route::put('/users/{id}/profile', [UsersController::class, 'updateProfile']);

    // Transactions
    Route::get('/transactions', [TransactionController::class, 'index']);
    Route::get('/transactions/{id}', [TransactionController::class, 'show']);
    Route::post('/transactions', [TransactionController::class, 'store']);
    Route::put('/transactions/{id}', [TransactionController::class, 'update']);
    Route::delete('/transactions/{id}', [TransactionController::class, 'destroy']);
    
    // Items (for transaction)
    Route::get('/items/search', [TransactionController::class, 'searchItems']);
    Route::get('/items/barcode/{barcode}', [TransactionController::class, 'getItemByBarcode']);
});
