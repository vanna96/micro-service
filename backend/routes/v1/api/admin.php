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
Route::post('register', [AuthController::class, 'register']);
Route::post('login', [AuthController::class, 'login']);
// Route::post('forgot-password', [AuthController::class, 'forgotPassword']);
// Route::post('reset-password', [AuthController::class, 'resetPassword']);

// // Protected admin routes
// Route::middleware('auth:sanctum')->group(function () {
//     Route::post('logout', [AuthController::class, 'logout']);

//     // Example: admin-only routes
//     Route::get('dashboard', function () {
//         return response()->json(['message' => 'Admin dashboard']);
//     });
// });
