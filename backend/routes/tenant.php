<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\InitializeTenancyByPath;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;
use App\Http\Controllers\API\V1\AuthController;
use App\Http\Controllers\API\V1\AdministratorController;
/*
|--------------------------------------------------------------------------
| Tenant Routes
|--------------------------------------------------------------------------
|
| Here you can register the tenant routes for your application.
| These routes are loaded by the TenantRouteServiceProvider.
|
| Feel free to customize them however you want. Good luck!
|
*/

// API Tenant
Route::middleware([
    'api',
    InitializeTenancyByDomain::class,
    PreventAccessFromCentralDomains::class,
])->group(function ($r) {
    $r->group([ 'prefix' => 'v1/tenant'], function ($r) {
        $r->group([ 'prefix' => 'user'], function ($r) {
            $r->post('register', [AuthController::class, 'register']);
            $r->post('login', [AuthController::class, 'login'])->middleware('tenant.active');

            $r->middleware(['auth:sanctum',  'tenant.active'])->group(function ($r) {
                $r->post('store', [AuthController::class, 'register']);
                $r->get('list', [AdministratorController::class, 'list']);
                $r->get('edit/{admin}', [AdministratorController::class, 'edit']);
                $r->patch('update/{admin}', [AdministratorController::class, 'update']);
                $r->get('auth', [AuthController::class, 'auth']);
                $r->post('logout', [AuthController::class, 'logout']);
            });
        });
    });
});
