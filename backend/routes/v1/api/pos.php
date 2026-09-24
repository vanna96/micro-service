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
        InitializeTenancyByDomainOrRequestData::class,
        'tenant.active',
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
        Route::put('sales/current', [PosSaleController::class, 'saveCurrent']);
        Route::post('sales/complete-payment', [PosSaleController::class, 'completePayment']);
        Route::post('sales/complete-cash', [PosSaleController::class, 'completeCash']);
        Route::post('sales/hold', [PosSaleController::class, 'hold']);
        Route::post('sales/{sale}/restore', [PosSaleController::class, 'restore']);
        Route::delete('sales/{sale}', [PosSaleController::class, 'destroy']);

        // POS Customer-Facing Second Screen Display
        Route::post('display/sync', [PosDisplayController::class, 'sync']);
        Route::get('display/state', [PosDisplayController::class, 'state']);
        Route::get('display/promotions', [PosDisplayController::class, 'promotions']);
        Route::post('display/promotions', [PosDisplayController::class, 'savePromotions']);
    });
