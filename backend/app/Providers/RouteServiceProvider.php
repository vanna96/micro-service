<?php

namespace App\Providers;

use App\Models\SecuritySetting;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

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
    }
}
