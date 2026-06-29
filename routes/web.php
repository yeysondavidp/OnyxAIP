<?php

use App\Http\Controllers\AssetController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\ServiceJobController;
use App\Http\Controllers\StoreController;
use App\Http\Controllers\Technician\JobFlowController;
use App\Http\Controllers\TechnicianController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Root redirect
|--------------------------------------------------------------------------
*/
Route::get('/', fn () => redirect()->route('dashboard'));

// Dev smoke test — verifies Livewire, Alpine, DB, and Tz helper.
Route::get('/smoke', fn () => view('smoke'))->name('smoke');

/*
|--------------------------------------------------------------------------
| Guest-only auth routes (login + password reset)
|
| Middleware groups:
|   guest       — redirects authenticated users away (avoids double-login)
|   throttle:X  — references named limiters in bootstrap/app.php
|--------------------------------------------------------------------------
*/
Route::middleware('guest')->group(function () {
    Route::get('/login', fn () => view('auth.login'))->name('login');

    Route::post('/login', [AuthenticatedSessionController::class, 'store'])
        ->middleware('throttle:login')
        ->name('login.post');

    // Password reset — send link
    Route::get('/forgot-password', [ForgotPasswordController::class, 'create'])
        ->name('password.request');

    Route::post('/forgot-password', [ForgotPasswordController::class, 'store'])
        ->middleware('throttle:password.reset')
        ->name('password.email');

    // Password reset — set new password via signed token link
    Route::get('/reset-password/{token}', [NewPasswordController::class, 'create'])
        ->name('password.reset');

    Route::post('/reset-password', [NewPasswordController::class, 'store'])
        ->middleware('throttle:password.reset')
        ->name('password.update');
});

Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

/*
|--------------------------------------------------------------------------
| PM portal routes
|
| Middleware groups:
|   auth         — must be authenticated (redirects to login otherwise)
|   role:pm      — must hold the PM role; non-PM authenticated users → 403
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'role:pm'])->group(function () {

    Route::get('/dashboard', fn () => view('dashboard'))->name('dashboard');

    Route::resource('clients', ClientController::class);
    Route::resource('stores', StoreController::class);
    Route::resource('assets', AssetController::class);
    Route::resource('jobs', ServiceJobController::class);
    Route::resource('technicians', TechnicianController::class);

    Route::get('/reports', fn () => view('reports.index'))->name('reports.index');
});

/*
|--------------------------------------------------------------------------
| Technician guest flow
|
| Middleware groups:
|   signed           — validates Laravel signed URL signature + expiry
|   throttle:guest.job — named limiter from bootstrap/app.php
|--------------------------------------------------------------------------
*/
Route::middleware(['signed', 'throttle:guest.job'])->group(function () {
    Route::get('/job/{job}/start', [JobFlowController::class, 'overview'])
        ->name('technician.job.overview');

    Route::post('/job/{job}/start', [JobFlowController::class, 'start'])
        ->name('technician.job.start');

    Route::post('/job/{job}/complete', [JobFlowController::class, 'complete'])
        ->name('technician.job.complete');
});
