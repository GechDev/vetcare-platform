<?php

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
