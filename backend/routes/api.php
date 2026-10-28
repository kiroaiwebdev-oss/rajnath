<?php

use Illuminate\Support\Facades\Route;

// Versioned API shared by the Flutter app. Customer auth endpoints arrive in phase 3.
Route::prefix('v1')->middleware('throttle:api')->group(function () {
    Route::get('meta', fn () => response()->json([
        'api_version' => 'v1',
        'brand' => config('brand')['brand.name'],
        'min_app_version' => config('app.min_mobile_version', '1.0.0'),
        'source_code_url' => config('brand')['source.repository_url'],
    ]))->name('api.v1.meta');
});
