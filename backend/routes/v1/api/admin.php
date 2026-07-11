<?php

use App\Http\Controllers\API\V1\AdministratorController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\V1\AuthController;
use App\Http\Controllers\API\V1\TenantController;
use Stancl\Tenancy\Middleware\InitializeTenancyByRequestData;
use App\Http\Controllers\API\V1\CategoryController;
use App\Http\Controllers\API\V1\ItemController;
use App\Http\Controllers\API\V1\BranchController;
use App\Http\Controllers\API\V1\CartPricingController;
/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::get('sanctum/csrf-cookie', [\Laravel\Sanctum\Http\Controllers\CsrfCookieController::class, 'show']);
Route::middleware(['api'])->group(function () {

    Route::prefix('user')->group(function () {
        // Public routes
        Route::post('register', [AuthController::class, 'register']);
        Route::post('login', [AuthController::class, 'login']);

        // Protected user routes
        Route::middleware(['auth:sanctum'])->group(function () {
            Route::get('list', [AdministratorController::class, 'list']);
            Route::post('store', [AuthController::class, 'register']);
            Route::get('edit/{admin}', [AdministratorController::class, 'edit']);
            Route::patch('update/{admin}', [AdministratorController::class, 'update']);
            Route::get('auth', [AuthController::class, 'auth']);
            Route::post('logout', [AuthController::class, 'logout']);
        });
    });

    // Protected tenant routes
    Route::prefix('tenant')->middleware(['auth:sanctum'])->group(function () {
        Route::get('list', [TenantController::class, 'list']);
        Route::post('store', [TenantController::class, 'store']);
        Route::get('edit/{tenant}', [TenantController::class, 'edit']);
        Route::patch('update/{tenant}', [TenantController::class, 'update']);
        Route::delete('delete/{tenant}', [TenantController::class, 'delete']);
    });

});

Route::middleware([
        'api',
        InitializeTenancyByRequestData::class,
        'tenant.active',
        'tenant.access'
    ])->group(function ($r) {

    $r->prefix('category')
        ->group(function () {
            Route::get('list', [CategoryController::class, 'list']);
            Route::post('store', [CategoryController::class, 'store']);
            Route::get('edit/{category}', [CategoryController::class, 'edit']);
            Route::match(['put', 'patch'], 'update/{category}', [CategoryController::class, 'update']);
            Route::delete('delete/{category}', [CategoryController::class, 'delete']);
        });

    $r->prefix('branch')
        ->group(function () {
            Route::get('list', [BranchController::class, 'list']);
            Route::post('store', [BranchController::class, 'store']);
            Route::get('edit/{branch}', [BranchController::class, 'edit']);
            Route::match(['put', 'patch'], 'update/{branch}', [BranchController::class, 'update']);
            Route::delete('delete/{branch}', [BranchController::class, 'delete']);
        });

    $r->prefix('item')
        ->group(function () {
            Route::get('list', [ItemController::class, 'list']);
            Route::post('store', [ItemController::class, 'store']);
            Route::get('edit/{item}', [ItemController::class, 'edit']);
            Route::match(['put', 'patch'], 'update/{item}', [ItemController::class, 'update']);
            Route::delete('delete/{item}', [ItemController::class, 'delete']);
        });

    $r->prefix('cart')
        ->group(function () {
            Route::post('price', [CartPricingController::class, 'price']);
        });
});
