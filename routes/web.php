<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LegalController;
use App\Http\Controllers\Web\User\UserManagementController;


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

});