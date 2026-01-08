<?php

use App\Http\Controllers\Api\InventoryController;
use App\Http\Controllers\Api\MasterDataController;
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
    Route::prefix('master')->group(function () {
        Route::get('/{entity}', [MasterDataController::class, 'index'])->middleware('permission:master.read');
        Route::post('/{entity}', [MasterDataController::class, 'store'])->middleware('permission:master.write');
        Route::patch('/{entity}/{id}', [MasterDataController::class, 'update'])->middleware('permission:master.write');
        Route::get('/{entity}/{id}/versions', [MasterDataController::class, 'versions'])->middleware('permission:master.read');
        Route::post('/{entity}/{id}/revert', [MasterDataController::class, 'revert'])->middleware('permission:master.revert');
    });

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

    Route::prefix('inventory')->group(function () {
        Route::get('/items', [InventoryController::class, 'items'])->middleware('permission:inventory.read');
        Route::post('/receipts', [InventoryController::class, 'receipt'])->middleware('permission:inventory.write');
        Route::post('/issues', [InventoryController::class, 'issue'])->middleware('permission:inventory.write');
        Route::post('/transfers', [InventoryController::class, 'transfer'])->middleware('permission:inventory.write');
        Route::post('/stocktakes', [InventoryController::class, 'createStocktake'])->middleware('permission:inventory.stocktake.write');
        Route::post('/stocktakes/{id}/lines', [InventoryController::class, 'addStocktakeLines'])->middleware('permission:inventory.stocktake.write');
        Route::post('/stocktakes/{id}/approve-variance', [InventoryController::class, 'approveVariance'])->middleware('permission:inventory.stocktake.approve');
        Route::put('/reservation-strategy', [InventoryController::class, 'setReservationStrategy'])->middleware('permission:inventory.write');
        Route::post('/service-orders/{id}/reserve', [InventoryController::class, 'reserveServiceOrder'])->middleware('permission:inventory.write');
        Route::post('/service-orders/{id}/close', [InventoryController::class, 'closeServiceOrder'])->middleware('permission:inventory.write');
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
