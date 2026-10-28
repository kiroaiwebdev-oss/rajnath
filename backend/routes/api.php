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
    Route::post('enquiries', [V1\EnquiryApiController::class, 'store'])->middleware('throttle:enquiry');
    Route::get('tools', fn () => response()->json(['data' => collect(\App\Domain\Astro\Tools::TOOLS)->map(fn ($t, $k) => ['slug' => $k, 'title' => $t[0], 'input' => $t[1], 'kind' => $t[2], 'description' => $t[3]])->values()]));
    Route::post('tools/{slug}', [V1\ToolApiController::class, 'compute'])->middleware('throttle:tools');
    Route::get('panchang', [V1\ToolApiController::class, 'panchang'])->middleware('throttle:tools');

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
    });
});
