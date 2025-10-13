<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\AuthController;
use App\Http\Controllers\MeController;
use App\Http\Controllers\MenuController;
use App\Http\Controllers\PublicSalesController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\SalesOrderController;
use App\Http\Controllers\HomeController;


// ===== PUBLIC (token opsional via middleware `optional.auth`) =====
Route::middleware('optional.auth')->group(function () {
    Route::get('/home', [HomeController::class, 'index']);
    Route::get('/menus/{key}', [MenuController::class, 'showPublicAware']);
    Route::get('/sales/public-listings', [PublicSalesController::class, 'index']);
});

Route::post('/auth/login', [AuthController::class, 'login']);
Route::post('/auth/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
Route::post('/auth/refresh', [AuthController::class, 'refresh'])->middleware('auth:sanctum');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [MeController::class, 'show']);
    Route::get('/permissions', [MeController::class, 'permissions']);
    Route::get('/me/profile', [MeController::class, 'profile']);
    Route::put('/me/profile', [MeController::class, 'updateProfile']);

    // ----- Attendance -----
    /**
     * POST /api/attendance/clock
     * Body JSON (valid):
     *   {
     *     "type":"clock_in|clock_out|break_out|break_in",
     *     "latitude": number|null,
     *     "longitude": number|null,
     *     "photo_url": string|null,
     *     "video_url": string|null,
     *     "device_info": string|null,
     *     "geofence_id": string|null
     *   }
     * Contoh:
     *   curl -s -X POST http://localhost:8000/api/attendance/clock \
     *     -H 'Authorization: Bearer <token>' -H 'Content-Type: application/json' \
     *     -d '{"type":"clock_in","latitude":-6.2,"longitude":106.8}'
     */
    Route::post('/attendance/clock', [AttendanceController::class, 'clock']);
    Route::get('/attendance/allowed-locations', [AttendanceController::class, 'allowedLocations']);
    Route::get('/attendance/overview', [AttendanceController::class, 'overview']);

    /**
     * GET /api/attendance/logs?date=YYYY-MM-DD
     * Ambil log absensi user pada tanggal tertentu (default: hari ini).
     * Contoh:
     *   curl -s 'http://localhost:8000/api/attendance/logs?date=2025-01-01' \
     *     -H 'Authorization: Bearer <token>'
     */
    Route::get('/attendance/logs', [AttendanceController::class, 'logs']);
    Route::get('/sales/orders', [SalesOrderController::class, 'index']);
    Route::post('/sales/orders', [SalesOrderController::class, 'store']);

    /**
     * SALES: Customers
     */
    Route::get('/sales/customers', [\App\Http\Controllers\SalesCustomersController::class, 'index']);
    Route::post('/sales/customers', [\App\Http\Controllers\SalesCustomersController::class, 'store']);
    Route::get('/sales/customers/{id}', [\App\Http\Controllers\SalesCustomersController::class, 'show']);
    Route::put('/sales/customers/{id}', [\App\Http\Controllers\SalesCustomersController::class, 'update']);
    Route::delete('/sales/customers/{id}', [\App\Http\Controllers\SalesCustomersController::class, 'destroy']);

    /**
     * SALES: Properties & Units
     */
    Route::get('/sales/properties', [\App\Http\Controllers\SalesPropertiesController::class, 'index']);
    Route::post('/sales/properties', [\App\Http\Controllers\SalesPropertiesController::class, 'store']);
    Route::get('/sales/properties/{id}', [\App\Http\Controllers\SalesPropertiesController::class, 'show']);
    Route::put('/sales/properties/{id}', [\App\Http\Controllers\SalesPropertiesController::class, 'update']);
    Route::delete('/sales/properties/{id}', [\App\Http\Controllers\SalesPropertiesController::class, 'destroy']);
    Route::get('/sales/properties/{id}/units', [\App\Http\Controllers\SalesUnitsController::class, 'indexByProperty']);
    Route::post('/sales/properties/{id}/units', [\App\Http\Controllers\SalesUnitsController::class, 'storeForProperty']);
    Route::put('/sales/units/{unitId}', [\App\Http\Controllers\SalesUnitsController::class, 'update']);
    Route::delete('/sales/units/{unitId}', [\App\Http\Controllers\SalesUnitsController::class, 'destroy']);

    /**
     * SALES: Listings
     */
    Route::get('/sales/listings', [\App\Http\Controllers\SalesListingsController::class, 'index']);
    Route::post('/sales/listings', [\App\Http\Controllers\SalesListingsController::class, 'store']);
    Route::get('/sales/listings/{id}', [\App\Http\Controllers\SalesListingsController::class, 'show']);
    Route::put('/sales/listings/{id}', [\App\Http\Controllers\SalesListingsController::class, 'update']);
    Route::delete('/sales/listings/{id}', [\App\Http\Controllers\SalesListingsController::class, 'destroy']);

    /**
     * SALES: Orders detail & actions
     */
    Route::get('/sales/orders/{id}', [SalesOrderController::class, 'show']);
    Route::put('/sales/orders/{id}', [SalesOrderController::class, 'update']);
    Route::post('/sales/orders/{id}/confirm', [SalesOrderController::class, 'confirm']);
    Route::post('/sales/orders/{id}/cancel', [SalesOrderController::class, 'cancel']);

    /**
     * SALES: Contracts, Invoices, Payments
     */
    Route::get('/sales/contracts', [\App\Http\Controllers\ContractsController::class, 'index']);
    Route::post('/sales/contracts', [\App\Http\Controllers\ContractsController::class, 'store']);
    Route::get('/sales/contracts/{id}', [\App\Http\Controllers\ContractsController::class, 'show']);
    Route::put('/sales/contracts/{id}', [\App\Http\Controllers\ContractsController::class, 'update']);

    Route::get('/sales/invoices', [\App\Http\Controllers\InvoicesController::class, 'index']);
    Route::post('/sales/invoices', [\App\Http\Controllers\InvoicesController::class, 'store']);
    Route::get('/sales/invoices/{id}', [\App\Http\Controllers\InvoicesController::class, 'show']);
    Route::put('/sales/invoices/{id}', [\App\Http\Controllers\InvoicesController::class, 'update']);

    Route::get('/sales/payments', [\App\Http\Controllers\PaymentsController::class, 'index']);
    Route::post('/sales/payments', [\App\Http\Controllers\PaymentsController::class, 'store']);
    Route::get('/sales/payments/{id}', [\App\Http\Controllers\PaymentsController::class, 'show']);
    Route::put('/sales/payments/{id}', [\App\Http\Controllers\PaymentsController::class, 'update']);

    /**
     * ATTENDANCE: Geofences, Shifts, Schedules, Leave, Overtime
     */
    Route::get('/attendance/geofences', [\App\Http\Controllers\AttendanceGeofencesController::class, 'index']);
    Route::post('/attendance/geofences', [\App\Http\Controllers\AttendanceGeofencesController::class, 'store']);
    Route::put('/attendance/geofences/{id}', [\App\Http\Controllers\AttendanceGeofencesController::class, 'update']);
    Route::delete('/attendance/geofences/{id}', [\App\Http\Controllers\AttendanceGeofencesController::class, 'destroy']);

    Route::get('/attendance/shifts', [\App\Http\Controllers\AttendanceShiftsController::class, 'index']);
    Route::post('/attendance/shifts', [\App\Http\Controllers\AttendanceShiftsController::class, 'store']);
    Route::put('/attendance/shifts/{id}', [\App\Http\Controllers\AttendanceShiftsController::class, 'update']);
    Route::delete('/attendance/shifts/{id}', [\App\Http\Controllers\AttendanceShiftsController::class, 'destroy']);

    Route::get('/attendance/schedules', [\App\Http\Controllers\AttendanceSchedulesController::class, 'index']);
    Route::post('/attendance/schedules', [\App\Http\Controllers\AttendanceSchedulesController::class, 'store']);
    Route::put('/attendance/schedules/{id}', [\App\Http\Controllers\AttendanceSchedulesController::class, 'update']);
    Route::delete('/attendance/schedules/{id}', [\App\Http\Controllers\AttendanceSchedulesController::class, 'destroy']);

    Route::get('/attendance/leave-types', [\App\Http\Controllers\LeaveTypesController::class, 'index']);
    Route::post('/attendance/leave-types', [\App\Http\Controllers\LeaveTypesController::class, 'store']);
    Route::put('/attendance/leave-types/{id}', [\App\Http\Controllers\LeaveTypesController::class, 'update']);
    Route::delete('/attendance/leave-types/{id}', [\App\Http\Controllers\LeaveTypesController::class, 'destroy']);

    Route::get('/attendance/leave-requests', [\App\Http\Controllers\LeaveRequestsController::class, 'index']);
    Route::post('/attendance/leave-requests', [\App\Http\Controllers\LeaveRequestsController::class, 'store']);
    Route::get('/attendance/leave-requests/{id}', [\App\Http\Controllers\LeaveRequestsController::class, 'show']);
    Route::put('/attendance/leave-requests/{id}', [\App\Http\Controllers\LeaveRequestsController::class, 'update']);
    Route::post('/attendance/leave-requests/{id}/approve', [\App\Http\Controllers\LeaveRequestsController::class, 'approve']);
    Route::post('/attendance/leave-requests/{id}/reject', [\App\Http\Controllers\LeaveRequestsController::class, 'reject']);

    Route::get('/attendance/overtimes', [\App\Http\Controllers\OvertimeRequestsController::class, 'index']);
    Route::post('/attendance/overtimes', [\App\Http\Controllers\OvertimeRequestsController::class, 'store']);
    Route::get('/attendance/overtimes/{id}', [\App\Http\Controllers\OvertimeRequestsController::class, 'show']);
    Route::put('/attendance/overtimes/{id}', [\App\Http\Controllers\OvertimeRequestsController::class, 'update']);
    Route::post('/attendance/overtimes/{id}/approve', [\App\Http\Controllers\OvertimeRequestsController::class, 'approve']);
    Route::post('/attendance/overtimes/{id}/reject', [\App\Http\Controllers\OvertimeRequestsController::class, 'reject']);

    /**
     * CORE: Roles, Modules, Features, Permissions master
     */
    Route::get('/roles', [\App\Http\Controllers\RolesController::class, 'index']);
    Route::post('/roles', [\App\Http\Controllers\RolesController::class, 'store']);
    Route::put('/roles/{id}', [\App\Http\Controllers\RolesController::class, 'update']);
    Route::delete('/roles/{id}', [\App\Http\Controllers\RolesController::class, 'destroy']);
    Route::get('/roles/{id}/permissions', [\App\Http\Controllers\RolesController::class, 'permissions']);
    Route::put('/roles/{id}/permissions', [\App\Http\Controllers\RolesController::class, 'setPermissions']);

    Route::get('/modules', [\App\Http\Controllers\ModulesController::class, 'index']);
    Route::post('/modules', [\App\Http\Controllers\ModulesController::class, 'store']);
    Route::put('/modules/{id}', [\App\Http\Controllers\ModulesController::class, 'update']);
    Route::delete('/modules/{id}', [\App\Http\Controllers\ModulesController::class, 'destroy']);

    Route::get('/features', [\App\Http\Controllers\FeaturesController::class, 'index']);
    Route::post('/features', [\App\Http\Controllers\FeaturesController::class, 'store']);
    Route::put('/features/{id}', [\App\Http\Controllers\FeaturesController::class, 'update']);
    Route::delete('/features/{id}', [\App\Http\Controllers\FeaturesController::class, 'destroy']);

    Route::get('/permissions/master', [\App\Http\Controllers\PermissionsMasterController::class, 'index']);

    /**
     * MENUS (admin)
     */
    Route::get('/menus', [\App\Http\Controllers\MenuAdminController::class, 'menus']);
    Route::post('/menus', [\App\Http\Controllers\MenuAdminController::class, 'createMenu']);
    Route::put('/menus/{id}', [\App\Http\Controllers\MenuAdminController::class, 'updateMenu']);
    Route::delete('/menus/{id}', [\App\Http\Controllers\MenuAdminController::class, 'deleteMenu']);
    Route::get('/menus/{id}/items', [\App\Http\Controllers\MenuAdminController::class, 'items']);
    Route::post('/menus/{id}/items', [\App\Http\Controllers\MenuAdminController::class, 'createItem']);
    Route::put('/menu-items/{itemId}', [\App\Http\Controllers\MenuAdminController::class, 'updateItem']);
    Route::delete('/menu-items/{itemId}', [\App\Http\Controllers\MenuAdminController::class, 'deleteItem']);

    /**
     * AUDIT LOGS
     */
    Route::get('/audit-logs', [\App\Http\Controllers\AuditLogsController::class, 'index']);

    /**
     * USERS (admin)
     */
    Route::get('/users', [\App\Http\Controllers\UsersController::class, 'index']);
    Route::post('/users', [\App\Http\Controllers\UsersController::class, 'store']);
    Route::get('/users/{id}', [\App\Http\Controllers\UsersController::class, 'show']);
    Route::put('/users/{id}', [\App\Http\Controllers\UsersController::class, 'update']);
    Route::delete('/users/{id}', [\App\Http\Controllers\UsersController::class, 'destroy']);
    Route::get('/users/{id}/roles', [\App\Http\Controllers\UsersController::class, 'roles']);
    Route::put('/users/{id}/roles', [\App\Http\Controllers\UsersController::class, 'setRoles']);
    Route::get('/users/{id}/permissions', [\App\Http\Controllers\UsersController::class, 'permissions']);
    Route::put('/users/{id}/permissions', [\App\Http\Controllers\UsersController::class, 'setPermissions']);
    Route::get('/users/{id}/profile', [\App\Http\Controllers\UsersController::class, 'profile']);
    Route::put('/users/{id}/profile', [\App\Http\Controllers\UsersController::class, 'updateProfile']);
});

/*
|==========================================================================
| API Spec v1 (berdasarkan DBML SuperApps)
|==========================================================================
| Catatan umum
| - Seluruh endpoint di bawah diasumsikan berada dalam group `auth:sanctum`
|   kecuali yang dinyatakan publik. Gunakan header:
|     Authorization: Bearer <access_token>
| - Konvensi permission key: <module>.<feature>.<action>
|   - action mengacu ke enum action_key: view|create|update|delete|approve|export|import
| - Tabel sistem (cache, jobs, failed_jobs, sessions) tidak diekspos sebagai API publik.
| - Endpoint list di bawah dapat diimplementasikan via controller masing-masing
|   (direkomendasikan REST: index, show, store, update, destroy) dan cek izin
|   dengan helper User::hasPermission($key).
|
| CORE (User, Role, Permission, Module/Feature, Menu)
| - Users
|   GET   /api/users                          // users:view
|   POST  /api/users                          // users:create
|   GET   /api/users/{id}                     // users:view
|   PUT   /api/users/{id}                     // users:update
|   DELETE/ api/users/{id}                    // users:delete
|   GET   /api/users/{id}/roles               // users:view
|   PUT   /api/users/{id}/roles               // users:update (set array role_id)
|   GET   /api/users/{id}/permissions         // users:view (eff perms)
|   PUT   /api/users/{id}/permissions         // users:update (override allow/deny)
|   GET   /api/users/{id}/profile             // users:view
|   PUT   /api/users/{id}/profile             // users:update
|
| - Roles
|   GET   /api/roles                          // roles:view
|   POST  /api/roles                          // roles:create
|   GET   /api/roles/{id}                     // roles:view
|   PUT   /api/roles/{id}                     // roles:update
|   DELETE/ api/roles/{id}                    // roles:delete
|   GET   /api/roles/{id}/permissions         // roles:view
|   PUT   /api/roles/{id}/permissions         // roles:update (set array permission_id + allow)
|
| - Modules & Features
|   GET   /api/modules                        // modules:view
|   POST  /api/modules                        // modules:create
|   GET   /api/modules/{id}                   // modules:view
|   PUT   /api/modules/{id}                   // modules:update
|   DELETE/ api/modules/{id}                  // modules:delete
|   GET   /api/features                       // features:view
|   POST  /api/features                       // features:create
|   GET   /api/features/{id}                  // features:view
|   PUT   /api/features/{id}                  // features:update
|   DELETE/ api/features/{id}                 // features:delete
|
| - Permissions (dibangun dari feature + action)
|   GET   /api/permissions/master             // permissions:view (master list)
|
| - Menus & Items
|   GET   /api/menus                          // menus:view
|   POST  /api/menus                          // menus:create
|   GET   /api/menus/{id}                     // menus:view
|   PUT   /api/menus/{id}                     // menus:update
|   DELETE/ api/menus/{id}                    // menus:delete
|   GET   /api/menus/{id}/items               // menu_items:view
|   POST  /api/menus/{id}/items               // menu_items:create
|   PUT   /api/menu-items/{item_id}           // menu_items:update
|   DELETE/ api/menu-items/{item_id}          // menu_items:delete
|   GET   /api/menus/{id}/visibility/roles    // menu visibility per role:view
|   PUT   /api/menus/{id}/visibility/roles    // menu visibility per role:update
|   GET   /api/menus/{id}/visibility/users    // menu visibility per user:view
|   PUT   /api/menus/{id}/visibility/users    // menu visibility per user:update
|   GET   /api/menus/{key}/tree               // menus:view (struktur menu final utk user aktif)
|
| ATTENDANCE
| - Geofences
|   GET   /api/attendance/geofences           // attendance.geofence:view
|   POST  /api/attendance/geofences           // attendance.geofence:create
|   GET   /api/attendance/geofences/{id}      // attendance.geofence:view
|   PUT   /api/attendance/geofences/{id}      // attendance.geofence:update
|   DELETE/ api/attendance/geofences/{id}     // attendance.geofence:delete
|
| - Work Shifts
|   GET   /api/attendance/shifts              // attendance.shift:view
|   POST  /api/attendance/shifts              // attendance.shift:create
|   PUT   /api/attendance/shifts/{id}         // attendance.shift:update
|   DELETE/ api/attendance/shifts/{id}        // attendance.shift:delete
|
| - Work Schedules
|   GET   /api/attendance/schedules           // attendance.schedule:view (by user/date range)
|   POST  /api/attendance/schedules           // attendance.schedule:create (bulk assign)
|   PUT   /api/attendance/schedules/{id}      // attendance.schedule:update
|   DELETE/ api/attendance/schedules/{id}     // attendance.schedule:delete
|
| - Clocking & Logs (sudah ada)
|   POST  /api/attendance/clock               // attendance.clock:create (clock_in/out/break)
|   GET   /api/attendance/logs                // attendance.log:view (?date=YYYY-MM-DD)
|
| - Leave
|   GET   /api/attendance/leave-types         // attendance.leave_type:view
|   POST  /api/attendance/leave-types         // attendance.leave_type:create
|   PUT   /api/attendance/leave-types/{id}    // attendance.leave_type:update
|   DELETE/ api/attendance/leave-types/{id}   // attendance.leave_type:delete
|   GET   /api/attendance/leave-requests      // attendance.leave:view (filter by user/status)
|   POST  /api/attendance/leave-requests      // attendance.leave:create
|   GET   /api/attendance/leave-requests/{id} // attendance.leave:view
|   PUT   /api/attendance/leave-requests/{id} // attendance.leave:update
|   POST  /api/attendance/leave-requests/{id}/approve // attendance.leave:approve
|   POST  /api/attendance/leave-requests/{id}/reject  // attendance.leave:approve
|
| - Overtime
|   GET   /api/attendance/overtimes           // attendance.overtime:view
|   POST  /api/attendance/overtimes           // attendance.overtime:create
|   GET   /api/attendance/overtimes/{id}      // attendance.overtime:view
|   PUT   /api/attendance/overtimes/{id}      // attendance.overtime:update
|   POST  /api/attendance/overtimes/{id}/approve // attendance.overtime:approve
|   POST  /api/attendance/overtimes/{id}/reject  // attendance.overtime:approve
|
| SALES (Property & General Sales)
| - Customers
|   GET   /api/sales/customers                // sales.customer:view
|   POST  /api/sales/customers                // sales.customer:create
|   GET   /api/sales/customers/{id}           // sales.customer:view
|   PUT   /api/sales/customers/{id}           // sales.customer:update
|   DELETE/ api/sales/customers/{id}          // sales.customer:delete
|
| - Properties & Units
|   GET   /api/sales/properties               // sales.property:view
|   POST  /api/sales/properties               // sales.property:create
|   GET   /api/sales/properties/{id}          // sales.property:view
|   PUT   /api/sales/properties/{id}          // sales.property:update
|   DELETE/ api/sales/properties/{id}         // sales.property:delete
|   GET   /api/sales/properties/{id}/units    // sales.property.unit:view
|   POST  /api/sales/properties/{id}/units    // sales.property.unit:create
|   PUT   /api/sales/units/{unit_id}          // sales.property.unit:update
|   DELETE/ api/sales/units/{unit_id}         // sales.property.unit:delete
|
| - Listings (publik sebagian sudah ada)
|   GET   /api/sales/listings                 // sales.listing:view
|   POST  /api/sales/listings                 // sales.listing:create
|   GET   /api/sales/listings/{id}            // sales.listing:view
|   PUT   /api/sales/listings/{id}            // sales.listing:update
|   DELETE/ api/sales/listings/{id}           // sales.listing:delete
|   GET   /api/sales/public-listings          // publik (sudah ada)
|
| - Sales Orders & Items (sudah ada dasar)
|   GET   /api/sales/orders                   // sales.order:view
|   POST  /api/sales/orders                   // sales.order:create
|   GET   /api/sales/orders/{id}              // sales.order:view (detail+items)
|   PUT   /api/sales/orders/{id}              // sales.order:update (status/notes)
|   POST  /api/sales/orders/{id}/confirm      // sales.order:approve
|   POST  /api/sales/orders/{id}/cancel       // sales.order:update
|
| - Contracts
|   GET   /api/sales/contracts                // sales.contract:view
|   POST  /api/sales/contracts                // sales.contract:create
|   GET   /api/sales/contracts/{id}           // sales.contract:view
|   PUT   /api/sales/contracts/{id}           // sales.contract:update
|
| - Invoices & Payments
|   GET   /api/sales/invoices                 // sales.invoice:view
|   POST  /api/sales/invoices                 // sales.invoice:create
|   GET   /api/sales/invoices/{id}            // sales.invoice:view
|   PUT   /api/sales/invoices/{id}            // sales.invoice:update
|   GET   /api/sales/payments                 // sales.payment:view
|   POST  /api/sales/payments                 // sales.payment:create
|   GET   /api/sales/payments/{id}            // sales.payment:view
|   PUT   /api/sales/payments/{id}            // sales.payment:update
|
| AUDIT
|   GET   /api/audit-logs                     // audit:view (filter by user/action/date)
|
| Catatan implementasi:
| - Gunakan `id` bertipe string (UUID v7/orderedUuid) untuk semua create.
| - Scope data berdasarkan `company_id` pada entity yang multi-company.
| - Validasi mengikuti tipe kolom di DBML dan enum terkait.
| - Cek izin pakai `$request->user()->hasPermission('<key>')` di tiap aksi.
| - Tambahkan indexing/pagination standar (?page, ?limit, ?q) pada list.
*/
