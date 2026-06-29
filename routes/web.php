<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Auth routes — redirect root to login if unauthenticated
|--------------------------------------------------------------------------
*/
Route::get('/', fn () => redirect()->route('dashboard'));

Route::middleware('guest')->group(function () {
    Route::get('/login', fn () => view('auth.login'))->name('login');
    Route::post('/login', [\App\Http\Controllers\Auth\AuthenticatedSessionController::class, 'store'])->name('login.post');
});

Route::post('/logout', [\App\Http\Controllers\Auth\AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

/*
|--------------------------------------------------------------------------
| PM (authenticated) routes
|--------------------------------------------------------------------------
*/
Route::middleware(['auth'])->group(function () {

    Route::get('/dashboard', fn () => view('dashboard'))->name('dashboard');

    // Clients
    Route::resource('clients', \App\Http\Controllers\ClientController::class);

    // Stores
    Route::resource('stores', \App\Http\Controllers\StoreController::class);

    // Assets
    Route::resource('assets', \App\Http\Controllers\AssetController::class);

    // Service Jobs
    Route::resource('jobs', \App\Http\Controllers\ServiceJobController::class);

    // Technicians
    Route::resource('technicians', \App\Http\Controllers\TechnicianController::class);

    // Reports
    Route::get('/reports', fn () => view('reports.index'))->name('reports.index');

});

/*
|--------------------------------------------------------------------------
| Technician guest flow — signed URL access, no auth required
|--------------------------------------------------------------------------
*/
Route::middleware(['signed'])->group(function () {
    Route::get('/job/{job}/start', [\App\Http\Controllers\Technician\JobFlowController::class, 'overview'])->name('technician.job.overview');
    Route::post('/job/{job}/start', [\App\Http\Controllers\Technician\JobFlowController::class, 'start'])->name('technician.job.start');
    Route::post('/job/{job}/complete', [\App\Http\Controllers\Technician\JobFlowController::class, 'complete'])->name('technician.job.complete');
});
