<?php

use App\Domain\Astro\Gochar;
use App\Domain\Astro\Muhurat;
use App\Http\Controllers\Admin;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\ProviderWebhookController;
use App\Http\Controllers\RazorpayWebhookController;
use App\Http\Controllers\Staff\StaffAuthController;
use App\Http\Controllers\Web;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Route;

// ---- Public website ----
Route::get('/', [Web\SiteController::class, 'home'])->name('home');
Route::get('/services', [Web\SiteController::class, 'services'])->name('services.index');
Route::get('/services/{slug}', [Web\SiteController::class, 'service'])->name('services.show');
Route::post('/enquiries', [Web\SiteController::class, 'enquire'])->middleware('throttle:enquiry')->name('enquiries.store');
Route::get('/enquiries/{ref}/received', [Web\SiteController::class, 'thanks'])->name('enquiry.thanks');
Route::get('/faq', [Web\SiteController::class, 'faq'])->name('faq');
Route::get('/rashifal', [Web\ContentController::class, 'rashifal'])->name('rashifal');
Route::get('/rashifal/{kind}', [Web\ContentController::class, 'rashifal'])->whereIn('kind', ['nakshatra', 'chinese', 'numerology', 'tarot'])->name('rashifal.kind');
Route::get('/rashi/{sign}', [Web\ContentController::class, 'rashi'])->middleware('throttle:tools')->name('rashi');
Route::get('/gochar', [Web\JyotishController::class, 'gochar'])->middleware('throttle:tools')->name('gochar');
Route::get('/gochar/{planet}', [Web\JyotishController::class, 'planet'])->whereIn('planet', Gochar::PLANETS)->middleware('throttle:tools')->name('gochar.planet');
Route::get('/navagraha', [Web\JyotishController::class, 'navagraha'])->middleware('throttle:tools')->name('navagraha');
Route::get('/muhurat', [Web\JyotishController::class, 'muhuratIndex'])->name('muhurat.index');
Route::get('/muhurat/{event}', [Web\JyotishController::class, 'muhurat'])->whereIn('event', array_keys(Muhurat::EVENTS))->middleware('throttle:tools')->name('muhurat.show');
Route::get('/festivals', [Web\JyotishController::class, 'festivals'])->middleware('throttle:tools')->name('festivals');
Route::get('/assistant', [Web\AssistantController::class, 'page'])->name('assistant');
Route::post('/assistant/chat', [Web\AssistantController::class, 'chat'])->middleware('throttle:assistant')->name('assistant.chat');
Route::get('/calendar', [Web\ContentController::class, 'calendar'])->middleware('throttle:tools')->name('calendar');
Route::get('/gems', [Web\StoreController::class, 'index'])->name('store.index');
Route::get('/gems/{slug}', [Web\StoreController::class, 'show'])->name('store.show');
Route::post('/gems/buy', [Web\StoreController::class, 'buy'])->middleware(['customer', 'throttle:checkout'])->name('store.buy');
Route::get('/tools', [Web\ToolController::class, 'index'])->name('tools.index');
Route::permanentRedirect('/tools/match-making', '/tools/kundli-matching');
Route::get('/tools/{slug}', [Web\ToolController::class, 'show'])->name('tools.show');
Route::post('/tools/{slug}', [Web\ToolController::class, 'compute'])->middleware('throttle:tools')->name('tools.compute');
Route::get('/panchang', [Web\ToolController::class, 'panchang'])->middleware('throttle:tools')->name('panchang');
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
        Route::resource('birth-profiles', Web\BirthProfileController::class)->except('show')->parameters(['birth-profiles' => 'profile']);
        Route::get('palmistry', [Web\PalmController::class, 'index'])->name('palm.index');
        Route::get('palmistry/{ref}', [Web\PalmController::class, 'show'])->name('palm.show');
        Route::post('palmistry/{ref}/photos', [Web\PalmController::class, 'upload'])->middleware('throttle:checkout')->name('palm.upload');
        Route::get('bookings', [Web\BookingController::class, 'index'])->name('bookings');
        Route::post('bookings/{ref}/cancel', [Web\BookingController::class, 'cancel'])->name('bookings.cancel');
        Route::get('bookings/{ref}/reschedule', [Web\BookingController::class, 'rescheduleForm'])->name('bookings.reschedule');
        Route::post('bookings/{ref}/reschedule', [Web\BookingController::class, 'reschedule'])->middleware('throttle:checkout');
        Route::get('book/{variant}', [Web\BookingController::class, 'pick'])->name('book');
        Route::post('book/{variant}', [Web\BookingController::class, 'hold'])->middleware('throttle:checkout');
        Route::get('puja', [Web\CustomerOpsController::class, 'pujaIndex'])->name('puja.index');
        Route::get('puja/{ref}', [Web\CustomerOpsController::class, 'pujaShow'])->name('puja.show');
        Route::post('puja/{ref}/details', [Web\CustomerOpsController::class, 'pujaDetails'])->middleware('throttle:checkout')->name('puja.details');
        Route::get('reports', [Web\CustomerOpsController::class, 'reportIndex'])->name('reports.index');
        Route::get('reports/{ref}', [Web\CustomerOpsController::class, 'reportShow'])->name('reports.show');
        Route::post('reports/{ref}/details', [Web\CustomerOpsController::class, 'reportDetails'])->middleware('throttle:checkout')->name('reports.details');
        Route::get('support', [Web\CustomerOpsController::class, 'supportIndex'])->name('support.index');
        Route::post('support', [Web\CustomerOpsController::class, 'supportStore'])->middleware('throttle:checkout')->name('support.store');
        Route::get('support/{ref}', [Web\CustomerOpsController::class, 'supportShow'])->name('support.show');
        Route::post('support/{ref}/reply', [Web\CustomerOpsController::class, 'supportReply'])->middleware('throttle:checkout')->name('support.reply');
        Route::post('support/{ref}/close', [Web\CustomerOpsController::class, 'supportClose'])->name('support.close');
        Route::get('privacy', [Web\CustomerOpsController::class, 'privacy'])->name('privacy');
        Route::get('notifications', [Web\CustomerOpsController::class, 'notifications'])->name('notifications');
        Route::post('notifications/read-all', [Web\CustomerOpsController::class, 'readAllNotifications'])->name('notifications.read-all');
        Route::post('privacy/export', [Web\CustomerOpsController::class, 'export'])->middleware('throttle:checkout')->name('privacy.export');
        Route::post('privacy/delete', [Web\CustomerOpsController::class, 'destroy'])->middleware('throttle:checkout')->name('privacy.delete');
        Route::get('apply-astrologer', [Web\ApplyController::class, 'show'])->name('apply');
        Route::post('apply-astrologer', [Web\ApplyController::class, 'store'])->middleware('throttle:checkout');
        Route::post('apply-astrologer/{ref}/withdraw', [Web\ApplyController::class, 'withdraw'])->name('apply.withdraw');
        Route::get('wallet', [Web\PaymentController::class, 'wallet'])->name('wallet');
        Route::post('wallet/recharge', [Web\PaymentController::class, 'recharge'])->middleware('throttle:checkout')->name('wallet.recharge');
        Route::get('orders', [Web\PaymentController::class, 'orders'])->name('orders');
        Route::get('orders/{ref}', [Web\PaymentController::class, 'receipt'])->name('receipt');
        Route::get('invoices/{ref}', [Web\DocumentController::class, 'invoice'])->name('invoice');
        Route::get('invoices/{ref}/pdf', [Web\DocumentController::class, 'invoicePdf'])->middleware('throttle:checkout')->name('invoice.pdf');
        Route::get('reports/{ref}/pdf', [Web\DocumentController::class, 'reportPdf'])->middleware('throttle:checkout')->name('reports.pdf');
        Route::get('palmistry/{ref}/pdf', [Web\DocumentController::class, 'palmPdf'])->middleware('throttle:checkout')->name('palm.pdf');
    });
});

// Private palm photo (owner or authorised fulfilment staff with MFA session).
Route::get('/palm-photos/{ref}/{side}', [Web\PalmController::class, 'photo'])->middleware('auth')->name('palm.photo');

Route::middleware('auth')->group(function () {
    Route::get('/call/{ref}', [Web\CallController::class, 'page'])->name('call.join');
    Route::post('/call/{ref}/token', [Web\CallController::class, 'token'])->middleware('throttle:checkout')->name('call.token');
});
Route::get('/puja-photos/{ref}/{i}', [Web\CustomerOpsController::class, 'pujaPhoto'])->whereNumber('i')->middleware('auth')->name('puja.photo');

// ---- Checkout (customer) and Razorpay webhook ----
Route::middleware('customer')->prefix('checkout')->name('checkout.')->group(function () {
    Route::post('/', [Web\PaymentController::class, 'buy'])->middleware('throttle:checkout')->name('buy');
    Route::get('{ref}', [Web\PaymentController::class, 'show'])->name('show');
    Route::post('{ref}/verify', [Web\PaymentController::class, 'verify'])->name('verify');
    Route::post('{ref}/cancel', [Web\PaymentController::class, 'cancel'])->name('cancel');
});
Route::post('/webhooks/razorpay', RazorpayWebhookController::class)->withoutMiddleware([ValidateCsrfToken::class])->name('webhooks.razorpay');
Route::post('/webhooks/razorpayx', [ProviderWebhookController::class, 'razorpayx'])->withoutMiddleware([ValidateCsrfToken::class])->middleware('throttle:60,1')->name('webhooks.razorpayx');
Route::post('/webhooks/courier-tracking', [ProviderWebhookController::class, 'courier'])->withoutMiddleware([ValidateCsrfToken::class])->middleware('throttle:120,1')->name('webhooks.courier');

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
    Route::get('push', [Admin\IntegrationsController::class, 'push'])->name('push.edit');
    Route::get('calling', [Admin\CallingController::class, 'edit'])->name('calling.edit');
    Route::get('calling/google/callback', [Admin\CallingController::class, 'googleCallback'])->name('calling.google.callback');
    Route::get('assistant', [Admin\IntegrationsController::class, 'assistant'])->name('assistant.edit');
    Route::post('assistant/knowledge', [Admin\IntegrationsController::class, 'saveKnowledge'])->name('assistant.knowledge');
    Route::get('assistant/conversations/{ref}', [Admin\IntegrationsController::class, 'conversation'])->name('assistant.conversation');
    Route::get('health', Admin\HealthController::class)->name('health');
    Route::get('privacy', [Admin\PrivacyController::class, 'index'])->name('privacy.index');
    Route::get('privacy/{ref}', [Admin\PrivacyController::class, 'show'])->name('privacy.show');
    Route::post('privacy/{ref}', [Admin\PrivacyController::class, 'update'])->name('privacy.update');
    Route::get('privacy/{ref}/export', [Admin\PrivacyController::class, 'export'])->name('privacy.export');
    Route::get('practitioner-applications', [Admin\ApplicationController::class, 'index'])->name('applications.index');
    Route::get('coupons', [Admin\FinanceController::class, 'coupons'])->name('coupons.index');
    Route::get('finance/invoices/{ref}', [Admin\FinanceController::class, 'invoice'])->name('finance.invoice');
    Route::post('store/orders/{ref}/courier', [Admin\StoreAdminController::class, 'bookCourier'])->name('store.courier');
    Route::get('practitioners', [Admin\PractitionerController::class, 'index'])->name('practitioners.index');
    Route::get('practitioners/{ref}', [Admin\PractitionerController::class, 'show'])->name('practitioners.show');
    Route::post('practitioners/{ref}/review', [Admin\PractitionerController::class, 'review'])->name('practitioners.review');
    Route::post('practitioners/{ref}/commission', [Admin\PractitionerController::class, 'proposeCommission'])->name('practitioners.commission');
    Route::post('practitioners/{ref}/payout', [Admin\PractitionerController::class, 'proposePayout'])->name('practitioners.payout');
    Route::controller(Admin\PracticeController::class)->prefix('practice')->name('practice.')->group(function () {
        Route::get('/', 'show')->name('show');
        Route::put('profile', 'saveProfile')->name('profile');
        Route::put('payout', 'savePayout')->name('payout');
        Route::post('hours', 'saveHours')->name('hours');
        Route::delete('hours/{id}', 'deleteHours')->whereNumber('id')->name('hours.delete');
        Route::post('leave', 'addLeave')->name('leave');
        Route::put('bookings/{ref}', 'updateBooking')->name('booking');
        Route::get('statement', 'statement')->name('statement');
    });

    Route::get('catalogue', [Admin\CatalogueController::class, 'index'])->name('catalogue.index');
    Route::get('catalogue/new', [Admin\CatalogueController::class, 'create'])->name('catalogue.create');
    Route::post('catalogue', [Admin\CatalogueController::class, 'store'])->name('catalogue.store');
    Route::get('catalogue/{ref}', [Admin\CatalogueController::class, 'edit'])->name('catalogue.edit');
    Route::put('catalogue/{ref}', [Admin\CatalogueController::class, 'update'])->name('catalogue.update');
    Route::put('catalogue/{ref}/status', [Admin\CatalogueController::class, 'setStatus'])->name('catalogue.status');
    Route::put('catalogue/{ref}/translations/{locale}', [Admin\CatalogueController::class, 'saveTranslation'])->name('catalogue.translation');

    Route::get('enquiries', [Admin\EnquiryController::class, 'index'])->name('enquiries.index');
    Route::get('enquiries/export', [Admin\EnquiryController::class, 'export'])->name('enquiries.export');
    Route::get('enquiries/{enquiry}', [Admin\EnquiryController::class, 'show'])->name('enquiries.show');
    Route::put('enquiries/{enquiry}', [Admin\EnquiryController::class, 'update'])->name('enquiries.update');

    Route::get('customers', [Admin\CustomerController::class, 'index'])->name('customers.index');
    Route::get('customers/{customer}', [Admin\CustomerController::class, 'show'])->name('customers.show');
    Route::put('customers/{customer}/status', [Admin\CustomerController::class, 'setStatus'])->name('customers.status');

    Route::get('finance', [Admin\FinanceController::class, 'index'])->name('finance.index');
    Route::get('finance/orders/{ref}', [Admin\FinanceController::class, 'order'])->name('finance.order');
    Route::post('finance/orders/{ref}/refunds', [Admin\FinanceController::class, 'requestRefund'])->name('finance.refund.request');
    Route::post('finance/reconcile', [Admin\FinanceController::class, 'reconcile'])->name('finance.reconcile');
    Route::post('finance/findings/{id}/resolve', [Admin\FinanceController::class, 'resolveFinding'])->whereNumber('id')->name('finance.finding.resolve');
    Route::get('payments', [Admin\FinanceController::class, 'settings'])->name('payments.settings');
    Route::get('store/products', [Admin\StoreAdminController::class, 'products'])->name('store.products');
    Route::get('store/products/new', [Admin\StoreAdminController::class, 'edit'])->name('store.new');
    Route::get('store/products/{ref}', [Admin\StoreAdminController::class, 'edit'])->name('store.edit');
    Route::post('store/products', [Admin\StoreAdminController::class, 'save'])->name('store.create');
    Route::post('store/products/{ref}', [Admin\StoreAdminController::class, 'save'])->name('store.update');
    Route::get('store/orders', [Admin\StoreAdminController::class, 'orders'])->name('store.orders');
    Route::get('store/orders/{ref}', [Admin\StoreAdminController::class, 'order'])->name('store.order');
    Route::put('store/orders/{ref}/shipment', [Admin\StoreAdminController::class, 'updateShipment'])->name('store.shipment');
    Route::get('palmistry', [Admin\PalmAdminController::class, 'index'])->name('palm.index');
    Route::get('palmistry/{ref}', [Admin\PalmAdminController::class, 'show'])->name('palm.show');
    Route::post('palmistry/{ref}/claim', [Admin\PalmAdminController::class, 'claim'])->name('palm.claim');
    Route::post('palmistry/{ref}/report', [Admin\PalmAdminController::class, 'report'])->name('palm.report');
    Route::post('palmistry/{ref}/need-photos', [Admin\PalmAdminController::class, 'needPhotos'])->name('palm.need');
    Route::controller(Admin\OperationsController::class)->group(function () {
        Route::get('bookings', 'bookings')->name('ops.bookings');
        Route::post('bookings/rules', 'saveRule')->name('ops.rule');
        Route::delete('bookings/rules/{id}', 'deleteRule')->whereNumber('id')->name('ops.rule.delete');
        Route::post('bookings/leave', 'addLeave')->name('ops.leave');
        Route::put('bookings/{ref}', 'updateBooking')->name('ops.booking');
        Route::get('suppliers', 'suppliers')->name('ops.suppliers');
        Route::post('suppliers', 'saveSupplier')->name('ops.supplier');
        Route::get('pujas', 'pujas')->name('ops.pujas');
        Route::get('pujas/{ref}', 'puja')->name('ops.puja');
        Route::post('pujas/{ref}', 'pujaAction')->name('ops.puja.action');
        Route::get('reports', 'reports')->name('ops.reports');
        Route::get('reports/{ref}', 'report')->name('ops.report');
        Route::post('reports/{ref}', 'saveReport')->name('ops.report.save');
        Route::get('support', 'tickets')->name('ops.tickets');
        Route::get('support/{ref}', 'ticket')->name('ops.ticket');
        Route::post('support/{ref}', 'ticketReply')->name('ops.ticket.reply');
        Route::get('rashifal', 'horoscopes')->name('ops.horoscopes');
        Route::post('rashifal', 'saveHoroscope')->name('ops.horoscope');
        Route::post('rashifal/{id}/decision', 'decideHoroscope')->whereNumber('id')->name('ops.horoscope.decide');
    });
    Route::get('faqs', [Admin\FaqController::class, 'index'])->name('faqs.index');
    Route::post('faqs', [Admin\FaqController::class, 'save'])->name('faqs.save');
    Route::delete('faqs/{id}', [Admin\FaqController::class, 'destroy'])->whereNumber('id')->name('faqs.destroy');

    // Sensitive changes: fresh password + TOTP confirmation required.
    Route::middleware('step.up')->group(function () {
        Route::put('push', [Admin\IntegrationsController::class, 'savePush'])->name('push.save');
        Route::put('calling', [Admin\CallingController::class, 'update'])->name('calling.update');
        Route::post('calling/test', [Admin\CallingController::class, 'test'])->middleware('throttle:provider-test')->name('calling.test');
        Route::post('calling/google/connect', [Admin\CallingController::class, 'googleConnect'])->name('calling.google.connect');
        Route::post('push/test', [Admin\IntegrationsController::class, 'testPush'])->middleware('throttle:provider-test')->name('push.test');
        Route::put('assistant', [Admin\IntegrationsController::class, 'saveAssistant'])->name('assistant.save');
        Route::post('assistant/test', [Admin\IntegrationsController::class, 'testAssistant'])->middleware('throttle:provider-test')->name('assistant.test');
        Route::post('practitioners/invite', [Admin\PractitionerController::class, 'invite'])->name('practitioners.invite');
        Route::post('commission/{id}/decision', [Admin\PractitionerController::class, 'decideCommission'])->whereNumber('id')->name('practitioners.commission.decide');
        Route::post('payouts/{ref}', [Admin\PractitionerController::class, 'payoutAction'])->name('payouts.action');
        Route::post('team/invitations', [Admin\TeamController::class, 'invite'])->name('team.invite');
        Route::delete('team/invitations/{ref}', [Admin\TeamController::class, 'revokeInvitation'])->name('team.invitations.revoke');
        Route::put('team/{user}/roles', [Admin\TeamController::class, 'updateRoles'])->name('team.roles');
        Route::put('team/{user}/status', [Admin\TeamController::class, 'setStatus'])->name('team.status');
        Route::put('settings', [Admin\SettingsController::class, 'update'])->name('settings.update');
        Route::put('providers/mail', [Admin\ProvidersController::class, 'updateMail'])->name('providers.mail');
        Route::put('providers/sms', [Admin\ProvidersController::class, 'updateSms'])->name('providers.sms');
        Route::post('providers/mail/test', [Admin\ProvidersController::class, 'testMail'])->middleware('throttle:provider-test')->name('providers.mail.test');
        Route::post('providers/sms/test', [Admin\ProvidersController::class, 'testSms'])->middleware('throttle:provider-test')->name('providers.sms.test');
        Route::post('catalogue/{ref}/variants', [Admin\CatalogueController::class, 'saveVariant'])->name('catalogue.variant');
        Route::post('finance/refunds/{ref}/decision', [Admin\FinanceController::class, 'decideRefund'])->name('finance.refund.decide');
        Route::put('payments', [Admin\FinanceController::class, 'saveSettings'])->name('payments.save');
        Route::post('payments/packages', [Admin\FinanceController::class, 'savePackage'])->name('payments.package');
        Route::put('payments/tax', [Admin\FinanceController::class, 'saveTax'])->name('payments.tax');
        Route::put('payments/payouts', [Admin\FinanceController::class, 'savePayouts'])->name('payments.payouts');
        Route::put('payments/courier', [Admin\FinanceController::class, 'saveCourier'])->name('payments.courier');
        Route::post('coupons', [Admin\FinanceController::class, 'saveCoupon'])->name('coupons.save');
        Route::post('practitioner-applications/{ref}/decision', [Admin\ApplicationController::class, 'decide'])->name('applications.decide');
        Route::post('privacy/{ref}/delete', [Admin\PrivacyController::class, 'runDeletion'])->name('privacy.delete');
        Route::post('privacy/{ref}/hold', [Admin\PrivacyController::class, 'hold'])->name('privacy.hold');
    });
});

// Laravel's default "login" route name is used by the auth middleware for guests.
Route::redirect('/login', '/account/sign-in')->name('login');
