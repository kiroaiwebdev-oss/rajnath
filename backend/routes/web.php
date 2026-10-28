<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\Staff\StaffAuthController;
use App\Http\Controllers\Web;
use Illuminate\Support\Facades\Route;

// ---- Public website ----
Route::get('/', [Web\SiteController::class, 'home'])->name('home');
Route::get('/services', [Web\SiteController::class, 'services'])->name('services.index');
Route::get('/services/{slug}', [Web\SiteController::class, 'service'])->name('services.show');
Route::post('/enquiries', [Web\SiteController::class, 'enquire'])->middleware('throttle:enquiry')->name('enquiries.store');
Route::get('/enquiries/{ref}/received', [Web\SiteController::class, 'thanks'])->name('enquiry.thanks');
Route::get('/faq', [Web\SiteController::class, 'faq'])->name('faq');
Route::view('/about', 'public.about')->name('about');
Route::view('/contact', 'public.contact')->name('contact');
Route::view('/privacy', 'public.privacy')->name('privacy');
Route::view('/terms', 'public.terms')->name('terms');
Route::view('/offline', 'public.offline')->name('offline');
Route::view('/design-system', 'public.design-system')->name('design-system');
Route::get('/manifest.webmanifest', [Web\SiteController::class, 'manifest'])->name('manifest');
Route::get('/places/search', [Web\SiteController::class, 'places'])->middleware('throttle:places')->name('places.search');
Route::get('/ready', [HealthController::class, 'ready'])->name('ready');

// ---- Customer sign-in (email / mobile OTP) and account ----
Route::prefix('account')->name('account.')->group(function () {
    Route::get('sign-in', [Web\CustomerAuthController::class, 'show'])->name('sign-in');
    Route::post('sign-in', [Web\CustomerAuthController::class, 'start'])->middleware('throttle:otp');
    Route::get('verify', [Web\CustomerAuthController::class, 'showVerify'])->name('verify');
    Route::post('verify', [Web\CustomerAuthController::class, 'verify'])->middleware('throttle:otp');
    Route::post('verify/resend', [Web\CustomerAuthController::class, 'resend'])->middleware('throttle:otp')->name('verify.resend');

    Route::middleware('customer')->group(function () {
        Route::post('sign-out', [Web\CustomerAuthController::class, 'logout'])->name('sign-out');
        Route::get('/', [Web\AccountController::class, 'dashboard'])->name('dashboard');
        Route::get('profile', [Web\AccountController::class, 'editProfile'])->name('profile');
        Route::put('profile', [Web\AccountController::class, 'updateProfile']);
        Route::post('contact', [Web\AccountController::class, 'startContactChange'])->middleware('throttle:otp')->name('contact.start');
        Route::post('contact/confirm', [Web\AccountController::class, 'confirmContactChange'])->middleware('throttle:otp')->name('contact.confirm');
        Route::get('devices', [Web\AccountController::class, 'devices'])->name('devices');
        Route::delete('devices/sessions/{id}', [Web\AccountController::class, 'revokeSession'])->name('devices.session.revoke');
        Route::delete('devices/tokens/{id}', [Web\AccountController::class, 'revokeToken'])->whereNumber('id')->name('devices.token.revoke');
        Route::post('devices/sign-out-others', [Web\AccountController::class, 'revokeAllOthers'])->name('devices.others');
        Route::get('wallet', [Web\WalletController::class, 'show'])->name('wallet');
        Route::post('wallet/recharge', [Web\WalletController::class, 'recharge'])->middleware('throttle:payments')->name('wallet.recharge');
        Route::get('wallet/statement.csv', [Web\WalletController::class, 'statementCsv'])->name('wallet.statement');
        Route::get('payments/{payment}', [Web\WalletController::class, 'status'])->name('payments.show');
        Route::get('payments/{payment}/checkout', [Web\WalletController::class, 'checkout'])->name('payments.checkout');
        Route::post('payments/{payment}/confirm', [Web\WalletController::class, 'confirm'])->middleware('throttle:payments')->name('payments.confirm');
        Route::post('payments/{payment}/refresh', [Web\WalletController::class, 'refresh'])->middleware('throttle:payment-refresh')->name('payments.refresh');
        Route::get('payments/{payment}/receipt', [Web\WalletController::class, 'receipt'])->name('payments.receipt');
        Route::resource('birth-profiles', Web\BirthProfileController::class)->except('show')->parameters(['birth-profiles' => 'profile']);
    });
});

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
    Route::get('settings', [Admin\SettingsController::class, 'edit'])->name('settings.edit');
    Route::get('audit', [Admin\AuditController::class, 'index'])->name('audit.index');
    Route::get('audit/verify', [Admin\AuditController::class, 'verify'])->name('audit.verify');
    Route::get('providers', [Admin\ProvidersController::class, 'edit'])->name('providers.edit');

    Route::get('catalogue', [Admin\CatalogueController::class, 'index'])->name('catalogue.index');
    Route::get('catalogue/new', [Admin\CatalogueController::class, 'create'])->name('catalogue.create');
    Route::post('catalogue', [Admin\CatalogueController::class, 'store'])->name('catalogue.store');
    Route::get('catalogue/{ref}', [Admin\CatalogueController::class, 'edit'])->name('catalogue.edit');
    Route::put('catalogue/{ref}', [Admin\CatalogueController::class, 'update'])->name('catalogue.update');
    Route::put('catalogue/{ref}/status', [Admin\CatalogueController::class, 'setStatus'])->name('catalogue.status');

    Route::get('enquiries', [Admin\EnquiryController::class, 'index'])->name('enquiries.index');
    Route::get('enquiries/export', [Admin\EnquiryController::class, 'export'])->name('enquiries.export');
    Route::get('enquiries/{enquiry}', [Admin\EnquiryController::class, 'show'])->name('enquiries.show');
    Route::put('enquiries/{enquiry}', [Admin\EnquiryController::class, 'update'])->name('enquiries.update');

    Route::get('customers', [Admin\CustomerController::class, 'index'])->name('customers.index');
    Route::get('customers/{customer}', [Admin\CustomerController::class, 'show'])->name('customers.show');
    Route::put('customers/{customer}/status', [Admin\CustomerController::class, 'setStatus'])->name('customers.status');

    Route::get('faqs', [Admin\FaqController::class, 'index'])->name('faqs.index');
    Route::post('faqs', [Admin\FaqController::class, 'save'])->name('faqs.save');
    Route::delete('faqs/{id}', [Admin\FaqController::class, 'destroy'])->whereNumber('id')->name('faqs.destroy');

    Route::prefix('finance')->name('finance.')->controller(Admin\FinanceController::class)->group(function () {
        Route::get('/', 'overview')->name('overview');
        Route::post('verify', 'verify')->name('verify');
        Route::post('findings/{id}/resolve', 'resolveFinding')->whereNumber('id')->name('findings.resolve');
        Route::get('payments', 'payments')->name('payments');
        Route::get('payments/{payment}', 'payment')->name('payment');
        Route::post('payments/{payment}/recheck', 'recheck')->middleware('throttle:payment-refresh')->name('payment.recheck');
        Route::post('payments/{payment}/refund', 'requestRefund')->name('payment.refund');
        Route::get('refunds', 'refunds')->name('refunds');
        Route::get('disputes', 'disputes')->name('disputes');
        Route::get('packages', 'packages')->name('packages');
    });
    Route::get('payments-setup', [Admin\PaymentSetupController::class, 'edit'])->name('payments-setup.edit');

    // Sensitive changes: fresh password + TOTP confirmation required.
    Route::middleware('step.up')->group(function () {
        Route::post('team/invitations', [Admin\TeamController::class, 'invite'])->name('team.invite');
        Route::delete('team/invitations/{ref}', [Admin\TeamController::class, 'revokeInvitation'])->name('team.invitations.revoke');
        Route::put('team/{user}/roles', [Admin\TeamController::class, 'updateRoles'])->name('team.roles');
        Route::put('team/{user}/status', [Admin\TeamController::class, 'setStatus'])->name('team.status');
        Route::put('settings', [Admin\SettingsController::class, 'update'])->name('settings.update');
        Route::put('providers/mail', [Admin\ProvidersController::class, 'updateMail'])->name('providers.mail');
        Route::put('providers/sms', [Admin\ProvidersController::class, 'updateSms'])->name('providers.sms');
        Route::post('providers/mail/test', [Admin\ProvidersController::class, 'testMail'])->middleware('throttle:provider-test')->name('providers.mail.test');
        Route::post('providers/sms/test', [Admin\ProvidersController::class, 'testSms'])->middleware('throttle:provider-test')->name('providers.sms.test');
        Route::post('finance/refunds/{refund}/decide', [Admin\FinanceController::class, 'decideRefund'])->name('finance.refund.decide');
        Route::post('finance/packages', [Admin\FinanceController::class, 'savePackage'])->name('finance.packages.save');
        Route::put('payments-setup', [Admin\PaymentSetupController::class, 'update'])->name('payments-setup.update');
        Route::put('payments-setup/mode', [Admin\PaymentSetupController::class, 'updateMode'])->name('payments-setup.mode');
        Route::post('payments-setup/test', [Admin\PaymentSetupController::class, 'test'])->middleware('throttle:provider-test')->name('payments-setup.test');
        Route::post('catalogue/{ref}/variants', [Admin\CatalogueController::class, 'saveVariant'])->name('catalogue.variant');
    });
});

// Laravel's default "login" route name is used by the auth middleware for guests.
Route::redirect('/login', '/account/sign-in')->name('login');
