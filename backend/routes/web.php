<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\Staff\StaffAuthController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'public.home')->name('home');
Route::view('/design-system', 'public.design-system')->name('design-system');
Route::get('/ready', [HealthController::class, 'ready'])->name('ready');

// ---- Staff authentication ----
Route::prefix('staff')->name('staff.')->controller(StaffAuthController::class)->group(function () {
    Route::get('invitation/{token}', 'showInvitation')->name('invitation');
    Route::post('invitation/{token}', 'acceptInvitation')->middleware('throttle:staff-mfa')->name('invitation.accept');
    Route::get('login', 'showLogin')->name('login');
    Route::post('login', 'login')->middleware('throttle:staff-login');
    Route::get('mfa', 'showMfa')->name('mfa');
    Route::post('mfa', 'verifyMfa')->middleware('throttle:staff-mfa');
    Route::middleware(['auth', 'staff'])->group(function () {
        Route::get('step-up', 'showStepUp')->name('step-up');
        Route::post('step-up', 'stepUp')->middleware('throttle:staff-mfa');
        Route::post('logout', 'logout')->name('logout');
    });
});

// ---- Admin control centre ----
Route::prefix('admin')->name('admin.')->middleware(['auth', 'staff'])->group(function () {
    Route::get('/', Admin\DashboardController::class)->name('dashboard');

    Route::get('team', [Admin\TeamController::class, 'index'])->name('team.index');
    Route::middleware('step.up')->group(function () {
        Route::post('team/invitations', [Admin\TeamController::class, 'invite'])->name('team.invite');
        Route::delete('team/invitations/{ref}', [Admin\TeamController::class, 'revokeInvitation'])->name('team.invitations.revoke');
        Route::put('team/{user}/roles', [Admin\TeamController::class, 'updateRoles'])->name('team.roles');
        Route::put('team/{user}/status', [Admin\TeamController::class, 'setStatus'])->name('team.status');
        Route::put('settings', [Admin\SettingsController::class, 'update'])->name('settings.update');

        Route::get('providers', [Admin\ProvidersController::class, 'edit'])->name('providers.edit');
        Route::put('providers/mail', [Admin\ProvidersController::class, 'updateMail'])->name('providers.mail');
        Route::put('providers/sms', [Admin\ProvidersController::class, 'updateSms'])->name('providers.sms');
        Route::post('providers/mail/test', [Admin\ProvidersController::class, 'testMail'])->middleware('throttle:provider-test')->name('providers.mail.test');
        Route::post('providers/sms/test', [Admin\ProvidersController::class, 'testSms'])->middleware('throttle:provider-test')->name('providers.sms.test');
    });

    Route::get('settings', [Admin\SettingsController::class, 'edit'])->name('settings.edit');
    Route::get('audit', [Admin\AuditController::class, 'index'])->name('audit.index');
    Route::get('audit/verify', [Admin\AuditController::class, 'verify'])->name('audit.verify');
});

// Laravel's default "login" route name is used by the auth middleware for guests.
Route::redirect('/login', '/staff/login')->name('login');
