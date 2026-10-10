<?php

use App\Http\Controllers\API\V1\Pos\PosCatalogController;
use App\Http\Controllers\API\V1\Pos\PosCustomerController;
use App\Http\Controllers\API\V1\Pos\PosDisplayController;
use App\Http\Controllers\API\V1\Pos\PosSaleController;
use App\Http\Middleware\InitializeTenancyByDomainOrRequestData;
use Illuminate\Support\Facades\Route;

Route::prefix('pos')
    ->middleware([
        'api',
    ])->group(function () {
        // Customer-facing display reads are public, rate-limited, and token-scoped.
        Route::middleware([
            InitializeTenancyByDomainOrRequestData::class,
            'tenant.active',
            'throttle:display-read',
        ])->group(function () {
            Route::get('display/state', [PosDisplayController::class, 'state']);
            Route::get('display/promotions', [PosDisplayController::class, 'promotions']);
        });

        Route::middleware([
            'auth:sanctum',
            InitializeTenancyByDomainOrRequestData::class,
            'tenant.active',
            'pos.access',
            'admin.permission:pos.view',
        ])->group(function () {
            // POS Catalog & Cart Pricing
            Route::get('branches', [PosCatalogController::class, 'branches']);
            Route::get('banners', [PosCatalogController::class, 'banners']);
            Route::get('categories', [PosCatalogController::class, 'categories']);
            Route::get('products', [PosCatalogController::class, 'products']);
            Route::get('products/{item}', [PosCatalogController::class, 'show']);
            Route::post('cart/price', [PosCatalogController::class, 'priceCart']);

            // POS Customers
            Route::get('customers', [PosCustomerController::class, 'index']);

            // POS Sales & Orders
            Route::get('sales', [PosSaleController::class, 'index']);
            Route::middleware('admin.permission:pos.edit')->group(function () {
                Route::put('sales/current', [PosSaleController::class, 'saveCurrent']);
                Route::post('sales/{sale}/restore', [PosSaleController::class, 'restore']);
                Route::post('display/sync', [PosDisplayController::class, 'sync']);
                Route::post('display/promotions', [PosDisplayController::class, 'savePromotions']);
            });
            Route::middleware('admin.permission:pos.create')->group(function () {
                Route::post('sales/complete-payment', [PosSaleController::class, 'completePayment']);
                Route::post('sales/complete-cash', [PosSaleController::class, 'completeCash']);
                Route::post('sales/hold', [PosSaleController::class, 'hold']);
            });
            Route::delete('sales/{sale}', [PosSaleController::class, 'destroy'])
                ->middleware('admin.permission:pos.delete');
        });
    });
