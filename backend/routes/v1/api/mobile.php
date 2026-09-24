<?php

use App\Http\Controllers\API\V1\Mobile\AddressController;
use App\Http\Controllers\API\V1\Mobile\CartController;
use App\Http\Controllers\API\V1\Mobile\AuthController;
use App\Http\Controllers\API\V1\Mobile\CatalogController;
use App\Http\Controllers\API\V1\Mobile\FavoriteController;
use App\Http\Controllers\API\V1\Mobile\HomeController;
use App\Http\Controllers\API\V1\Mobile\NotificationController;
use App\Http\Controllers\API\V1\Mobile\OrderController;
use App\Http\Controllers\API\V1\Mobile\ProfileController;
use App\Http\Middleware\InitializeTenancyByDomainOrRequestData;
use Illuminate\Support\Facades\Route;

Route::prefix('mobile')
    ->middleware([
        'api',
        InitializeTenancyByDomainOrRequestData::class,
        'tenant.active',
    ])->group(function () {
        Route::prefix('auth')->group(function () {
            Route::post('register', [AuthController::class, 'register']);
            Route::post('login', [AuthController::class, 'login']);
            Route::post('refresh', [AuthController::class, 'refresh']);
        });

        Route::get('bootstrap', [HomeController::class, 'bootstrap']);
        Route::get('legal', [HomeController::class, 'legal']);
        Route::get('branches', [CatalogController::class, 'branches']);
        Route::get('banners', [CatalogController::class, 'banners']);
        Route::get('categories', [CatalogController::class, 'categories']);
        Route::get('products', [CatalogController::class, 'products']);
        Route::get('products/{item}', [CatalogController::class, 'show']);
        Route::post('cart/price', [CatalogController::class, 'priceCart']);

        Route::middleware(['tenant.access'])->group(function () {
            Route::prefix('auth')->group(function () {
                Route::get('me', [AuthController::class, 'me']);
                Route::post('logout', [AuthController::class, 'logout']);
            });

            Route::get('profile', [ProfileController::class, 'show']);
            Route::match(['put', 'patch'], 'profile', [ProfileController::class, 'update']);

            Route::get('addresses', [AddressController::class, 'index']);
            Route::post('addresses', [AddressController::class, 'store']);
            Route::match(['put', 'patch'], 'addresses/{address}', [AddressController::class, 'update']);
            Route::delete('addresses/{address}', [AddressController::class, 'delete']);
            Route::post('addresses/{address}/default', [AddressController::class, 'makeDefault']);

            Route::get('favorites', [FavoriteController::class, 'index']);
            Route::post('favorites/toggle', [FavoriteController::class, 'toggle']);

            Route::get('cart', [CartController::class, 'index']);
            Route::post('cart/sync', [CartController::class, 'sync']);
            Route::delete('cart', [CartController::class, 'clear']);

            Route::get('orders', [OrderController::class, 'index']);
            Route::post('orders', [OrderController::class, 'store']);
            Route::post('sales', [OrderController::class, 'store']);
            Route::post('pos-sales', [OrderController::class, 'store']);
            Route::get('orders/{order}', [OrderController::class, 'show']);

            Route::get('notifications', [NotificationController::class, 'index']);
            Route::post('notifications/read-all', [NotificationController::class, 'markAllRead']);
            Route::post('notifications/{notification}/read', [NotificationController::class, 'markRead']);
        });
    });
