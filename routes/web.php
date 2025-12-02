<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LegalController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/privacy-policy', [LegalController::class, 'privacyPolicy'])->name('privacy.policy');
