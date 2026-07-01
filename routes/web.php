<?php

use App\Http\Controllers\AssetController;
use App\Http\Controllers\AssetQrController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\DisplayGroupController;
use App\Http\Controllers\EmailTemplateController;
use App\Http\Controllers\LabelSheetController;
use App\Http\Controllers\ServiceJobController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\SlaProfileController;
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

// Link-expired page — publicly accessible for expired/invalid technician links (US-09.4)
Route::get('/link-expired', fn () => view('technician.link-expired'))->name('technician.link-expired');

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
    Route::resource('sla-profiles', SlaProfileController::class);
    Route::resource('stores', StoreController::class);
    Route::resource('assets', AssetController::class);
    Route::resource('jobs', ServiceJobController::class);

    // Job transitions (US-08.3)
    Route::post('/jobs/{job}/invite', [ServiceJobController::class, 'invite'])->name('jobs.invite');

    // Validation review surface + submit (US-11.1)
    Route::get('/jobs/{job}/validate', [ServiceJobController::class, 'showValidation'])->name('jobs.validate-form');
    Route::post('/jobs/{job}/validate', [ServiceJobController::class, 'validate'])->name('jobs.validate');
    Route::post('/jobs/{job}/flag-remediation', [ServiceJobController::class, 'flagRemediation'])->name('jobs.flag-remediation');
    Route::post('/jobs/{job}/force-complete', [ServiceJobController::class, 'forceComplete'])->name('jobs.force-complete');

    // Job attachments (US-08.6)
    Route::post('/jobs/{job}/attachments', [ServiceJobController::class, 'storeAttachment'])->name('jobs.attachments.store');
    Route::get('/jobs/{job}/attachments/{attachment}/download', [ServiceJobController::class, 'downloadAttachment'])->name('jobs.attachments.download');
    Route::delete('/jobs/{job}/attachments/{attachment}', [ServiceJobController::class, 'destroyAttachment'])->name('jobs.attachments.destroy');

    // Before/after job photos — PM review (US-11.1)
    Route::get('/jobs/{job}/photos/{photo}/download', [ServiceJobController::class, 'downloadPhoto'])->name('jobs.photos.download');
    Route::resource('technicians', TechnicianController::class);

    // Invitation send (US-09.2) — rate-limited
    Route::get('/jobs/{job}/invite', [TechnicianController::class, 'inviteForm'])->name('jobs.invite-form');
    Route::post('/jobs/{job}/invite/send', [TechnicianController::class, 'sendInvitations'])
        ->middleware('throttle:invitation.send')
        ->name('jobs.invite.send');

    // Display Groups — nested under stores
    Route::resource('stores.display-groups', DisplayGroupController::class)
        ->except(['show'])
        ->parameters(['display-groups' => 'display_group']);

    // PDF label sheet for one or more assets
    Route::get('/assets/{asset}/label', [LabelSheetController::class, 'single'])
        ->name('assets.label');
    Route::post('/assets/labels', [LabelSheetController::class, 'batch'])
        ->name('assets.labels.batch');

    // Service history photo — no public route, scoped to this asset's own history (US-11.3)
    Route::get('/assets/{asset}/service-history/photo', [AssetController::class, 'downloadHistoryPhoto'])
        ->name('assets.service-history.photo');

    Route::get('/reports', fn () => view('reports.index'))->name('reports.index');

    // Settings (US-16.1)
    Route::get('/settings', [SettingsController::class, 'edit'])->name('settings.edit');
    Route::patch('/settings', [SettingsController::class, 'update'])->name('settings.update');

    // Email templates (US-16.2)
    Route::get('/settings/email-templates', [EmailTemplateController::class, 'index'])->name('email-templates.index');
    Route::get('/settings/email-templates/{slot}/edit', [EmailTemplateController::class, 'edit'])->name('email-templates.edit');
    Route::patch('/settings/email-templates/{slot}', [EmailTemplateController::class, 'update'])->name('email-templates.update');
    Route::get('/settings/email-templates/{slot}/preview', [EmailTemplateController::class, 'preview'])->name('email-templates.preview');
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
/*
|--------------------------------------------------------------------------
| QR asset lookup — public, unauthenticated, rate-limited (US-07.1, US-07.3)
| Threshold: 30 req/min per IP (documented in AppServiceProvider::configureRateLimiters)
|--------------------------------------------------------------------------
*/
Route::get('/qr/{assetCode}', [AssetQrController::class, 'lookup'])
    ->middleware('throttle:qr.lookup')
    ->name('assets.qr.lookup')
    ->where('assetCode', '[A-Za-z0-9\-_]{1,40}');

Route::middleware(['signed', 'throttle:guest.job'])->group(function () {
    // Accept/Decline invitation response (US-09.3)
    Route::get('/job/{job}/respond', [TechnicianController::class, 'inviteResponse'])
        ->name('technician.job.respond');

    Route::post('/job/{job}/accept', [TechnicianController::class, 'acceptInvite'])
        ->name('technician.job.accept');

    Route::post('/job/{job}/decline', [TechnicianController::class, 'declineInvite'])
        ->name('technician.job.decline');

    // Screen 1 — Job overview
    Route::get('/job/{job}/start', [JobFlowController::class, 'overview'])
        ->name('technician.job.overview');

    // Start checkpoint → Screen 2
    Route::post('/job/{job}/start', [JobFlowController::class, 'start'])
        ->name('technician.job.start');

    Route::post('/job/{job}/cancel-start', [JobFlowController::class, 'cancelStart'])
        ->name('technician.job.cancel-start');

    // Screen 2 — Before photos
    Route::get('/job/{job}/before-photos', [JobFlowController::class, 'beforePhotos'])
        ->name('technician.job.before-photos');

    // Screen 3 — Briefing & asset reference
    Route::get('/job/{job}/brief', [JobFlowController::class, 'brief'])
        ->name('technician.job.brief');

    // In-flow asset status update (Screen 3, rate-limited via throttle:guest.job)
    Route::post('/job/{job}/assets/{asset}/status', [JobFlowController::class, 'updateAssetStatus'])
        ->name('technician.job.asset-status');

    // Screen 4 — After photos & outcomes
    Route::get('/job/{job}/after-photos', [JobFlowController::class, 'afterPhotos'])
        ->name('technician.job.after-photos');

    // Photo upload — rate-limited (applies the photo.upload limiter inside the signed group)
    Route::post('/job/{job}/photos', [JobFlowController::class, 'uploadPhoto'])
        ->middleware('throttle:photo.upload')
        ->name('technician.job.photos');

    // Complete checkpoint → Screen 5
    Route::post('/job/{job}/complete', [JobFlowController::class, 'complete'])
        ->name('technician.job.complete');

    // Screen 5 — Summary
    Route::get('/job/{job}/summary', [JobFlowController::class, 'summary'])
        ->name('technician.job.summary');
});
