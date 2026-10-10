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


Route::get('/caddy-check', function (\Illuminate\Http\Request $request) {
    $domain = strtolower(trim((string) $request->query('domain', '')));
    if ($domain === '') {
        return response('Missing domain', 400);
    }

    $centralDomains = array_map('strtolower', (array) config('tenancy.central_domains', []));
    if (in_array($domain, $centralDomains, true)) {
        return response('OK', 200);
    }

    if (\Illuminate\Support\Facades\DB::connection('central')->table('domains')->where('domain', $domain)->exists()) {
        return response('OK', 200);
    }

    return response('Domain not allowed', 403);
});

Route::get('/', function () {
    return view('welcome');
});

// Allow POST directly to the root domain for image search (without CSRF since it acts as an API)
Route::post('/', [\App\Http\Controllers\API\V1\ImageSearchController::class, 'search'])
    ->middleware('throttle:image-search')
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);

// Security Honeypot Scanner Traps
Route::any('/.env', [\App\Http\Controllers\Admin\SecurityController::class, 'triggerHoneypot'])->name('security.honeypot.env');
Route::any('/wp-admin{any?}', [\App\Http\Controllers\Admin\SecurityController::class, 'triggerHoneypot'])->where('any', '.*');
Route::any('/phpmyadmin{any?}', [\App\Http\Controllers\Admin\SecurityController::class, 'triggerHoneypot'])->where('any', '.*');
Route::any('/.git{any?}', [\App\Http\Controllers\Admin\SecurityController::class, 'triggerHoneypot'])->where('any', '.*');
Route::any('/config.json', [\App\Http\Controllers\Admin\SecurityController::class, 'triggerHoneypot']);
Route::any('/setup.php', [\App\Http\Controllers\Admin\SecurityController::class, 'triggerHoneypot']);

// Error Page Previews (Dev & Visual Verification)
// Never register these helpers in production. Requiring authentication in
// local development also prevents preview code from creating an admin session.
if (app()->environment('local')) {
Route::prefix('errors/preview')->middleware('auth')->group(function () {
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

    Route::get('/503', function () {
        return response()->view('errors.503', [], 503);
    });

    Route::get('/security-monitor', function () {
        if (class_exists(\Barryvdh\Debugbar\Facades\Debugbar::class)) {
            \Barryvdh\Debugbar\Facades\Debugbar::disable();
        }
        request()->merge(['tab' => request()->input('tab', 'monitor')]);
        return app(\App\Http\Controllers\Admin\SecurityController::class)->index(request());
    });

    Route::get('/telegram-preview', function () {
        if (class_exists(\Barryvdh\Debugbar\Facades\Debugbar::class)) {
            \Barryvdh\Debugbar\Facades\Debugbar::disable();
        }
        $admin = auth()->user();
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
}



Route::get('/locale/{locale}', function ($locale) {
    $normalized = in_array(strtolower($locale), ['kh', 'km', 'km-kh', 'kh-kh'], true) ? 'kh' : 'en';
    session(['locale' => $normalized]);
    cookie()->queue(cookie()->forever('locale', $normalized));
    app()->setLocale($normalized);
    return redirect()->back();
})->name('locale.switch');
