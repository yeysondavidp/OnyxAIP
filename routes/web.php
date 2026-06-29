<?php

use App\Http\Controllers\AssetController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\ServiceJobController;
use App\Http\Controllers\StoreController;
use App\Http\Controllers\Technician\JobFlowController;
use App\Http\Controllers\TechnicianController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Auth routes — redirect root to login if unauthenticated
|--------------------------------------------------------------------------
*/
Route::get('/', fn () => redirect()->route('dashboard'));

// Dev smoke test — verifies Livewire, Alpine, DB, and Tz helper
Route::get('/smoke', fn () => view('smoke'))->name('smoke');

Route::middleware('guest')->group(function () {
    Route::get('/login', fn () => view('auth.login'))->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.post');
});

Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
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
    Route::resource('clients', ClientController::class);

    // Stores
    Route::resource('stores', StoreController::class);

    // Assets
    Route::resource('assets', AssetController::class);

    // Service Jobs
    Route::resource('jobs', ServiceJobController::class);

    // Technicians
    Route::resource('technicians', TechnicianController::class);

    // Reports
    Route::get('/reports', fn () => view('reports.index'))->name('reports.index');

});

/*
|--------------------------------------------------------------------------
| Technician guest flow — signed URL access, no auth required
|--------------------------------------------------------------------------
*/
Route::middleware(['signed'])->group(function () {
    Route::get('/job/{job}/start', [JobFlowController::class, 'overview'])->name('technician.job.overview');
    Route::post('/job/{job}/start', [JobFlowController::class, 'start'])->name('technician.job.start');
    Route::post('/job/{job}/complete', [JobFlowController::class, 'complete'])->name('technician.job.complete');
});
