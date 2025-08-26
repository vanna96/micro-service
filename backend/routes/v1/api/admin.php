<?php

use App\Http\Controllers\API\V1\AdministratorController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\V1\AuthController;
use App\Http\Controllers\API\V1\TenantController;
use Stancl\Tenancy\Middleware\InitializeTenancyByPath;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;
use Stancl\Tenancy\Middleware\InitializeTenancyByRequestData;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;
use Laravel\Sanctum\PersonalAccessToken;
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

Route::middleware(['auth:sanctum', 'api'])->group(function ($r) {
    $r->group([ 'prefix' => 'administrator'], function ($r) {
        $r->get('list', [AdministratorController::class, 'list']);
        $r->get('edit/{admin}', [AdministratorController::class, 'edit']);
        $r->post('store', [AuthController::class, 'register']);
        $r->patch('update/{admin}', [AdministratorController::class, 'update']);
        $r->get('auth', [AuthController::class, 'auth']);
        $r->post('logout', [AuthController::class, 'logout']);
    });

    $r->group([ 'prefix' => 'tenant'], function ($r) {
        $r->get('list', [TenantController::class, 'list']);
        $r->post('store', [TenantController::class, 'store']);
        $r->get('edit/{tenant}', [TenantController::class, 'edit']);
        $r->patch('update/{tenant}', [TenantController::class, 'update']);
        $r->delete('delete/{tenant}', [TenantController::class, 'delete']);
    });
});

Route::middleware([ InitializeTenancyByRequestData::class, 'api'])->prefix('tenant')->group(function () {
    Route::get('/test', function () {
        $accessToken = request()->bearerToken();
        if (!$accessToken || !str_contains($accessToken, '|')) return response()->json(['message' => 'Invalid token format'], 401);

        [$id, $token] = explode('|', $accessToken, 2);
        $tokenModel = PersonalAccessToken::on('central')->find($id);

        if (!$tokenModel) return response()->json(['message' => 'Unauthenticated.'], 401);
        if (!hash_equals($tokenModel->token, hash('sha256', $token))) return response()->json(['message' => 'Unauthenticated.'], 401);

        $user = $tokenModel->tokenable;
        auth()->setUser($user);

        App\Models\Category::create(['name' => 'Test Category from tenant route']);
        return [
            'user' => $user,
            'tenant_id' => tenant('id'),
        ];
    });
});
