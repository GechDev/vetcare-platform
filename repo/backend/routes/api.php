<?php

use App\Http\Controllers\Api\RentalsController;
use App\Http\Controllers\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::get('/csrf-token', function (Request $request) {
        return response()->json([
            'csrf_token' => $request->session()->token(),
            'request_id' => $request->header('X-Request-Id') ?: (string) Str::uuid(),
        ]);
    });

    Route::post('/login', [AuthController::class, 'login'])->middleware('cookie.csrf');

    Route::middleware('auth.api')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout'])->middleware('cookie.csrf');
        Route::get('/me', [AuthController::class, 'me'])->middleware('permission:auth.me.read');
        Route::post('/password/change', [AuthController::class, 'changePassword'])
            ->middleware(['cookie.csrf', 'permission:auth.password.change']);
    });
});

Route::middleware(['auth.api', 'audit.mutations'])->group(function () {
    Route::prefix('rentals')->group(function () {
        Route::get('/assets', [RentalsController::class, 'listAssets'])->middleware('permission:rentals.read');
        Route::post('/assets', [RentalsController::class, 'createAsset'])->middleware('permission:rentals.write');
        Route::patch('/assets/{id}', [RentalsController::class, 'updateAsset'])->middleware('permission:rentals.write');
        Route::post('/checkouts', [RentalsController::class, 'checkout'])->middleware('permission:rentals.checkout');
        Route::post('/checkouts/{id}/return', [RentalsController::class, 'returnCheckout'])->middleware('permission:rentals.checkout');
        Route::get('/checkouts/{id}', [RentalsController::class, 'getCheckout'])->middleware('permission:rentals.read');
        Route::post('/assets/{id}/transfer', [RentalsController::class, 'requestTransfer'])->middleware('permission:rentals.transfer.request');
        Route::post('/transfers/{id}/approve', [RentalsController::class, 'approveTransfer'])->middleware('permission:rentals.transfer.approve');
    });
});

Route::get('/health', function (Request $request) {
    return response()->json([
        'data' => [
            'service' => 'vetops-backend',
            'status' => 'ok',
            'timestamp_utc' => now()->utc()->toISOString(),
            'currency' => [
                'code' => config('vetops.currency.code', 'USD'),
                'amount_format' => config('vetops.currency.amount_format', 'integer_cents'),
            ],
        ],
        'request_id' => $request->header('X-Request-Id') ?: (string) Str::uuid(),
    ]);
});
