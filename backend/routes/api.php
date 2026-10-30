<?php

use App\Domain\Assistant\Assistant;
use App\Domain\Astro\Tools;
use App\Domain\Notify\Fcm;
use App\Http\Controllers\Api\V1;
use App\Http\Controllers\Web\AssistantController;
use App\Http\Controllers\Web\CallController;
use App\Http\Middleware\SetLocale;
use Illuminate\Support\Facades\Route;

// Versioned API shared by the Flutter app. Docs: docs/api/openapi.yaml
Route::prefix('v1')->middleware('throttle:api')->group(function () {
    Route::get('meta', fn () => response()->json([
        'api_version' => 'v1',
        'brand' => config('brand')['brand.name'],
        'min_app_version' => config('app.min_mobile_version', '1.0.0'),
        'source_code_url' => config('brand')['source.repository_url'],
        'push' => ($fb = app(Fcm::class)->clientConfig()) ? ['firebase' => $fb] : null,
        'assistant' => ['enabled' => app(Assistant::class)->enabled()],
        'locales' => SetLocale::enabled(),
    ]))->name('api.v1.meta');
    Route::get('store/products', [V1\CommerceController::class, 'products']);
    Route::get('store/products/{slug}', [V1\CommerceController::class, 'productShow']);
    Route::get('practitioners', [V1\CommerceController::class, 'practitioners']);

    Route::prefix('auth/otp')->middleware('throttle:otp')->group(function () {
        Route::post('start', [V1\AuthController::class, 'start']);
        Route::post('resend', [V1\AuthController::class, 'resend']);
        Route::post('verify', [V1\AuthController::class, 'verify']);
    });

    Route::get('catalogue/families', [V1\CatalogueController::class, 'families']);
    Route::get('catalogue/offerings', [V1\CatalogueController::class, 'offerings']);
    Route::get('catalogue/offerings/{slug}', [V1\CatalogueController::class, 'offering']);
    Route::get('places', [V1\CatalogueController::class, 'places'])->middleware('throttle:places');
    Route::post('enquiries', [V1\EnquiryApiController::class, 'store'])->middleware('throttle:enquiry');
    Route::get('tools', fn () => response()->json(['data' => collect(Tools::TOOLS)->map(fn ($t, $k) => ['slug' => $k, 'title' => $t[0], 'input' => $t[1], 'kind' => $t[2], 'description' => $t[3]])->values()]));
    Route::post('tools/{slug}', [V1\ToolApiController::class, 'compute'])->middleware('throttle:tools');
    Route::get('panchang', [V1\ToolApiController::class, 'panchang'])->middleware('throttle:tools');
    Route::get('faq', [V1\ContentApiController::class, 'faq']);
    Route::get('site', [V1\ContentApiController::class, 'site']);
    Route::get('horoscopes/types', [V1\ContentApiController::class, 'horoscopeTypes']);
    Route::get('horoscopes', [V1\ContentApiController::class, 'horoscopes']);
    Route::middleware('throttle:tools')->group(function () {
        Route::get('gochar', [V1\ContentApiController::class, 'gochar']);
        Route::get('gochar/{planet}', [V1\ContentApiController::class, 'gocharPlanet']);
        Route::get('muhurat', [V1\ContentApiController::class, 'muhuratEvents']);
        Route::get('muhurat/{event}', [V1\ContentApiController::class, 'muhurat']);
        Route::get('festivals', [V1\ContentApiController::class, 'festivals']);
        Route::get('calendar', [V1\ContentApiController::class, 'calendar']);
        Route::get('rashi/{sign}', [V1\ContentApiController::class, 'rashi']);
        Route::get('navagraha', [V1\ContentApiController::class, 'navagraha']);
    });

    Route::middleware(['auth:sanctum', 'ability:customer', 'customer.api'])->group(function () {
        Route::post('auth/logout', [V1\AuthController::class, 'logout']);
        Route::get('me', [V1\MeController::class, 'show']);
        Route::patch('me', [V1\MeController::class, 'update']);
        Route::get('me/devices', [V1\MeController::class, 'devices']);
        Route::delete('me/devices/{id}', [V1\MeController::class, 'revokeDevice'])->whereNumber('id');
        Route::get('birth-profiles', [V1\BirthProfileController::class, 'index']);
        Route::post('birth-profiles', [V1\BirthProfileController::class, 'store']);
        Route::get('birth-profiles/{ref}', [V1\BirthProfileController::class, 'show']);
        Route::put('birth-profiles/{ref}', [V1\BirthProfileController::class, 'update']);
        Route::delete('birth-profiles/{ref}', [V1\BirthProfileController::class, 'destroy']);

        Route::post('push-tokens', [V1\NotificationController::class, 'register']);
        Route::delete('push-tokens', [V1\NotificationController::class, 'unregister']);
        Route::get('notifications', [V1\NotificationController::class, 'index']);
        Route::post('notifications/read-all', [V1\NotificationController::class, 'readAll']);
        Route::post('notifications/{id}/read', [V1\NotificationController::class, 'read'])->whereNumber('id');

        Route::get('wallet', [V1\CommerceController::class, 'wallet']);
        Route::get('slots', [V1\CommerceController::class, 'slots']);
        Route::middleware('throttle:checkout')->group(function () {
            Route::post('checkout/service', [V1\CommerceController::class, 'service']);
            Route::post('checkout/booking', [V1\CommerceController::class, 'booking']);
            Route::post('checkout/recharge', [V1\CommerceController::class, 'recharge']);
            Route::post('checkout/product', [V1\CommerceController::class, 'product']);
            Route::post('coupons/check', [V1\CommerceController::class, 'checkCoupon']);
            Route::post('orders/{ref}/verify', [V1\CommerceController::class, 'verify']);
            Route::post('orders/{ref}/cancel', [V1\CommerceController::class, 'cancel']);
            Route::post('bookings/{ref}/cancel', [V1\ActivityController::class, 'cancelBooking']);
            Route::post('bookings/{ref}/reschedule', [V1\ActivityController::class, 'rescheduleBooking']);
            Route::post('palmistry/{ref}/photos', [V1\ActivityController::class, 'palmUpload']);
            Route::post('reports/{ref}/details', [V1\ActivityController::class, 'reportDetails']);
            Route::post('puja/{ref}/details', [V1\ActivityController::class, 'pujaDetails']);
        });
        Route::get('orders', [V1\CommerceController::class, 'orders']);
        Route::get('orders/{ref}', [V1\CommerceController::class, 'show']);
        Route::middleware('throttle:checkout')->group(function () {
            Route::get('invoices/{ref}/pdf', [V1\DocumentApiController::class, 'invoicePdf']);
            Route::get('reports/{ref}/pdf', [V1\DocumentApiController::class, 'reportPdf']);
            Route::get('palmistry/{ref}/pdf', [V1\DocumentApiController::class, 'palmPdf']);
        });
        Route::get('bookings', [V1\ActivityController::class, 'bookings']);
        Route::post('bookings/{ref}/call-token', [CallController::class, 'token'])->middleware('throttle:checkout');
        Route::get('palmistry', [V1\ActivityController::class, 'palms']);
        Route::get('palmistry/{ref}', [V1\ActivityController::class, 'palmShow']);
        Route::get('reports', [V1\ActivityController::class, 'reports']);
        Route::get('reports/{ref}', [V1\ActivityController::class, 'reportShow']);
        Route::get('puja', [V1\ActivityController::class, 'pujas']);
        Route::get('puja/{ref}', [V1\ActivityController::class, 'pujaShow']);
        Route::get('practitioner-applications', [V1\ApplicationApiController::class, 'index']);
        Route::post('practitioner-applications', [V1\ApplicationApiController::class, 'store'])->middleware('throttle:checkout');
        Route::post('practitioner-applications/{ref}/withdraw', [V1\ApplicationApiController::class, 'withdraw']);
        Route::get('support', [V1\AccountApiController::class, 'tickets']);
        Route::get('support/{ref}', [V1\AccountApiController::class, 'showTicket']);
        Route::get('privacy', [V1\AccountApiController::class, 'privacy']);
        Route::middleware('throttle:checkout')->group(function () {
            Route::post('support', [V1\AccountApiController::class, 'openTicket']);
            Route::post('support/{ref}/reply', [V1\AccountApiController::class, 'replyTicket']);
            Route::post('support/{ref}/close', [V1\AccountApiController::class, 'closeTicket']);
            Route::post('me/sign-out-others', [V1\AccountApiController::class, 'signOutOthers']);
            Route::post('privacy/export', [V1\AccountApiController::class, 'export']);
            Route::post('privacy/delete', [V1\AccountApiController::class, 'destroy']);
        });
        Route::middleware('throttle:otp')->group(function () {
            Route::post('me/contact', [V1\AccountApiController::class, 'startContactChange']);
            Route::post('me/contact/confirm', [V1\AccountApiController::class, 'confirmContactChange']);
            Route::post('privacy/code', [V1\AccountApiController::class, 'privacyCode']);
        });
        Route::post('assistant/chat', [AssistantController::class, 'chat'])->middleware('throttle:assistant');
    });
});
