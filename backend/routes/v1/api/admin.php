<?php

use App\Http\Controllers\API\V1\AdministratorController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\V1\AuthController;
use App\Http\Controllers\API\V1\TenantController;
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
Route::post('login', [AuthController::class, 'login']);
Route::post('register', [AuthController::class, 'register']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    Route::get('auth', [AuthController::class, 'auth']);
    Route::post('logout', [AuthController::class, 'logout']);
    Route::get('dashboard', function() {
        return response()->json(['message' => 'Welcome to dashboard']);
    });

    // administrator
    Route::get('administrator', [AdministratorController::class, 'list']);
    Route::get('administrator/edit/{admin}', [AdministratorController::class, 'edit']);
    Route::patch('administrator/update/{admin}', [AdministratorController::class, 'update']);

    // tanent 
    Route::get('tenant', [TenantController::class, 'list']);
    Route::post('tenant', [TenantController::class, 'store']);
    Route::get('tenant/edit/{tenant}', [TenantController::class, 'edit']);
    Route::patch('tenant/update/{tenant}', [TenantController::class, 'update']);
    Route::delete('tenant/delete/{tenant}', [TenantController::class, 'delete']);
});
