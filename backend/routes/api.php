<?php

use App\Http\Controllers\Api\V1;
use Illuminate\Support\Facades\Route;

// Versioned API shared by the Flutter app. Docs: docs/api/openapi.yaml
Route::prefix('v1')->middleware('throttle:api')->group(function () {
    Route::get('meta', fn () => response()->json([
        'api_version' => 'v1',
        'brand' => config('brand')['brand.name'],
        'min_app_version' => config('app.min_mobile_version', '1.0.0'),
        'source_code_url' => config('brand')['source.repository_url'],
    ]))->name('api.v1.meta');

    Route::prefix('auth/otp')->middleware('throttle:otp')->group(function () {
        Route::post('start', [V1\AuthController::class, 'start']);
        Route::post('resend', [V1\AuthController::class, 'resend']);
        Route::post('verify', [V1\AuthController::class, 'verify']);
    });

    Route::get('catalogue/families', [V1\CatalogueController::class, 'families']);
    Route::get('catalogue/offerings', [V1\CatalogueController::class, 'offerings']);
    Route::get('catalogue/offerings/{slug}', [V1\CatalogueController::class, 'offering']);
    Route::get('places', [V1\CatalogueController::class, 'places'])->middleware('throttle:places');

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
        Route::get('wallet', [V1\WalletController::class, 'show']);
        Route::get('wallet/packages', [V1\WalletController::class, 'packages']);
        Route::get('wallet/transactions', [V1\WalletController::class, 'transactions']);
        Route::post('wallet/recharges', [V1\WalletController::class, 'recharge'])->middleware('throttle:payments');
        Route::get('payments', [V1\WalletController::class, 'index']);
        Route::get('payments/{ref}', [V1\WalletController::class, 'showPayment']);
        Route::post('payments/{ref}/confirm', [V1\WalletController::class, 'confirm'])->middleware('throttle:payments');
        Route::post('payments/{ref}/refresh', [V1\WalletController::class, 'refresh'])->middleware('throttle:payment-refresh');
    });
});

// Razorpay webhooks: signature-verified, no session/CSRF. Configure in Razorpay Dashboard.
Route::post('webhooks/razorpay', \App\Http\Controllers\RazorpayWebhookController::class)->middleware('throttle:webhooks')->name('webhooks.razorpay');
