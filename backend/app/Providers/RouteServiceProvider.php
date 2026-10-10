<?php

namespace App\Providers;

use App\Models\SecuritySetting;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to the "home" route for your application.
     *
     * Typically, users are redirected here after authentication.
     *
     * @var string
     */
    public const HOME = '/admin/dashboard';

    /**
     * Define your route model bindings, pattern filters, and other route configuration.
     *
     * @return void
     */
    public function boot()
    {
        $this->configureRateLimiting();

        $this->routes(function () {
            Route::get('/internal/metrics', \App\Http\Controllers\Internal\MonitoringMetricsController::class)
                ->name('monitoring.metrics');

            $centralDomains = array_unique(array_filter(array_merge(
                (array) config('tenancy.central_domains', []),
                ['localhost', '127.0.0.1']
            )));

            foreach ($centralDomains as $centralDomain) {
                Route::middleware('api')
                    ->domain($centralDomain)
                    ->prefix('v1/api')
                    ->group(base_path('routes/v1/api/admin.php'));
            }

            Route::middleware('api')
                ->prefix('v1/api')
                ->group(base_path('routes/v1/api/admin.php'));

            Route::middleware('api')
                ->prefix('v1/api')
                ->group(base_path('routes/v1/api/mobile.php'));

            Route::middleware('api')
                ->prefix('v1/api')
                ->group(base_path('routes/v1/api/pos.php'));

            Route::middleware('api')
                ->prefix('v1/api')
                ->group(base_path('routes/api.php'));

            Route::middleware('web')
                ->group(base_path('routes/admin.php'));

            Route::middleware('web')
                ->group(base_path('routes/web.php'));

            // Tenant domains
            Route::middleware('api') 
                ->group(function () {
                    Route::group([
                        'domain' => '{tenant}.localhost',
                        'middleware' => [
                            \Stancl\Tenancy\Middleware\InitializeTenancyByDomain::class,
                            \Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains::class,
                        ]
                    ], function () {
                        require base_path('routes/tenant.php');
                    });
                });
        });
    }

    /**
     * Configure the rate limiters for the application.
     *
     * @return void
     */
    protected function configureRateLimiting()
    {
        RateLimiter::for('api', function (Request $request) {
            $isUnderAttack = SecuritySetting::getBool('under_attack_mode', false);
            $limit = $isUnderAttack ? 30 : SecuritySetting::getInt('global_rate_limit_per_minute', 120);

            return Limit::perMinute($limit)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('login', function (Request $request) {
            $login = Str::lower(trim((string) $request->input('username', $request->input('email', 'unknown'))));

            return [
                Limit::perMinute(6)->by('login-ip:'.$request->ip()),
                Limit::perMinute(4)->by('login-account:'.hash('sha256', $login).'|'.$request->ip()),
            ];
        });

        RateLimiter::for('token-refresh', fn (Request $request) => [
            Limit::perMinute(12)->by('refresh-ip:'.$request->ip()),
        ]);

        RateLimiter::for('image-search', fn (Request $request) => [
            Limit::perMinute(6)->by('image-search-minute:'.$request->ip()),
            Limit::perHour(30)->by('image-search-hour:'.$request->ip()),
        ]);

        RateLimiter::for('display-read', fn (Request $request) => [
            Limit::perMinute(60)->by('display-read:'.$request->ip()),
        ]);
    }
}
