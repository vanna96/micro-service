<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\V1\AuthController;
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

Route::prefix('v1/admin')->group(function () {
    // Public routes
    Route::get('sanctum/csrf-cookie', [\Laravel\Sanctum\Http\Controllers\CsrfCookieController::class, 'show']);
    Route::post('login', [AuthController::class, 'login']);
    Route::post('register', [AuthController::class, 'register']); // optional

    // Protected routes
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('auth', [AuthController::class, 'auth']); // check logged-in user
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('dashboard', function() {
            return response()->json(['message' => 'Welcome to dashboard']);
        });
    });
});
