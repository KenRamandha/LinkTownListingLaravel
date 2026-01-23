<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LegalController;
use App\Http\Controllers\Web\User\UserManagementController;
use App\Http\Controllers\Web\ShiftController;


Route::get('/', function () {
    return redirect()->route('login');
});

Route::get('/privacy-policy', [LegalController::class, 'privacyPolicy'])->name('privacy.policy');

// Auth
Route::get('/login', [App\Http\Controllers\Web\Auth\LoginController::class, 'showLoginForm'])->name('login');
Route::post('/login', [App\Http\Controllers\Web\Auth\LoginController::class, 'login'])->name('login.post');
Route::post('/logout', [App\Http\Controllers\Web\Auth\LoginController::class, 'logout'])->name('logout');

/*
|--------------------------------------------------------------------------
| Route Website
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->group(function () {
    Route::get('/home', [App\Http\Controllers\Web\DashboardController::class, 'index'])->name('home');

    // user
    Route::get('/users', [UserManagementController::class, 'index'])->name('users.index');
    Route::get('/users/add', [UserManagementController::class, 'add'])->name('users.add');
    Route::post('/users', [UserManagementController::class, 'store'])->name('users.store');
    Route::get('/users/{id}/edit', [UserManagementController::class, 'edit'])->name('users.edit');
    Route::put('/users/{id}', [UserManagementController::class, 'update'])->name('users.update');
    Route::delete('/users/{id}', [UserManagementController::class, 'destroy'])->name('users.destroy');
    Route::get('/user/departments/{company_id}', [UserManagementController::class, 'getDepartmentsByCompany'])->name('user.departments');
    Route::get('/users/list', [UserManagementController::class, 'getList'])->name('users.list');

    // roles
    Route::get('/roles', [App\Http\Controllers\Web\RoleController::class, 'index'])->name('roles.index');
    Route::get('/roles/list', [App\Http\Controllers\Web\RoleController::class, 'getList'])->name('roles.list');

    // shift
    Route::get('/shift', [ShiftController::class, 'index'])->name('shift.index');
    Route::get('/shift/list', [ShiftController::class, 'getList'])->name('shift.list');
    Route::get('/shift/add', [ShiftController::class, 'add'])->name('shift.add');
    Route::post('/shift', [ShiftController::class, 'store'])->name('shift.store');
    Route::get('/shift/{id}/edit', [ShiftController::class, 'edit'])->name('shift.edit');
    Route::put('/shift/{id}', [ShiftController::class, 'update'])->name('shift.update');
    Route::delete('/shift/{id}', [ShiftController::class, 'destroy'])->name('shift.destroy');

    // shift mapping
    Route::get('/users/{userId}/shift-mapping', [UserManagementController::class, 'getMappings'])->name('users.shift-mapping.list');
    Route::post('/users/{userId}/shift-mapping', [UserManagementController::class, 'storeMapping'])->name('users.shift-mapping.store');
    Route::delete('/users/shift-mapping/{id}', [UserManagementController::class, 'destroyMapping'])->name('users.shift-mapping.destroy');

    // user attachments
    Route::get('/users/{userId}/attachments', [UserManagementController::class, 'getAttachments'])->name('users.attachments.list');
    Route::post('/users/{userId}/attachments', [UserManagementController::class, 'storeAttachment'])->name('users.attachments.store');
    Route::delete('/users/attachments/{id}', [UserManagementController::class, 'destroyAttachment'])->name('users.attachments.destroy');

});