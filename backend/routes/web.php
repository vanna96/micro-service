<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

// Security Honeypot Scanner Traps
Route::any('/.env', [\App\Http\Controllers\Admin\SecurityController::class, 'triggerHoneypot'])->name('security.honeypot.env');
Route::any('/wp-admin{any?}', [\App\Http\Controllers\Admin\SecurityController::class, 'triggerHoneypot'])->where('any', '.*');
Route::any('/phpmyadmin{any?}', [\App\Http\Controllers\Admin\SecurityController::class, 'triggerHoneypot'])->where('any', '.*');
Route::any('/.git{any?}', [\App\Http\Controllers\Admin\SecurityController::class, 'triggerHoneypot'])->where('any', '.*');
Route::any('/config.json', [\App\Http\Controllers\Admin\SecurityController::class, 'triggerHoneypot']);
Route::any('/setup.php', [\App\Http\Controllers\Admin\SecurityController::class, 'triggerHoneypot']);

// Error Page Previews (Dev & Visual Verification)
Route::prefix('errors/preview')->group(function () {
    Route::get('/429', function () {
        return response()->view('errors.429', [
            'ip' => request()->ip(),
            'incidentId' => 'SEC-JCV6MNL2',
            'retryAfter' => 60,
            'limit' => 120,
            'currentCount' => 125,
        ], 429);
    });

    Route::get('/403', function () {
        return response()->view('errors.security-blocked', [
            'ip' => request()->ip(),
            'reason' => 'Access Denied: Malicious probe intercepted by Security Firewall.',
            'incidentId' => 'SEC-WAF98721',
        ], 403);
    });

    Route::get('/404', function () {
        return response()->view('errors.404', [], 404);
    });

    Route::get('/500', function () {
        return response()->view('errors.500', [], 500);
    });

    Route::get('/security-monitor', function () {
        if (class_exists(\Barryvdh\Debugbar\Facades\Debugbar::class)) {
            \Barryvdh\Debugbar\Facades\Debugbar::disable();
        }
        $admin = \App\Models\User::first();
        if ($admin) {
            auth()->login($admin);
        }
        request()->merge(['tab' => request()->input('tab', 'monitor')]);
        return app(\App\Http\Controllers\Admin\SecurityController::class)->index(request());
    });

    Route::get('/telegram-preview', function () {
        if (class_exists(\Barryvdh\Debugbar\Facades\Debugbar::class)) {
            \Barryvdh\Debugbar\Facades\Debugbar::disable();
        }
        $admin = \App\Models\User::first();
        if ($admin) {
            auth()->login($admin);
        }
        $tenant = $admin ? $admin->tenants()->first() : \App\Models\Tenant::first();
        if ($tenant) {
            session(['admin_selected_tenant_id' => $tenant->id]);
            if (request()->hasSession()) {
                request()->session()->put('admin_selected_tenant_id', $tenant->id);
            }
        }
        request()->merge(['tab' => request()->input('tab', 'error_log')]);
        return app(\App\Http\Controllers\Admin\TelegramNotificationSettingController::class)->index(
            app(\App\Services\TelegramNotificationService::class)
        );
    });
});


